<?php
echo "<pre>";
print_r($_POST);
echo "</pre>";

session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Error: Invalid request method.");
}

if (empty($_POST['expense_id'])) {
    die("Error: expense_id is missing! Debug: " . print_r($_POST, true));
}
$expense_id = $_POST['expense_id'];


$expense_id = $_POST['expense_id'];
$category_id = $_POST['edit_expense_category'];
$description = $_POST['edit_expense_description'];
$amount = $_POST['edit_expense_amount'];
$quantity = $_POST['edit_expense_quantity'];
$payment_method = $_POST['edit_expense_payment'];
$purchase_date = $_POST['edit_purchase_date'];



if (
    empty($expense_id) || empty($category_id) || empty($description) || 
    !is_numeric($amount) || !is_numeric($quantity) || empty($payment_method) || empty($purchase_date)
) {
    die("Error: Missing or invalid required fields.");
}

$amount_per_piece = $amount / $quantity;

global $pdo;
$stmt = $pdo->prepare("UPDATE expenses 
    SET category_id = ?, description = ?, total_amount = ?, quantity = ?, amount_per_piece = ?, payment_method = ?, purchase_date = ?
    WHERE id = ? AND user_id = ?");

if ($stmt->execute([$category_id, $description, $amount, $quantity, $amount_per_piece, $payment_method, $purchase_date, $expense_id, $_SESSION['user_id']])) {
    header("Location: dashboard.php");
    exit;
} else {
    echo "Error updating expense.";
}
?>

