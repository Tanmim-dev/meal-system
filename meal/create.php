<?php

session_start();

require_once "../config/database.php";

// Make sure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $meal_name = trim($_POST["meal_name"]);
    $month_name = trim($_POST["month_name"]);
    $year = (int) $_POST["year"];

    if (empty($meal_name) || empty($month_name) || empty($year)) {

        $message = "Please fill in all fields.";

    } else {

        try {

            // Generate a unique join code
            do {
                $join_code = "MEAL-" . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

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

            // Get the newly created meal ID
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

            $message = "Meal created successfully! Your Join Code is: " . $join_code;

        } catch (PDOException $e) {

            // Cancel changes if something went wrong
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = "Something went wrong: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Meal - Meal System</title>

</head>

<body>

    <h1>Create New Meal</h1>

    <?php if ($message): ?>

        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>


    <form method="POST">

        <label>Meal Name:</label>

        <br>

        <input
            type="text"
            name="meal_name"
            placeholder="Example: Abdullah's Mess"
            required
        >

        <br><br>


        <label>Month:</label>

        <br>

        <input
            type="text"
            name="month_name"
            placeholder="Example: September"
            required
        >

        <br><br>


        <label>Year:</label>

        <br>

        <input
            type="number"
            name="year"
            value="<?php echo date('Y'); ?>"
            required
        >

        <br><br>


        <button type="submit">
            Create Meal
        </button>

    </form>

    <br>

    <a href="../dashboard/index.php">
        Back to Dashboard
    </a>

</body>

</html>