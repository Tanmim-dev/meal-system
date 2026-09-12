<?php

date_default_timezone_set("Asia/Dhaka");

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

$message = "";
$error = "";
$reset_link = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF protection
    verify_csrf();

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        $stmt = $pdo->prepare("
            SELECT id, name, email
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * Always show the same normal message.
         * This prevents account enumeration.
         */

        if ($user) {

            // Delete previous unused tokens
            $delete = $pdo->prepare("
                DELETE FROM password_resets
                WHERE user_id = ?
                  AND used_at IS NULL
            ");

            $delete->execute([$user["id"]]);

            // Generate secure random token
            $token = bin2hex(random_bytes(32));

            // Store only token hash
            $token_hash = hash("sha256", $token);

            // Token expires after 30 minutes
            $insert = $pdo->prepare("
                INSERT INTO password_resets
                (user_id, token_hash, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");

            $insert->execute([
                $user["id"],
                $token_hash
            ]);

            /*
             * Temporary local testing link.
             *
             * This will later be replaced by an email
             * when SMTP is connected.
             */
            $reset_link =
                "http://localhost/meal-system/reset_password.php?token="
                . urlencode($token);
        }

        /*
         * Always show the same message whether the
         * email exists or not.
         */
        $message =
            "If an account with that email exists, password reset instructions have been sent.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Forgot Password - Meal System</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        .auth-page {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #f5f3ff;
        }

        .auth-card {
            width: 100%;
            max-width: 430px;
            background: white;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
        }

        .auth-logo {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            color: #7c3aed;
            margin-bottom: 25px;
        }

        .auth-card h1 {
            text-align: center;
            margin-bottom: 10px;
            font-size: 26px;
        }

        .auth-description {
            text-align: center;
            color: #777;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #444;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #7c3aed;
        }

        .auth-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            background: #7c3aed;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .auth-btn:hover {
            background: #6d28d9;
        }

        .success-message {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
        }

        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .dev-reset-box {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .dev-reset-box strong {
            display: block;
            color: #c2410c;
            margin-bottom: 8px;
        }

        .dev-reset-box p {
            margin: 0 0 10px;
            color: #7c2d12;
            font-size: 13px;
        }

        .dev-reset-link {
            display: block;
            word-break: break-all;
            color: #7c3aed;
            font-size: 13px;
            text-decoration: none;
        }

        .dev-reset-link:hover {
            text-decoration: underline;
        }

        .back-login {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .back-login a {
            color: #7c3aed;
            text-decoration: none;
            font-weight: 600;
        }

        .back-login a:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="auth-page">

    <div class="auth-card">

        <div class="auth-logo">
            🍽️ Meal System
        </div>

        <h1>Forgot Password?</h1>

        <p class="auth-description">
            Enter the email address associated with your account.
            If the account exists, we will send you instructions
            to reset your password.
        </p>


        <?php if ($message): ?>

            <div class="success-message">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <?php if ($reset_link): ?>

            <!--
                DEVELOPMENT ONLY

                This link will later be sent by email.
                Remove this box when SMTP is connected.
            -->

            <div class="dev-reset-box">

                <strong>
                    Development Reset Link
                </strong>

                <p>
                    SMTP is not connected yet.
                    Use this link to test the reset process.
                </p>

                <a
                    href="<?= htmlspecialchars($reset_link) ?>"
                    class="dev-reset-link"
                >
                    Open Reset Password
                </a>

            </div>

        <?php endif; ?>


        <form method="POST">

            <?= csrf_field() ?>

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    required
                >

            </div>


            <button
                type="submit"
                class="auth-btn"
            >
                Send Reset Instructions
            </button>

        </form>


        <div class="back-login">

            <a href="login.php">
                ← Back to Login
            </a>

        </div>

    </div>

</div>

</body>

</html>