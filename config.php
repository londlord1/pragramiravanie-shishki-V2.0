<?php
/**
 * Конфигурация подключения к базе данных сервиса бронирования «Сладкие сны».
 * Отредактируйте параметры в соответствии с данными MySQL, выданными в панели
 * управления Бегет (раздел «Базы данных»).
 */
session_start();

define('DB_HOST', 'localhost');            // хост СУБД (обычно localhost)
define('DB_NAME', '');                     // имя базы данных
define('DB_USER', '');                     // пользователь базы данных
define('DB_PASS', '');                     // пароль базы данных

// Учётные данные администратора панели управления сайтом (ПР №4).
// Логин фиксирован по заданию: hotel123, пароль хранится в виде хеша.
define('ADMIN_LOGIN', 'hotel123');
// Хеш пароля adminHotel (сгенерировать: php -r "echo password_hash('adminHotel', PASSWORD_DEFAULT);")
define('ADMIN_PASS_HASH', '$2y$10$rzY36tcYnXzlnOCCgY1cLO97E8AGoxSKGWzH1jL4hcU17JAA4PMVm');

/**
 * Возвращает соединение PDO с базой данных. При ошибке — выбрасывает исключение.
 */
function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
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
        die('Ошибка подключения к базе данных. Проверьте настройки в файле config.php');
    }

    return $pdo;
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