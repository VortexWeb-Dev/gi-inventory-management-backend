<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    require __DIR__ . "/src/{$class}.php";
});

header("Content-Type: application/json");

$id = $_GET['id'] ?? null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$controller = new InventoryController();
$controller->processRequest($_SERVER['REQUEST_METHOD'], $id, $page);

exit;
