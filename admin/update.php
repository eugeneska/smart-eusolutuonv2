<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
requireAdmin();

$csrf = $_POST['csrf'] ?? '';
checkCsrf($csrf);

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$pdo = pdo();

switch ($action) {
    case 'processed':
        $stmt = $pdo->prepare("UPDATE contact_requests SET status='processed' WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        break;
    case 'spam':
        $stmt = $pdo->prepare("UPDATE contact_requests SET status='spam' WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        break;
    case 'delete':
        $stmt = $pdo->prepare("DELETE FROM contact_requests WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        break;
    default:
        http_response_code(400);
        exit('Unknown action');
}

header('Location: /admin/index.php?'.http_build_query($_GET));
exit;