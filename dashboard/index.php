<?php

session_start();

require_once "../config/database.php";

// Make sure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Get all meal groups where the current user is a member
$stmt = $pdo->prepare("
    SELECT 
        mg.id,
        mg.name,
        mg.join_code,
        mg.month_name,
        mg.year,
        mm.role
    FROM meal_members mm
    INNER JOIN meal_groups mg 
        ON mm.meal_group_id = mg.id
    WHERE mm.user_id = ?
    ORDER BY mg.created_at DESC
");

$stmt->execute([$user_id]);

$meal_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Meal System</title>

</head>

<body>

    <h1>Meal System</h1>

    <h2>
        Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>! 👋
    </h2>

    <hr>

    <h3>Meal Actions</h3>

    <a href="../meal/create.php">
        <button>Create New Meal</button>
    </a>

    <a href="../meal/join.php">
        <button>Join a Meal</button>
    </a>

    <br><br>

    <hr>

    <h3>My Meal Groups</h3>

    <?php if (count($meal_groups) > 0): ?>

        <?php foreach ($meal_groups as $meal): ?>

            <div>

                <h3>
                    <?php echo htmlspecialchars($meal["name"]); ?>
                </h3>

                <p>
                    Period:
                    <?php echo htmlspecialchars($meal["month_name"]); ?>
                    <?php echo htmlspecialchars($meal["year"]); ?>
                </p>

                <p>
                    Role:
                    <strong>
                        <?php echo htmlspecialchars($meal["role"]); ?>
                    </strong>
                </p>

                <p>
                    Join Code:
                    <?php echo htmlspecialchars($meal["join_code"]); ?>
                </p>

                <a href="meal.php?id=<?php echo $meal["id"]; ?>">
                    Open Meal
                </a>

            </div>

            <hr>

        <?php endforeach; ?>

    <?php else: ?>

        <p>You haven't joined any meal groups yet.</p>

    <?php endif; ?>


    <br>

    <a href="../logout.php">
        Logout
    </a>

</body>

</html>