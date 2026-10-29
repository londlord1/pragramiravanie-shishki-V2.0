-- ============================================================
-- База данных сервиса бронирования номеров гостиницы «Сладкие сны»
-- ПР №3 (МДК 03.02): разработка базы данных по предметной области
-- ПР №4: доработка сервиса (занятые периоды, панель администратора)
-- СУБД: MySQL 5.7+ / 8.0  (кодировка utf8mb4)
-- ============================================================

-- Создание базы (раскомментируйте и укажите имя, либо выполните импорт
-- в уже созданную в панели Бегет базу данных).
-- CREATE DATABASE IF NOT EXISTS `hotel_sladkiesny`
--   DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `hotel_sladkiesny`;

-- ------------------------------------------------------------
-- Таблица номеров (каталог)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `rooms`;

CREATE TABLE `rooms` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`    ENUM('стандартный','студия','люкс') NOT NULL DEFAULT 'стандартный',
  `price`       DECIMAL(10,2) NOT NULL,
  `seats`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `area`        DECIMAL(6,2) DEFAULT NULL,
  `has_ac`      TINYINT(1) NOT NULL DEFAULT 0,     -- кондиционер
  `has_tv`      TINYINT(1) NOT NULL DEFAULT 0,     -- телевизор
  `has_fridge`  TINYINT(1) NOT NULL DEFAULT 0,     -- холодильник
  `has_wifi`    TINYINT(1) NOT NULL DEFAULT 0,     -- Wi-Fi
  `has_minibar` TINYINT(1) NOT NULL DEFAULT 0,     -- мини-бар
  `description` TEXT,
  `image`       VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Таблица заявок на бронирование
-- Статусы: 'Новая', 'Одобрена', 'Отклонена'
-- ------------------------------------------------------------
CREATE TABLE `bookings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id`     INT UNSIGNED NOT NULL,
  `surname`     VARCHAR(100) NOT NULL,
  `firstname`   VARCHAR(100) NOT NULL,
  `phone`       VARCHAR(40)  NOT NULL,
  `email`       VARCHAR(150) NOT NULL,
  `check_in`    DATE NOT NULL,
  `check_out`   DATE NOT NULL,
  `status`      ENUM('Новая','Одобрена','Отклонена') NOT NULL DEFAULT 'Новая',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`room_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`check_in`,`check_out`),
  CONSTRAINT `fk_booking_room` FOREIGN KEY (`room_id`)
    REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Начальное наполнение каталога номеров
-- (характеристики приведены в приложении «Характеристики номеров.txt»)
-- ------------------------------------------------------------
INSERT INTO `rooms`
  (`category`,`price`,`seats`,`area`,`has_ac`,`has_tv`,`has_fridge`,`has_wifi`,`has_minibar`,`description`,`image`) VALUES
('стандартный', 3000,  2, 18.0, 0, 1, 0, 1, 0, 'Полный санузел, двуспальная кровать, телевизор.',            'standart.png'),
('стандартный', 3400,  2, 20.0, 0, 1, 0, 1, 0, 'Полный санузел, двуспальная кровать, телевизор.',            'standart.png'),
('стандартный', 3800,  3, 22.0, 0, 1, 0, 1, 0, 'Полный санузел, двуспальная кровать, телевизор.',            'standart.png'),
('студия',      4600,  2, 26.0, 0, 1, 1, 1, 0, 'Кухонный уголок, полный санузел, двуспальная кровать, телевизор, холодильник.', 'studio.jpg'),
('студия',      5400,  3, 30.0, 1, 1, 1, 1, 0, 'Кухонный уголок, полный санузел, кондиционер, холодильник.', 'studio.jpg'),
('студия',      6200,  4, 34.0, 1, 1, 1, 1, 0, 'Кухонный уголок, кондиционер, холодильник, двуспальная кровать.', 'studio.jpg'),
('люкс',        8500,  2, 42.0, 1, 1, 1, 1, 1, 'Двухкомнатный, полный санузел, кондиционер, холодильник.',   'lux.png'),
('люкс',       11500,  3, 48.0, 1, 1, 1, 1, 1, 'Двухкомнатный, кондиционер, холодильник, мини-бар.',         'lux.png'),
('люкс',       15000,  4, 56.0, 1, 1, 1, 1, 1, 'Двухкомнатный, кондиционер, холодильник, мини-бар.',         'lux.png');