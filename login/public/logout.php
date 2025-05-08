<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/auth.php';

$auth = new Auth($pdo);
$auth->logout();

header('Location: index.php');
exit;