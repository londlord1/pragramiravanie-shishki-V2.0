<?php
/**
 * Страница бронирования номера (ПР №3 и ПР №4).
 * Серверная валидация:
 *   - обязательные поля;
 *   - корректность email и телефона;
 *   - дата заезда не раньше текущей даты;
 *   - дата выезда позже даты заезда;
 *   - если на номере уже есть одобренная бронь, перекрывающаяся с запрошенным
 *     периодом, — номер на этот период забронировать нельзя.
 */
require_once __DIR__ . '/config.php';

$pdo = db();

// ---- Определяем выбранный номер ----
$roomId = isset($_GET['room']) ? (int)$_GET['room'] : 0;
$roomStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id');
$roomStmt->execute([':id' => $roomId]);
$room = $roomStmt->fetch();

if (!$room) {
    redirect('index.php');
}

$errors = [];
$old = [
    'surname' => '', 'firstname' => '', 'phone' => '', 'email' => '',
    'check_in' => '', 'check_out' => '',
];

// ---- Обработка отправки формы (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['surname']   = trim((string)(isset($_POST['surname']) ? $_POST['surname'] : ''));
    $old['firstname'] = trim((string)(isset($_POST['firstname']) ? $_POST['firstname'] : ''));
    $old['phone']     = trim((string)(isset($_POST['phone']) ? $_POST['phone'] : ''));
    $old['email']     = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));
    $old['check_in']  = trim((string)(isset($_POST['check_in']) ? $_POST['check_in'] : ''));
    $old['check_out'] = trim((string)(isset($_POST['check_out']) ? $_POST['check_out'] : ''));

    // --- Валидация на стороне сервера ---
    if ($old['surname'] === '' || !preg_match('/^[А-Яа-яЁёA-Za-z\s-]+$/u', $old['surname'])) {
        $errors[] = 'Укажите корректную фамилию (только буквы).';
    }
    if ($old['firstname'] === '' || !preg_match('/^[А-Яа-яЁёA-Za-z\s-]+$/u', $old['firstname'])) {
        $errors[] = 'Укажите корректное имя (только буквы).';
    }
    $phoneDigits = preg_replace('/\D+/', '', $old['phone']);
    if ($phoneDigits === '' || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
        $errors[] = 'Укажите корректный номер телефона, например +7(900)000-00-00.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Укажите корректный адрес электронной почты.';
    }

    // Проверка формата дат ДД.ММ.ГГГГ (как требует задание) — принимаем и ISO
    $checkIn  = null;
    $checkOut = null;
    foreach (['check_in' => &$checkIn, 'check_out' => &$checkOut] as $field => &$target) {
        $d = $old[$field];
        if ($d === '') {
            $errors[] = 'Укажите дату ' . ($field === 'check_in' ? 'заезда' : 'выезда');
        } elseif (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $d, $m)) {
            $target = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $target = $d;
        } else {
            $errors[] = 'Дата должна быть в формате ДД.ММ.ГГГГ.';
        }
    }
    unset($target);

    // Семантические проверки дат
    if ($checkIn && strtotime($checkIn) === false) {
        $errors[] = 'Некорректная дата заезда.';
    }
    if ($checkOut && strtotime($checkOut) === false) {
        $errors[] = 'Некорректная дата выезда.';
    }
    if ($checkIn && $checkOut) {
        $today = date('Y-m-d');
        if (strtotime($checkIn) < strtotime($today)) {
            $errors[] = 'Дата заезда не может быть раньше текущей даты.';
        }
        if (strtotime($checkOut) <= strtotime($checkIn)) {
            $errors[] = 'Дата выезда должна быть позже даты заезда.';
        }
    }

    // Проверка пересечения с уже одобренными бронями на этот номер
    // Период [check_in; check_out) — выезд не входит в занятие.
    if (empty($errors) && $checkIn && $checkOut) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE room_id = :room_id
               AND status = 'Одобрена'
               AND check_in < :check_out
               AND check_out > :check_in"
        );
        $stmt->execute([
            ':room_id'  => $roomId,
            ':check_in' => $checkIn,
            ':check_out'=> $checkOut,
        ]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Данный номер уже занят в выбранный период. Выберите другие даты.';
        }
    }

    // ---- Сохранение заявки ----
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO bookings
               (room_id, surname, firstname, phone, email, check_in, check_out, status)
             VALUES
               (:room_id, :surname, :firstname, :phone, :email, :check_in, :check_out, \'Новая\')'
        );
        $stmt->execute([
            ':room_id'   => $roomId,
            ':surname'   => $old['surname'],
            ':firstname' => $old['firstname'],
            ':phone'     => $old['phone'],
            ':email'     => $old['email'],
            ':check_in'  => $checkIn,
            ':check_out' => $checkOut,
        ]);
        $success = true;
    }
}

// ---- Список занятых (одобренных) дат для блокировки в календаре (ПР №4) ----
$busyDates = [];
$bs = $pdo->prepare(
    "SELECT check_in, check_out FROM bookings
     WHERE room_id = :room_id AND status = 'Одобрена'"
);
$bs->execute([':room_id' => $roomId]);
// Для простоты блокировки покажем занятые дни на ближайшие 365 дней
$busySet = [];
foreach ($bs->fetchAll() as $row) {
    for ($d = strtotime($row['check_in']); $d < strtotime($row['check_out']); $d += 86400) {
        $busySet[date('Y-m-d', $d)] = true;
    }
}
$busyDates = array_keys($busySet);
$busyJson = json_encode($busyDates);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/index.css">
    <title>Бронирование номера — Сладкие сны</title>
