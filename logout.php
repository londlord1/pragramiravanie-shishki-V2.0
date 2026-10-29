<?php
/**
 * Выход администратора из панели управления.
 */
require_once __DIR__ . '/config.php';

session_regenerate_id(true);
unset($_SESSION['admin']);
redirect('login.php');