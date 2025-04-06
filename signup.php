<?php
require 'config.php'; // Ensure database connection is included

$username = $email = '';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $passwordRaw = $_POST['password'];

    // Basic validation
    if (!preg_match('/@gmail\.com$/', $email)) {
        $error = "Email must be a Gmail address (end with @gmail.com)";
    } elseif (!preg_match('/^(?=.*\d).{6,}$/', $passwordRaw)) {
        $error = "Password must be at least 6 characters long and contain at least one number.";
    } else {
        $password = password_hash($passwordRaw, PASSWORD_DEFAULT); // Hash password

        // Check if username already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);

        if ($checkStmt->fetch()) {
            $error = "Username already taken!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $email, $password])) {
                header("Location: login.php");
                exit;
            } else {
                $error = "Registration failed!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
         body {
                background: url('images/bg1.jpg') no-repeat center center fixed;
                background-size: cover;
                font-family: 'Segoe UI', sans-serif;
            }
        .card {
            background-color: #fffaf1; /* Lighter cream */
            border: 1px solid #d6c7b0;
            border-radius: 16px;
        }

        .form-label {
            color: #5d4037; /* Brown */
            font-weight: 600;
        }

        .form-control {
            background-color: #fffdf7;
            border: 1px solid #d6c7b0;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #a1887f;
        }

        .btn-success {
            background-color: #795548;
            border-color: #6d4c41;
        }

        .btn-success:hover {
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
                <h2 class="text-center mb-4" style="color: #5d4037;">Sign Up</h2>
                <?php if (!empty($error)) echo "<p class='text-danger text-center'>$error</p>"; ?>
                <form action="signup.php" method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required
                               value="<?= htmlspecialchars($username ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               placeholder="example@gmail.com"
                               value="<?= htmlspecialchars($email ?? '') ?>"
                               pattern="^[\w.+\-]+@gmail\.com$"
                               title="Email must end with @gmail.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required
                               pattern="^(?=.*\d).{6,}$"
                               title="At least 6 characters and 1 number">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Sign Up</button>
                    <div class="text-center mt-3">
                        <a href="login.php">Already have an account? Login</a>
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