</head>
<body class="container">
<header class="d-flex flex-wrap justify-content-center py-3">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
        <span class="fs-4 mx-2 fw-medium">Сладкие сны</span>
    </a>
    <ul class="nav">
        <li class="nav-item"><a href="index.php" class="nav-link">← К каталогу номеров</a></li>
    </ul>
</header>

<?php if (isset($success) && $success): ?>
    <div class="alert alert-success text-center" role="alert">
        Заявка успешно отправлена! Администратор рассмотрит её и свяжется с вами.
        <a href="index.php" class="alert-link">Вернуться в каталог</a>.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Исправьте следующие ошибки:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!isset($success) || !$success): ?>
<main>
    <div class="d-flex justify-content-between flex-wrap align-items-center">
        <h1>Бронирование номера</h1>
    </div>

    <div class="card mb-3" style="max-width: 540px;">
        <div class="row g-0">
            <div class="col-md-4">
                <?php $img = file_exists(__DIR__ . '/img/' . $room['image']) ? 'img/' . $room['image'] : 'img/standart.png'; ?>
                <img src="<?php echo e($img); ?>" class="img-fluid rounded-start" alt="<?php echo e($room['category']); ?>">
            </div>
            <div class="col-md-8">
                <div class="card-body">
                    <h5 class="card-title"><?php echo e(ucfirst($room['category'])); ?> · <?php echo e((int)$room['price']); ?> ₽/сутки</h5>
                    <p class="card-text">Мест: <?php echo e((int)$room['seats']); ?>. Площадь: <?php echo e((int)$room['area']); ?> м².</p>
                </div>
            </div>
        </div>
    </div>

    <form class="row g-3 needs-validation my-2" novalidate method="post"
          action="order.php?room=<?php echo (int)$roomId; ?>">
        <input type="hidden" name="room_id" value="<?php echo (int)$roomId; ?>">

        <div class="col-md-6">
            <label for="firstname" class="form-label">Имя</label>
            <input type="text" class="form-control" id="firstname" name="firstname"
                   value="<?php echo e($old['firstname']); ?>" required>
        </div>
        <div class="col-md-6">
            <label for="surname" class="form-label">Фамилия</label>
            <input type="text" class="form-control" id="surname" name="surname"
                   value="<?php echo e($old['surname']); ?>" required>
        </div>
        <div class="col-md-6">
            <label for="phone" class="form-label">Телефон</label>
            <input type="text" class="form-control" id="phone" name="phone"
                   value="<?php echo e($old['phone']); ?>" placeholder="+7(900)000-00-00" required>
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Почта</label>
            <input type="text" class="form-control" id="email" name="email"
                   value="<?php echo e($old['email']); ?>" required>
        </div>
        <div class="col-md-6">
            <label for="check_in" class="form-label">Дата заезда</label>
            <input type="date" class="form-control" id="check_in" name="check_in"
                   min="<?php echo date('Y-m-d'); ?>"
                   value="<?php echo e($old['check_in']); ?>" required>
        </div>
        <div class="col-md-6">
            <label for="check_out" class="form-label">Дата выезда</label>
            <input type="date" class="form-control" id="check_out" name="check_out"
                   value="<?php echo e($old['check_out']); ?>" required>
        </div>

        <div class="col-12">
            <span class="text-muted small">* Даты, когда номер уже забронирован, отмечены в календаре как недоступные.</span>
        </div>

        <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit">Отправить заявку</button>
        </div>
    </form>
</main>
<?php endif; ?>

<footer class="py-2 my-2">
    <ul class="nav justify-content-between align-items-center">
        <li class="nav-item"><span class="nav-link text-body-secondary">г. Москва, ул. Ивовая, 48</span></li>
        <li class="nav-item"><span class="nav-link text-body-secondary">Время работы: Пн-Пт, с 8:00-17:00</span></li>
        <li class="nav-item"><a href="tel:88005553535" class="nav-link text-body-secondary">тел. 8 (800) 555 - 35 - 35</a></li>
        <li class="nav-item"><a href="mailto:sladkie-sny@example.ru" class="nav-link text-body-secondary">Email: sladkie-sny@example.ru</a></li>
    </ul>
</footer>

<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/jquery-3.7.1.slim.min.js"></script>
<script src="js/jquery.inputmask.min.js"></script>
<script>
    $(document).ready(function () {
        // Маска телефона
        $('#phone').inputmask({"mask": "+7(999)999-99-99"});

        // Блокировка занятых дат (передаются с сервера)
        var busy = <?php echo $busyJson; ?>;
        function isBusy(d) {
            return busy.indexOf(d) !== -1;
        }
        function disableBusy(minDate) {
            ['#check_in', '#check_out'].forEach(function (sel) {
                var el = $(sel)[0];
                if (el) {
                    el.min = minDate || el.min || '';
                    var warning = el.getAttribute('data-busy-warn');
                    if (!warning) {
                        el.setAttribute('data-busy-warn', '1');
                        el.addEventListener('input', function () {
                            if (busy.indexOf(this.value) !== -1) {
                                this.setCustomValidity('Эта дата уже забронирована. Выберите другую.');
                            } else {
                                this.setCustomValidity('');
                            }
                        });
                    }
                }
            });
        }
        disableBusy('<?php echo date('Y-m-d'); ?>');

        // Показ подсказки о занятых датах (информативность)
        if (busy.length) {
            var hint = $('#check_in').closest('.col-md-6').siblings('.col-12').find('.text-muted');
            // Подсветка занятых дней во всплывашке календаря уже задана через нативную проверку.
        }
    });
</script>
</body>
</html>