<?php

session_start();

require_once "../config/database.php";

// Make sure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $meal_name = trim($_POST["meal_name"] ?? "");
    $month_name = trim($_POST["month_name"] ?? "");
    $year = (int) ($_POST["year"] ?? 0);

    if (empty($meal_name) || empty($month_name) || empty($year)) {

        $message = "Please fill in all fields.";

    } else {

        try {

            // Generate a unique join code
            do {
                $join_code = "MEAL-" . strtoupper(
                    substr(bin2hex(random_bytes(4)), 0, 6)
                );

                $check = $pdo->prepare(
                    "SELECT id FROM meal_groups WHERE join_code = ?"
                );

                $check->execute([$join_code]);

            } while ($check->fetch());

            // Start database transaction
            $pdo->beginTransaction();

            // Create meal group
            $stmt = $pdo->prepare(
                "INSERT INTO meal_groups
                (name, join_code, month_name, year, created_by)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $meal_name,
                $join_code,
                $month_name,
                $year,
                $_SESSION["user_id"]
            ]);

            // Get newly created meal ID
            $meal_group_id = $pdo->lastInsertId();

            // Add creator as Manager
            $stmt = $pdo->prepare(
                "INSERT INTO meal_members
                (meal_group_id, user_id, role)
                VALUES (?, ?, 'manager')"
            );

            $stmt->execute([
                $meal_group_id,
                $_SESSION["user_id"]
            ]);

            // Save everything
            $pdo->commit();

            $message = "Meal created successfully!";
            $success = true;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = "Something went wrong. Please try again.";
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

    <title>Create Meal - Meal System</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>

<body class="auth-page">

<div class="auth-card create-meal-card">

    <!-- Logo -->
    <div class="auth-logo">
        🍽️ Meal System
    </div>

    <?php if ($success): ?>

        <!-- Success Message -->
        <div class="success-box">

            <div class="success-icon">
                ✓
            </div>

            <h2>Meal Created Successfully!</h2>

            <p>
                Your meal group has been created.
            </p>

            <div class="join-code-box">

                <span>Your Join Code</span>

                <strong>
                    <?php echo htmlspecialchars($join_code); ?>
                </strong>

            </div>

            <a
                href="../dashboard/meal.php?id=<?php echo $meal_group_id; ?>"
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
        <h1>Create New Meal</h1>

        <p class="auth-subtitle">
            Create a meal group and become its Manager.
        </p>

        <!-- Error -->
        <?php if ($message): ?>

            <div class="error-box">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <!-- Form -->
        <form method="POST">

            <!-- Meal Name -->
            <div class="form-group">

                <label for="meal_name">
                    Meal Name
                </label>

                <input
                    type="text"
                    id="meal_name"
                    name="meal_name"
                    placeholder="Example: Abdullah's Mess"
                    value="<?php echo htmlspecialchars($_POST["meal_name"] ?? ""); ?>"
                    required
                >

            </div>


            <!-- Month -->
            <div class="form-group">

                <label for="month_name">
                    Month
                </label>

                <input
                    type="text"
                    id="month_name"
                    name="month_name"
                    placeholder="Example: September"
                    value="<?php echo htmlspecialchars($_POST["month_name"] ?? ""); ?>"
                    required
                >

            </div>


            <!-- Year -->
            <div class="form-group">

                <label for="year">
                    Year
                </label>

                <input
                    type="number"
                    id="year"
                    name="year"
                    value="<?php echo htmlspecialchars($_POST["year"] ?? date("Y")); ?>"
                    min="2000"
                    max="2100"
                    required
                >

            </div>


            <!-- Submit -->
            <button
                type="submit"
                class="auth-btn"
            >
                Create Meal
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

/* Create Meal Card */

.create-meal-card {
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


/* Join Code */

.join-code-box {
    background: #f5f3ff;

    border: 1px solid #ddd6fe;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 20px;
}

.join-code-box span {
    display: block;

    font-size: 13px;

    color: #777;

    margin-bottom: 7px;
}

.join-code-box strong {
    display: block;

    font-size: 24px;

    letter-spacing: 2px;

    color: #7c3aed;
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

    box-sizing: border-box;

    padding: 13px 14px;

    border: 1px solid #ddd;

    border-radius: 10px;

    font-size: 15px;

    outline: none;

    background: #fff;

    transition: 0.2s;
}

.form-group input:focus {
    border-color: #7c3aed;

    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
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


/* Back */

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