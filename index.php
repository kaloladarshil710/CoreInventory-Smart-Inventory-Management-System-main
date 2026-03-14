<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
