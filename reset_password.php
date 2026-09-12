<?php

date_default_timezone_set("Asia/Dhaka");

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

$error = "";
$success = false;

$token = trim($_GET["token"] ?? "");

if ($token === "") {

    $error = "This password reset link is invalid.";

} else {

    // Hash the token received from the URL
    $token_hash = hash("sha256", $token);

    $stmt = $pdo->prepare("
        SELECT
            pr.id AS reset_id,
            pr.user_id,
            u.name,
            u.email
        FROM password_resets pr
        INNER JOIN users u
            ON u.id = pr.user_id
        WHERE pr.token_hash = ?
          AND pr.used_at IS NULL
          AND pr.expires_at > NOW()
        LIMIT 1
    ");

    $stmt->execute([$token_hash]);

    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {

        $error =
            "This password reset link is invalid, expired, or has already been used.";

    } else {

        if ($_SERVER["REQUEST_METHOD"] === "POST") {

            // CSRF protection
            verify_csrf();

            $password = $_POST["password"] ?? "";
            $confirm_password = $_POST["confirm_password"] ?? "";

            if (strlen($password) < 8) {

                $error =
                    "Password must be at least 8 characters long.";

            } elseif ($password !== $confirm_password) {

                $error = "Passwords do not match.";

            } else {

                try {

                    $pdo->beginTransaction();

                    // Hash the new password
                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Update user's password
                    $update = $pdo->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ");

                    $update->execute([
                        $password_hash,
                        $reset["user_id"]
                    ]);

                    /*
                     * Invalidate ALL unused reset tokens
                     * for this user.
                     */
                    $invalidate = $pdo->prepare("
                        UPDATE password_resets
                        SET used_at = NOW()
                        WHERE user_id = ?
                          AND used_at IS NULL
                    ");

                    $invalidate->execute([
                        $reset["user_id"]
                    ]);

                    $pdo->commit();

                    /*
                     * Do not automatically log the user in.
                     * Regenerate the session after password reset.
                     */
                    $_SESSION = [];

                    session_regenerate_id(true);

                    $success = true;

                } catch (Exception $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        "Something went wrong. Please try again.";
                }
            }
        }
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

    <title>Reset Password - Meal System</title>

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

        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success-box {
            text-align: center;
        }

        .success-icon {
            font-size: 50px;
            margin-bottom: 10px;
        }

        .success-box h2 {
            margin-bottom: 10px;
        }

        .success-box p {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }

        .login-btn {
            display: block;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            margin-top: 20px;
            padding: 13px;
            border-radius: 10px;
            background: #7c3aed;
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .login-btn:hover {
            background: #6d28d9;
        }

    </style>

</head>

<body>

<div class="auth-page">

    <div class="auth-card">

        <div class="auth-logo">
            🍽️ Meal System
        </div>

        <?php if ($success): ?>

            <div class="success-box">

                <div class="success-icon">
                    ✅
                </div>

                <h2>Password Reset Successfully</h2>

                <p>
                    Your password has been changed successfully.
                    You can now log in using your new password.
                </p>

                <a href="login.php" class="login-btn">
                    Go to Login
                </a>

            </div>

        <?php elseif ($error): ?>

            <h1>Reset Password</h1>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

            <a href="forgot_password.php" class="login-btn">
                Request a New Reset Link
            </a>

        <?php else: ?>

            <h1>Reset Password</h1>

            <p class="auth-description">
                Create a new password for your Meal System account.
            </p>

            <form method="POST">

                <?= csrf_field() ?>

                <div class="form-group">

                    <label for="password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        minlength="8"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                        minlength="8"
                        required
                    >

                </div>

                <button type="submit" class="auth-btn">
                    Reset Password
                </button>

            </form>

        <?php endif; ?>

    </div>

</div>

</body>

</html>