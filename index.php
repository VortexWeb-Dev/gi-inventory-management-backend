<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    require __DIR__ . "/src/{$class}.php";
});

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header("Content-Type: application/json");

$id = $_GET['id'] ?? null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$controller = new InventoryController();
$controller->processRequest($_SERVER['REQUEST_METHOD'], $id, $page);

exit;
