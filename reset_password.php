<?php
require 'config.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT id, token_expiry FROM users WHERE password_reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user && strtotime($user['token_expiry']) > time()) {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $newPassword = $_POST['password'];

            if (!preg_match('/^(?=.*\d).{6,}$/', $newPassword)) {
                $error = "Password must be at least 6 characters and contain a number.";
            } else {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = ?, password_reset_token = NULL, token_expiry = NULL WHERE id = ?");
                $update->execute([$hashed, $user['id']]);

                header("Location: login.php?reset=success");
                exit;
            }
        }
    } else {
        $error = "Token is invalid or expired.";
    }
} else {
    $error = "No token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f9f5f0;
        }
        .card {
            background-color: #fffaf3;
            border: 1px solid #d3c0ae;
        }
        .btn-custom {
            background-color: #8b5e3c;
            color: white;
        }
        .btn-custom:hover {
            background-color: #6f4428;
        }
    </style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="col-md-5">
        <div class="card p-4 shadow-lg">
            <h2 class="text-center mb-4">Reset Your Password</h2>
            <?php if (isset($error)) echo "<p class='text-danger text-center'>$error</p>"; ?>
            <?php if (!isset($error)) : ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" required pattern="^(?=.*\d).{6,}$" title="At least 6 characters with 1 number">
                    </div>
                    <button type="submit" class="btn btn-custom w-100">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
