<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Error: User not logged in");
}

$user_id = $_SESSION['user_id']; // Get logged-in user's ID

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $category_id = isset($_POST['category']) ? intval($_POST['category']) : 0;
    $description = isset($_POST['description']) ? trim(htmlspecialchars($_POST['description'])) : '';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0.0;
    
    $raw_quantity = isset($_POST['quantity']) ? trim($_POST['quantity']) : '';
    $quantity = (isset($_POST['na']) && $_POST['na'] === '1')
        ? 'N/A'
        : (is_numeric($raw_quantity) && intval($raw_quantity) > 0 && intval($raw_quantity) <= 100
            ? intval($raw_quantity)
            : null);
    
    $payment_method = isset($_POST['payment_method']) ? trim(htmlspecialchars($_POST['payment_method'])) : '';
    $purchase_date = isset($_POST['purchase_date']) ? $_POST['purchase_date'] : null;

    // Final validation check
    if (
        $category_id > 0 &&
        !empty($description) &&
        $total_amount > 0 &&
        !empty($payment_method) &&
        ($quantity !== null)
    ) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (category_id, description, total_amount, quantity, payment_method, purchase_date, user_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $category_id,
                $description,
                $total_amount,
                $quantity,
                $payment_method,
                $purchase_date,
                $user_id
            ]);

            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    } else {
        die("Error: Please fill in all required fields correctly.");
    }
}
?>
