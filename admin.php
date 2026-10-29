<?php
/**
 * Панель управления администратора (ПР №4).
 * Доступ только после входа администратора (см. login.php).
 * Функции: просмотр списка заявок, одобрение, отклонение (снятие), удаление,
 * а также управление каталогом номеров (добавление/редактирование/удаление).
 */
require_once __DIR__ . '/config.php';

if (!is_admin()) {
    redirect('login.php');
}

$pdo = db();
$message = '';

// ---- Обработка действий администратора (только POST, с валидацией) ----
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'approve':   // Одобрить заявку
            if ($bookingId > 0) {
                $st = $pdo->prepare("UPDATE bookings SET status='Одобрена' WHERE id=:id");
                $st->execute([':id' => $bookingId]);
                $message = 'Заявка #' . $bookingId . ' одобрена.';
            }
            break;

        case 'reject':    // Отклонить заявку (снять одобрение, вернуть в рассмотрение)
            if ($bookingId > 0) {
                $st = $pdo->prepare("UPDATE bookings SET status='Новая' WHERE id=:id AND status='Одобрена'");
                $st->execute([':id' => $bookingId]);
                $message = 'Одобрение заявки #' . $bookingId . ' снято (заявка возвращена в рассмотрение).';
            }
            break;

        case 'decline':   // Полностью отклонить заявку
            if ($bookingId > 0) {
                $st = $pdo->prepare("UPDATE bookings SET status='Отклонена' WHERE id=:id");
                $st->execute([':id' => $bookingId]);
                $message = 'Заявка #' . $bookingId . ' отклонена.';
            }
            break;

        case 'delete':    // Удалить заявку
            if ($bookingId > 0) {
                $st = $pdo->prepare("DELETE FROM bookings WHERE id=:id");
                $st->execute([':id' => $bookingId]);
                $message = 'Заявка #' . $bookingId . ' удалена.';
            }
            break;

        case 'add_room':  // Добавить номер в каталог
            $st = $pdo->prepare(
                "INSERT INTO rooms (category, price, seats, area, has_ac, has_tv, has_fridge, has_wifi, has_minibar, description, image)
                 VALUES (:category, :price, :seats, :area, :ac, :tv, :fr, :wi, :mb, :descr, :img)"
            );
            $st->execute([
                ':category' => (string)(isset($_POST['category']) ? $_POST['category'] : 'стандартный'),
                ':price'    => (float)(isset($_POST['price']) ? $_POST['price'] : 0),
                ':seats'    => (int)(isset($_POST['seats']) ? $_POST['seats'] : 1),
                ':area'     => (float)(isset($_POST['area']) ? $_POST['area'] : 0),
                ':ac'       => isset($_POST['has_ac']) ? 1 : 0,
                ':tv'       => isset($_POST['has_tv']) ? 1 : 0,
                ':fr'       => isset($_POST['has_fridge']) ? 1 : 0,
                ':wi'       => isset($_POST['has_wifi']) ? 1 : 0,
                ':mb'       => isset($_POST['has_minibar']) ? 1 : 0,
                ':descr'    => trim((string)(isset($_POST['description']) ? $_POST['description'] : '')),
                ':img'      => trim((string)(isset($_POST['image']) ? $_POST['image'] : 'standart.png')),
            ]);
            $message = 'Номер добавлен в каталог.';
            break;

        case 'delete_room': // Удалить номер из каталога
            $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
            if ($roomId > 0) {
                $st = $pdo->prepare("DELETE FROM rooms WHERE id=:id");
                $st->execute([':id' => $roomId]);
                $message = 'Номер #' . $roomId . ' удалён из каталога.';
            }
            break;

        default:
            break;
    }
}

// ---- Данные для отображения ----
$statusFilter = (string)(isset($_GET['status']) ? $_GET['status'] : '');
if (!in_array($statusFilter, ['Новая', 'Одобрена', 'Отклонена'], true)) {
    $statusFilter = '';
}

$sql = "SELECT b.*, r.category AS room_category, r.price AS room_price, r.image AS room_image
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id";
$params = [];
if ($statusFilter !== '') {
    $sql .= " WHERE b.status = :status";
    $params[':status'] = $statusFilter;
}
$sql .= " ORDER BY b.created_at DESC, b.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$bookings = $st->fetchAll();

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY category, price')->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/index.css">
    <title>Панель администратора — Сладкие сны</title>
</head>
<body class="container">
<header class="d-flex flex-wrap justify-content-center py-3 align-items-center">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
        <span class="fs-4 mx-2 fw-medium">Сладкие сны</span>
    </a>
    <a href="logout.php" class="btn btn-outline-danger my-1">Выйти</a>
</header>

