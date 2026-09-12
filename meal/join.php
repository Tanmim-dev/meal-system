<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

require_login();

$message = "";
$success = false;
$joined_meal_id = null;
$meal = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token
    verify_csrf();

    $join_code = strtoupper(trim($_POST["join_code"] ?? ""));

    if (empty($join_code)) {

        $message = "Please enter a meal code.";

    } else {

        // Find the meal group
        $stmt = $pdo->prepare(
            "SELECT id, name
             FROM meal_groups
             WHERE join_code = ?"
        );

        $stmt->execute([$join_code]);

        $meal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$meal) {

            $message = "Invalid meal code.";

        } else {

            // Check whether user is already a member
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM meal_members
                 WHERE meal_group_id = ?
                 AND user_id = ?"
            );

            $stmt->execute([
                $meal["id"],
                $_SESSION["user_id"]
            ]);

            if ($stmt->fetch()) {

                $message = "You are already a member of this meal.";

            } else {

                // Add user as normal member
                $stmt = $pdo->prepare(
                    "INSERT INTO meal_members
                    (meal_group_id, user_id, role)
                    VALUES (?, ?, 'member')"
                );

                $stmt->execute([
                    $meal["id"],
                    $_SESSION["user_id"]
                ]);

                $message = "Successfully joined!";
                $success = true;
                $joined_meal_id = $meal["id"];
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

    <title>Join Meal - Meal System</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>

<body class="auth-page">

<div class="auth-card join-meal-card">

    <!-- Logo -->
    <div class="auth-logo">
        🍽️ Meal System
    </div>


    <?php if ($success): ?>

        <!-- Success -->
        <div class="success-box">

            <div class="success-icon">
                ✓
            </div>

            <h2>Successfully Joined!</h2>

            <p>
                You have successfully joined
                <strong>
                    <?php echo htmlspecialchars($meal["name"]); ?>
                </strong>
            </p>

            <a
                href="../dashboard/meal.php?id=<?php echo $joined_meal_id; ?>"
                class="auth-btn"
            >
                Open Meal
            </a>

            <a
                href="../dashboard/index.php"
                class="back-link"
            >
                ← Back to Dashboard
            </a>

        </div>

    <?php else: ?>

        <!-- Heading -->
        <h1>Join a Meal</h1>

        <p class="auth-subtitle">
            Enter the join code provided by your Manager.
        </p>


        <!-- Error -->
        <?php if ($message): ?>

            <div class="error-box">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <!-- Form -->
        <form method="POST">

            <?= csrf_field() ?>

            <div class="form-group">

                <label for="join_code">
                    Meal Join Code
                </label>

                <input
                    type="text"
                    id="join_code"
                    name="join_code"
                    placeholder="Example: MEAL-3769FD"
                    value="<?php echo htmlspecialchars($_POST["join_code"] ?? ""); ?>"
                    maxlength="20"
                    required
                >

                <small class="input-help">
                    Ask the Manager for the meal's join code.
                </small>

            </div>


            <button
                type="submit"
                class="auth-btn"
            >
                Join Meal
            </button>

        </form>


        <!-- Back -->
        <a
            href="../dashboard/index.php"
            class="back-link"
        >
            ← Back to Dashboard
        </a>

    <?php endif; ?>

</div>


<style>

/* Join Meal Card */

.join-meal-card {
    max-width: 480px;
}


/* Success */

.success-box {
    text-align: center;
}

.success-icon {
    width: 60px;
    height: 60px;

    margin: 0 auto 15px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #dcfce7;
    color: #16a34a;

    font-size: 32px;
    font-weight: bold;
}

.success-box h2 {
    margin: 0 0 8px;
}

.success-box p {
    color: #666;

    margin-bottom: 25px;
}


/* Error */

.error-box {
    background: #fef2f2;

    border: 1px solid #fecaca;

    color: #dc2626;

    padding: 12px 14px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;
}


/* Form */

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;

    margin-bottom: 7px;

    font-weight: 600;

    color: #444;
}

.form-group input {
    width: 100%;

    box-sizing: border-box;

    padding: 13px 14px;

    border: 1px solid #ddd;

    border-radius: 10px;

    font-size: 16px;

    outline: none;

    text-transform: uppercase;

    background: #fff;

    transition: 0.2s;
}

.form-group input:focus {
    border-color: #7c3aed;

    box-shadow:
        0 0 0 3px rgba(124, 58, 237, 0.1);
}


/* Help Text */

.input-help {
    display: block;

    margin-top: 7px;

    color: #888;

    font-size: 13px;
}


/* Button */

.auth-btn {
    display: block;

    width: 100%;

    box-sizing: border-box;

    padding: 13px;

    border: none;

    border-radius: 10px;

    background: #7c3aed;

    color: white;

    text-align: center;

    text-decoration: none;

    font-size: 15px;

    font-weight: 600;

    cursor: pointer;

    transition: 0.2s;
}

.auth-btn:hover {
    background: #6d28d9;
}


/* Back Link */

.back-link {
    display: block;

    margin-top: 20px;

    text-align: center;

    color: #7c3aed;

    text-decoration: none;

    font-size: 14px;
}

.back-link:hover {
    text-decoration: underline;
}

</style>

</body>
</html>