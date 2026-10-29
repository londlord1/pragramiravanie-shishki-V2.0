<?php
/**
 * Конфигурация подключения к базе данных сервиса бронирования «Сладкие сны».
 * Отредактируйте параметры в соответствии с данными MySQL, выданными в панели
 * управления Бегет (раздел «Базы данных → MySQL»).
 */
session_start();

define('DB_HOST', 'localhost');            // хост СУБД (обычно localhost)
define('DB_NAME', '');                     // имя базы данных
define('DB_USER', '');                     // пользователь базы данных
define('DB_PASS', '');                     // пароль базы данных

// Учётные данные администратора панели управления сайтом (ПР №4).
// Логин фиксирован по заданию: hotel123.
define('ADMIN_LOGIN', 'hotel123');
// Хеш пароля adminHotel (сгенерировать: php -r "echo password_hash('adminHotel', PASSWORD_DEFAULT);")
define('ADMIN_PASS_HASH', '$2y$10$rzY36tcYnXzlnOCCgY1cLO97E8AGoxSKGWzH1jL4hcU17JAA4PMVm');

/**
 * Возвращает соединение PDO с базой данных.
 * При первом подключении автоматически создаёт недостающие таблицы.
 */
function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_NAME === '' || DB_USER === '') {
        http_response_code(500);
        die(
            '<h1>База данных ещё не настроена</h1>' .
            '<p>Впишите данные MySQL из панели Бегет (Раздел «Базы данных → MySQL») ' .
            'в файл <code>config.php</code> (константы <code>DB_NAME</code>, <code>DB_USER</code>, ' .
            '<code>DB_PASS</code>), затем обновите страницу.</p>'
        );
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connect error: ' . $e->getMessage());
        http_response_code(500);
        die(
            '<h1>Не удалось подключиться к базе данных</h1>' .
            '<p>Проверьте правильность <code>DB_HOST</code>, <code>DB_NAME</code>, ' .
            '<code>DB_USER</code>, <code>DB_PASS</code> в файле <code>config.php</code>. ' .
            'Пользователю БД должны быть выданы права на указанную базу.</p>' .
            '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>'
        );
    }

    ensure_schema($pdo);
    return $pdo;
}

/**
 * Создаёт таблицы, если их ещё нет (мини-миграция).
 */
function ensure_schema(PDO $pdo)
{
    try {
        $pdo->query('SELECT 1 FROM rooms LIMIT 1');
        $pdo->query('SELECT 1 FROM bookings LIMIT 1');
        return; // таблицы уже есть — схема развёрнута
    } catch (PDOException $e) {
        // таблиц нет — создаём
    }

    $schema = file_get_contents(__DIR__ . '/database.sql');
    if ($schema === false) {
        return; // файл-схема отсутствует, полагаемся на ручной импорт
    }
    // Удаляем комментарии и блок CREATE DATABASE/USE.
    $schema = preg_replace('/^--.*$/m', '', $schema);
    $schema = preg_replace('/(CREATE DATABASE|USE)[^;]+;/i', '', $schema);
    // Выполняем каждый оператор отдельно (совместимо со всеми драйверами).
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

/**
 * Экранирование вывода для защиты от XSS.
 */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Является ли текущая сессия сессией администратора.
 */
function is_admin()
{
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

/**
 * Перенаправление и выход.
 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}