<main>
    <div class="d-flex justify-content-between flex-wrap align-items-center">
        <h1>Панель администратора</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success" role="alert"><?php echo e($message); ?></div>
    <?php endif; ?>

    <!-- Фильтр по статусу заявок -->
    <div class="my-3">
        <a href="admin.php" class="btn btn-sm <?php echo $statusFilter === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Все заявки</a>
        <a href="admin.php?status=Новая" class="btn btn-sm <?php echo $statusFilter === 'Новая' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Новые</a>
        <a href="admin.php?status=Одобрена" class="btn btn-sm <?php echo $statusFilter === 'Одобрена' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Одобренные</a>
        <a href="admin.php?status=Отклонена" class="btn btn-sm <?php echo $statusFilter === 'Отклонена' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Отклонённые</a>
    </div>

    <?php if (count($bookings) === 0): ?>
        <div class="alert alert-info">Заявок нет.</div>
    <?php endif; ?>

    <div class="d-flex justify-content-around flex-wrap align-items-center">
        <?php foreach ($bookings as $b): ?>
            <?php
                if ($b['status'] === 'Одобрена')  { $badge = 'bg-success'; }
                elseif ($b['status'] === 'Отклонена') { $badge = 'bg-danger'; }
                else { $badge = 'bg-secondary'; }
            ?>
            <div class="card m-2" style="width: 20rem;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h5 class="card-title">№<?php echo (int)$b['id']; ?> · <?php echo e(ucfirst($b['room_category'])); ?></h5>
                        <span class="badge <?php echo $badge; ?>"><?php echo e($b['status']); ?></span>
                    </div>
                    <p class="mb-1"><strong><?php echo e($b['surname'] . ' ' . $b['firstname']); ?></strong></p>
                    <p class="mb-1">Тел: <?php echo e($b['phone']); ?></p>
                    <p class="mb-1">Email: <?php echo e($b['email']); ?></p>
                    <ul class="list-group mb-2">
                        <li class="list-group-item">Дата заезда: <?php echo date('d.m.Y', strtotime($b['check_in'])); ?></li>
                        <li class="list-group-item">Дата выезда: <?php echo date('d.m.Y', strtotime($b['check_out'])); ?></li>
                    </ul>
                    <div class="d-grid gap-2">
                        <form method="post" action="admin.php">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                            <?php if ($b['status'] !== 'Одобрена'): ?>
                                <button name="action" value="approve" class="btn btn-success w-100 mb-1">Одобрить</button>
                            <?php else: ?>
                                <button name="action" value="reject" class="btn btn-warning w-100 mb-1">Снять одобрение</button>
                            <?php endif; ?>
                            <button name="action" value="decline" class="btn btn-outline-danger w-100 mb-1">Отклонить</button>
                            <button name="action" value="delete" class="btn btn-danger w-100"
                                    onclick="return confirm('Удалить заявку №<?php echo (int)$b['id']; ?>?');">Удалить</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Управление каталогом номеров -->
    <hr class="my-4">
    <h2>Управление каталогом номеров</h2>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th><th>Категория</th><th>Цена</th><th>Мест</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rooms as $r): ?>
                <tr>
                    <td><?php echo (int)$r['id']; ?></td>
                    <td><?php echo e(ucfirst($r['category'])); ?></td>
                    <td><?php echo e(number_format((float)$r['price'], 0, '.', ' ')); ?> ₽</td>
                    <td><?php echo (int)$r['seats']; ?></td>
                    <td>
                        <form method="post" action="admin.php" style="display:inline;">
                            <input type="hidden" name="room_id" value="<?php echo (int)$r['id']; ?>">
                            <button name="action" value="delete_room" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Удалить номер #<?php echo (int)$r['id']; ?>?');">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Форма добавления номера -->
    <h3 class="mt-3">Добавить номер</h3>
    <form method="post" action="admin.php" class="row g-3 border rounded p-3 bg-light">
        <input type="hidden" name="action" value="add_room">
        <div class="col-md-3">
            <label class="form-label">Категория</label>
            <select name="category" class="form-select">
                <option value="стандартный">Стандартный</option>
                <option value="студия">Студия</option>
                <option value="люкс">Люкс</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Цена, ₽/сутки</label>
            <input type="number" min="0" name="price" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Мест</label>
            <input type="number" min="1" name="seats" class="form-control" value="2" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Площадь, м²</label>
            <input type="number" min="0" name="area" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Изображение</label>
            <input type="text" name="image" class="form-control" value="standart.png">
        </div>
        <div class="col-md-8">
            <label class="form-label">Описание</label>
            <input type="text" name="description" class="form-control">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-3">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="has_tv" value="1"><label class="form-check-label">Телевизор</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="has_fridge" value="1"><label class="form-check-label">Холодильник</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="has_ac" value="1"><label class="form-check-label">Кондиционер</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="has_wifi" value="1"><label class="form-check-label">Wi-Fi</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="has_minibar" value="1"><label class="form-check-label">Мини-бар</label></div>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Добавить номер</button>
        </div>
    </form>
</main>

<footer class="py-2 my-2">
    <ul class="nav justify-content-between align-items-center">
        <li class="nav-item"><span class="nav-link text-body-secondary">г. Москва, ул. Ивовая, 48</span></li>
        <li class="nav-item"><a href="index.php" class="nav-link">← На сайт</a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger">Выйти</a></li>
    </ul>
</footer>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>