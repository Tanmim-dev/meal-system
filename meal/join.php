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

    $join_code = strtoupper(trim($_POST["join_code"]));

    if (empty($join_code)) {

        $message = "Please enter a meal code.";

    } else {

        // Find the meal group
        $stmt = $pdo->prepare(
            "SELECT id, name FROM meal_groups WHERE join_code = ?"
        );

        $stmt->execute([$join_code]);

        $meal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$meal) {

            $message = "Invalid meal code.";

        } else {

            // Check whether user is already a member
            $stmt = $pdo->prepare(
                "SELECT id FROM meal_members
                 WHERE meal_group_id = ? AND user_id = ?"
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

                $message = "Successfully joined: " . $meal["name"];
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Join Meal - Meal System</title>

</head>

<body>

    <h1>Join a Meal</h1>

    <?php if ($message): ?>

        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>


    <form method="POST">

        <label>Meal Join Code:</label>

        <br>

        <input
            type="text"
            name="join_code"
            placeholder="Example: MEAL-3769FD"
            required
        >

        <br><br>

        <button type="submit">
            Join Meal
        </button>

    </form>

    <br>

    <a href="../dashboard/index.php">
        Back to Dashboard
    </a>

</body>

</html>