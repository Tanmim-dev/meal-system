<?php

session_start();

require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $message = "Please fill in all fields.";

    } else {

        // Find user by email
        $stmt = $pdo->prepare(
            "SELECT id, name, email, password
             FROM users
             WHERE email = ?"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {

            // Store user information in session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];

            // Go to dashboard
            header("Location: dashboard/index.php");
            exit;

        } else {

            $message = "Invalid email or password.";
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

    <title>Login - Meal System</title>

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
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
            box-sizing: border-box;
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
            margin: 0 0 10px;
            font-size: 26px;
        }

        .auth-description {
            text-align: center;
            color: #777;
            font-size: 14px;
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

        .forgot-password {
            text-align: right;
            margin-top: -8px;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #7c3aed;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .login-btn {
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

        .login-btn:hover {
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
        }

        .register-link {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
            color: #666;
        }

        .register-link a {
            color: #7c3aed;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
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

        <h1>Login</h1>

        <p class="auth-description">
            Login to manage your meals and groups.
        </p>

        <?php if ($message): ?>

            <div class="error-message">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

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

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <div class="forgot-password">
                <a href="forgot_password.php">
                    Forgot Password?
                </a>
            </div>

            <button type="submit" class="login-btn">
                Login
            </button>

        </form>

        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Register
            </a>

        </div>

    </div>

</div>

</body>

</html>