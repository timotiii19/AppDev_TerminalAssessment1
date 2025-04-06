<?php
require 'config.php';
checkAuth();

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid expense ID.");
}

// Ensure the expense belongs to the logged-in user
$stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
if ($stmt->execute([$id, $user_id])) {
    header("Location: index.php");
    exit();
} else {
    echo "Error deleting expense.";
}
?>