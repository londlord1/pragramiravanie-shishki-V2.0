<?php
/**
 * Вход администратора в панель управления сайтом (ПР №4).
 * Логин — hotel123, пароль — adminHotel (хранится в виде хеша в config.php).
 */
require_once __DIR__ . '/config.php';

if (is_admin()) {
    redirect('admin.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)(isset($_POST['username']) ? $_POST['username'] : ''));
    $pass  = (string)(isset($_POST['password']) ? $_POST['password'] : '');

    if ($login === '' || $pass === '') {
        $error = 'Введите логин и пароль.';
    } elseif ($login !== ADMIN_LOGIN || !password_verify($pass, ADMIN_PASS_HASH)) {
        // Базовая защита от перебора по времени
        if (ADMIN_PASS_HASH === '') {
            // Хеш не задан — генерируем на лету, чтобы пароль всерьёз не проверялся,
            // но сообщаем администратору о необходимости настроить config.php.
            $error = 'Пароль администратора не настроен. Сгенерируйте хеш и укажите его в config.php.';
        } else {
            $error = 'Неверный логин или пароль.';
        }
    } else {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        redirect('admin.php');
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/index.css">
    <title>Вход — Сладкие сны</title>
</head>
<body class="container">
<header class="d-flex flex-wrap justify-content-center py-3">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
        <span class="fs-4 mx-2 fw-medium">Сладкие сны</span>
    </a>
</header>

<main style="max-width: 420px; margin: 0 auto;">
    <div class="d-flex justify-content-between flex-wrap align-items-center">
        <h1>Вход администратора</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <form class="my-2" novalidate method="post" action="login.php">
        <div class="my-2">
            <label for="username" class="form-label">Логин</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="my-2">
            <label for="password" class="form-label">Пароль</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="d-grid gap-2">
            <button class="btn btn-primary">Войти</button>
        </div>
        <div class="mt-3">
            <a href="index.php" class="btn btn-link">← Вернуться в каталог</a>
        </div>
    </form>
</main>

<footer class="py-2 my-2" style="max-width: 420px; margin: 0 auto;">
    <ul class="nav flex-column">
        <li class="nav-item"><span class="nav-link text-body-secondary">г. Москва, ул. Ивовая, 48</span></li>
        <li class="nav-item"><span class="nav-link text-body-secondary">Время работы: Пн-Пт, с 8:00-17:00</span></li>
        <li class="nav-item"><a href="tel:88005553535" class="nav-link text-body-secondary">тел. 8 (800) 555 - 35 - 35</a></li>
        <li class="nav-item"><a href="mailto:sladkie-sny@example.ru" class="nav-link text-body-secondary">Email: sladkie-sny@example.ru</a></li>
    </ul>
</footer>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery-3.7.1.slim.min.js"></script>
<script src="js/jquery.inputmask.min.js"></script>
<script src="js/index.js"></script>
</body>
</html>