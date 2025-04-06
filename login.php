<?php
session_start();
require 'config.php';

$username = ''; // ✅ Set default value

// Handle Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    session_write_close();
    header("Location: index.php");
    exit;
}


// Handle Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Query the user by username
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id']; // Store user ID in session
        header("Location: dashboard.php"); // Redirect to dashboard
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
    <title>Login - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('images/bg1.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            background-color: #fffaf1; /* Lighter cream */
            border: 1px solid #d6c7b0;
            border-radius: 16px;
        }

        .form-label {
            color: #5d4037; /* Brown label */
            font-weight: 600;
        }

        .form-control {
            background-color: #fffdf7;
            border: 1px solid #d6c7b0;
        }

        .btn-primary {
            background-color: #795548; /* Brown button */
            border-color: #6d4c41;
        }

        .btn-primary:hover {
            background-color: #6d4c41;
            border-color: #5d4037;
        }

        .text-center a {
            color: #795548;
        }

        .text-center a:hover {
            text-decoration: underline;
        }

        .text-danger {
            color: #d32f2f;
        }
    </style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="col-md-4">
            <div class="card p-4 shadow-lg">
                <h2 class="text-center mb-4" style="color: #5d4037;">Login</h2>
                <?php if (isset($error)) echo "<p class='text-danger text-center'>$error</p>"; ?>
                <form action="login.php" method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required
                               oninput="this.value = this.value.trim();"
                               value="<?= htmlspecialchars(trim($username ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required pattern=".{6,}" title="At least 6 characters">
                    </div>
                    <div class="text-left mt-2 mb-3">
                            <a href="forgot_password.php" class="text-decoration-underline" style="color: #795548;">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    <div class="text-center mt-3">
                        <a href="signup.php">Don't have an account? Sign Up</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

<a href="index.php" class="position-absolute top-0 start-0 m-3 text-decoration-none"
   style="color: #fffaf1; font-family: 'Fredoka', sans-serif; font-size: 1.1rem; font-weight: 600;">
   ← Back to Welcome Page
</a>


</html>
