<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require 'config.php'; // your DB config

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (!preg_match('/@gmail\.com$/', $email)) {
        $error = "Only Gmail addresses are allowed.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600);

            $update = $pdo->prepare("UPDATE users SET password_reset_token = ?, token_expiry = ? WHERE email = ?");
            $update->execute([$token, $expiry, $email]);

            $resetLink = "http://localhost/my_project/reset_password.php?token=$token";

            // Send Email
            $mail = new PHPMailer(true);

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'xayvieraustin019@gmail.com'; // use your Gmail
                $mail->Password = 'seqo yeuo rsic attf';   // app password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('xayvieraustin019@gmail.com', 'Expense Tracker App');

                $mail->addAddress($email);
                $mail->Subject = 'Reset Your Password';
                $mail->Body = "Click this link to reset your password:\n\n$resetLink";

                // Updated sending logic
                if ($mail->send()) {
                    $success = "A reset link has been sent to your Gmail. It may take some time.";
                } else {
                    $error = "Mailer Error: " . $mail->ErrorInfo;
                }
            }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
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
            <h2 class="text-center mb-4">Forgot Password</h2>
            <?php if (isset($error)) echo "<p class='text-danger text-center'>$error</p>"; ?>
            <?php if (isset($success)) echo "<p class='text-success text-center'>$success</p>"; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Enter your Gmail address</label>
                    <input type="email" name="email" class="form-control" required placeholder="example@gmail.com">
                </div>
                <button type="submit" class="btn btn-custom w-100">Send Reset Link</button>
                <div class="text-center mt-3">
                    <a href="login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
