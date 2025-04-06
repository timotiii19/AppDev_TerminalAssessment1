<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';  
$dbname = 'expense_db'; 
$username = 'root';  // Change this if you have a different MySQL username
$password = '';  // Change this if you have a MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (!function_exists('checkAuth')) {
    function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit();
        }
    }
}

?>
