<?php
/**
 * Каталог номеров гостиницы «Сладкие сны».
 * Реализована серверная фильтрация по категории, цене и количеству мест.
 */
require_once __DIR__ . '/config.php';

$pdo = db();

// ---- Приём и нормализация параметров фильтра (только сервер) ----
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$price_min = isset($_GET['price_min']) ? trim($_GET['price_min']) : '';
$price_max = isset($_GET['price_max']) ? trim($_GET['price_max']) : '';
$seats = isset($_GET['seats']) ? trim($_GET['seats']) : '';

$allowedCategories = ['стандартный', 'студия', 'люкс'];
if (!in_array($category, $allowedCategories, true)) {
    $category = '';
}

// Проверки диапазона цены: допускаются только неотрицательные целые числа
if ($price_min === '' || !ctype_digit($price_min)) { $price_min = ''; }
if ($price_max === '' || !ctype_digit($price_max)) { $price_max = ''; }
if ($seats === '' || !ctype_digit($seats) || (int)$seats < 1) { $seats = ''; }

// ---- Построение запроса с фильтрацией на стороне сервера ----
$sql = "SELECT * FROM rooms WHERE 1=1";
$params = [];

if ($category !== '') {
    $sql .= " AND category = :category";
    $params[':category'] = $category;
}
if ($price_min !== '') {
    $sql .= " AND price >= :price_min";
    $params[':price_min'] = (int)$price_min;
}
if ($price_max !== '') {
    $sql .= " AND price <= :price_max";
    $params[':price_max'] = (int)$price_max;
}
if ($seats !== '') {
    $sql .= " AND seats >= :seats";
    $params[':seats'] = (int)$seats;
}
$sql .= " ORDER BY category, price";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// ---- Характеристики для отображения ----
function room_features(array $room)
{
    $f = [];
    if ($room['has_tv'])      $f[] = 'Телевизор';
    if ($room['has_fridge'])  $f[] = 'Холодильник';
    if ($room['has_ac'])      $f[] = 'Кондиционер';
    if ($room['has_wifi'])    $f[] = 'Wi-Fi';
    if ($room['has_minibar']) $f[] = 'Мини-бар';
    return $f;
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
    <title>Сладкие сны — каталог номеров</title>
</head>
<body class="container">
<header class="d-flex flex-wrap justify-content-center py-3">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
        <span class="fs-4 mx-2 fw-medium">Сладкие сны</span>
    </a>
    <?php if (is_admin()): ?>
        <a href="admin.php" class="btn btn-outline-secondary my-1">Панель администратора</a>
    <?php else: ?>
        <a href="login.php" class="btn btn-outline-secondary my-1">Вход для администратора</a>
    <?php endif; ?>
</header>

<main>
    <div class="d-flex justify-content-between flex-wrap">
        <h1>Каталог номеров</h1>
    </div>

    <!-- Блок фильтрации (данные уходят на сервер методом GET) -->
    <form method="get" action="index.php" class="row g-2 align-items-end mb-3 p-3 border rounded bg-light">
        <div class="col-md-3">
            <label for="category" class="form-label">Категория</label>
            <select name="category" id="category" class="form-select">
                <option value="">Все категории</option>
                <option value="стандартный" <?php echo $category === 'стандартный' ? 'selected' : ''; ?>>Стандартный</option>
                <option value="студия" <?php echo $category === 'студия' ? 'selected' : ''; ?>>Студия</option>
                <option value="люкс" <?php echo $category === 'люкс' ? 'selected' : ''; ?>>Люкс</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="price_min" class="form-label">Цена от, ₽</label>
            <input type="number" min="0" name="price_min" id="price_min" class="form-control" value="<?php echo e($price_min); ?>">
        </div>
        <div class="col-md-2">
            <label for="price_max" class="form-label">Цена до, ₽</label>
            <input type="number" min="0" name="price_max" id="price_max" class="form-control" value="<?php echo e($price_max); ?>">
        </div>
        <div class="col-md-2">
            <label for="seats" class="form-label">Мест не менее</label>
            <input type="number" min="1" name="seats" id="seats" class="form-control" value="<?php echo e($seats); ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Применить</button>
            <a href="index.php" class="btn btn-danger flex-grow-1">Сбросить</a>
        </div>
    </form>

    <?php if (!empty($price_min) && (int)$price_min > (int)$price_max && $price_max !== ''): ?>
        <div class="alert alert-danger">Минимальная цена не может быть больше максимальной.</div>
    <?php endif; ?>

    <?php if (count($rooms) === 0): ?>
        <div class="alert alert-info">По заданным условиям номера не найдены. Измените фильтр.</div>
    <?php endif; ?>

    <div class="d-flex justify-content-around flex-wrap align-items-center">
        <?php foreach ($rooms as $r): ?>
            <div class="card m-2" style="width: 20rem;">
                <?php $img = file_exists(__DIR__ . '/img/' . $r['image']) ? 'img/' . $r['image'] : 'img/standart.png'; ?>
                <img src="<?php echo e($img); ?>" class="card-img-top" alt="<?php echo e($r['category']); ?>">
                <div class="card-body">
                    <h3>Категория: <?php echo e(ucfirst($r['category'])); ?></h3>
                    <h5>Цена: <?php echo e(number_format((float)$r['price'], 0, '.', ' ')); ?> ₽ / сутки</h5>
                    <h5>Мест: <?php echo e($r['seats']); ?></h5>
                    <h5>Площадь: <?php echo e(number_format((float)$r['area'], 0, '.', ' ')); ?> м²</h5>
                    <h5>Характеристики:</h5>
                    <ul class="list-group">
                        <?php $feat = room_features($r); if (empty($feat)): ?>
                            <li class="list-group-item">—</li>
                        <?php else: foreach ($feat as $f): ?>
                            <li class="list-group-item"><?php echo e($f); ?></li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
                <div class="d-grid gap-2 p-2">
                    <a href="order.php?room=<?php echo (int)$r['id']; ?>" class="btn btn-success">Забронировать</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<footer class="py-2 my-2">
    <ul class="nav justify-content-between align-items-center">
        <li class="nav-item"><span class="nav-link text-body-secondary">г. Москва, ул. Ивовая, 48</span></li>
        <li class="nav-item"><span class="nav-link text-body-secondary">Время работы: Пн-Пт, с 8:00-17:00</span></li>
        <li class="nav-item"><a href="tel:88005553535" class="nav-link text-body-secondary">тел. 8 (800) 555 - 35 - 35</a></li>
        <li class="nav-item"><a href="mailto:sladkie-sny@example.ru" class="nav-link text-body-secondary">Email: sladkie-sny@example.ru</a></li>
    </ul>
</footer>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>