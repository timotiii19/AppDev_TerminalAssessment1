<?php
require 'config.php';

// Check if a session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// If the user is already logged in, redirect to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
        }
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #f9f5f0;
        }
        .navbar {
            background-color: #8b5e3c;
        }
        .navbar-brand, .nav-link {
            color: #fffaf3 !important;
        }
        .navbar-brand:hover, .nav-link:hover {
            color: #e8d8c3 !important;
        }
        .hero-section {
            height: calc(100vh - 60px);
            background: url('images/bg1.jpg') no-repeat center center;
            background-size: cover;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            color:rgb(132, 113, 85);
            filter: contrast(1.05) brightness(1.05);
        }

        .hero-section h1 {
            font-size: 4rem;
            font-weight: 600;
        }
        .hero-section p {
            font-size: 1.9rem;
            margin: 15px 0 30px;
        }
        .btn-custom {
            background-color: #8b5e3c;
            color: white;
            padding: 12px 25px;
            font-size: 1.2rem;
            border: none;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #6f4428;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">💰 Expense Tracker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <h1>Take Control of Your Finances</h1>
        <p>Track your expenses effortlessly and stay on top of your budget.</p>
        <a href="signup.php" class="btn btn-custom">Get Started</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
