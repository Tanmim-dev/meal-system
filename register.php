<?php

require_once "config/database.php";

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (empty($name) || empty($email) || empty($password)) {

        $message = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $message = "Password must be at least 8 characters long.";

    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";

    } else {

        // Check if email already exists
        $check = $pdo->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->execute([$email]);

        if ($check->fetch()) {

            $message = "Email already registered.";

        } else {

            // Securely hash the password
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert user
            $sql = "INSERT INTO users (name, email, password)
                    VALUES (?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $name,
                $email,
                $hashedPassword
            ]);

            $success = true;
            $message = "Registration successful! 🎉";
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

    <title>Register - Meal System</title>

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

        .register-btn {
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

        .register-btn:hover {
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

        .success-message {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .login-link {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
            color: #7c3aed;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
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

        <h1>Create Account</h1>

        <p class="auth-description">
            Create your account to start managing your meals.
        </p>

        <?php if ($message): ?>

            <div class="<?= $success ? 'success-message' : 'error-message' ?>">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <?php if (!$success): ?>

            <form method="POST">

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter your name"
                        value="<?= htmlspecialchars($_POST["name"] ?? "") ?>"
                        required
                    >

                </div>

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
                        placeholder="At least 8 characters"
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
                        placeholder="Enter password again"
                        minlength="8"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="register-btn"
                >
                    Create Account
                </button>

            </form>

        <?php endif; ?>

        <div class="login-link">

            Already have an account?

            <a href="login.php">
                Login
            </a>

        </div>

    </div>

</div>

</body>

</html>