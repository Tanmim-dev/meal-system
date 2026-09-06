<?php

session_start();

require_once "../config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

// Check meal ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid meal group.");
}

$meal_id = (int) $_GET["id"];
$user_id = $_SESSION["user_id"];

// Get meal information AND current user's role
$stmt = $pdo->prepare("
    SELECT
        mg.id,
        mg.name,
        mg.join_code,
        mg.month_name,
        mg.year,
        mm.role
    FROM meal_groups mg
    INNER JOIN meal_members mm
        ON mg.id = mm.meal_group_id
    WHERE mg.id = ? AND mm.user_id = ?
");

$stmt->execute([$meal_id, $user_id]);

$meal = $stmt->fetch(PDO::FETCH_ASSOC);

// User is not a member
if (!$meal) {
    die("You are not a member of this meal.");
}


// Get all members
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        mm.role
    FROM meal_members mm
    INNER JOIN users u
        ON mm.user_id = u.id
    WHERE mm.meal_group_id = ?
    ORDER BY
        CASE
            WHEN mm.role = 'manager' THEN 1
            WHEN mm.role = 'junior_manager' THEN 2
            ELSE 3
        END,
        u.name
");

$stmt->execute([$meal_id]);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo htmlspecialchars($meal["name"]); ?> - Meal System
    </title>

</head>

<body>

    <h1>
        <?php echo htmlspecialchars($meal["name"]); ?>
    </h1>


    <p>
        <strong>Month:</strong>

        <?php echo htmlspecialchars($meal["month_name"]); ?>

        <?php echo htmlspecialchars($meal["year"]); ?>
    </p>


    <p>
        <strong>Your Role:</strong>

        <?php echo htmlspecialchars($meal["role"]); ?>
    </p>


    <p>
        <strong>Join Code:</strong>

        <?php echo htmlspecialchars($meal["join_code"]); ?>
    </p>


    <hr>


    <h2>Meal System</h2>

    <p>
        Welcome to your meal group.
    </p>


    <hr>


    <h3>📢 Announcements</h3>

    <p>
        No announcements yet.
    </p>


    <hr>


    <h3>🍚 Daily Meals</h3>

    <p>
        Meal table will be added here.
    </p>


    <hr>


    <h3>🛒 Market / Bazar</h3>

    <p>
        Market information will be added here.
    </p>


    <hr>


    <h3>💰 Given Money</h3>

    <p>
        Payment information will be added here.
    </p>


    <hr>


    <h3>🧮 Calculation</h3>

    <p>
        Meal calculation will be added here.
    </p>


    <hr>


    <h3>👥 Members</h3>


    <?php if (count($members) > 0): ?>

        <table border="1" cellpadding="8">

            <tr>

                <th>Name</th>

                <th>Email</th>

                <th>Role</th>

                <th>Action</th>

            </tr>


            <?php foreach ($members as $member): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($member["name"]); ?>
                    </td>


                    <td>
                        <?php echo htmlspecialchars($member["email"]); ?>
                    </td>


                    <td>
                        <?php echo htmlspecialchars($member["role"]); ?>
                    </td>


                    <td>

                        <?php
                        // Manager controls
                        if ($meal["role"] === "manager"):
                        ?>

                            <?php if ($member["role"] === "member"): ?>

                                <a
                                    href="../meal/promote_member.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                    onclick="return confirm('Promote this member to Junior Manager?');"
                                >
                                    Promote to Junior Manager
                                </a>

                                <br>

                                <a
                                    href="../meal/remove_member.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                    onclick="return confirm('Are you sure you want to remove this member?');"
                                >
                                    Remove
                                </a>


                            <?php elseif ($member["role"] === "junior_manager"): ?>

                                <a
                                    href="../meal/remove_junior_manager.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                    onclick="return confirm('Remove Junior Manager role?');"
                                >
                                    Remove Junior Manager
                                </a>


                            <?php else: ?>

                                -

                            <?php endif; ?>


                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>


    <?php else: ?>

        <p>No members found.</p>

    <?php endif; ?>


    <br>


    <a href="index.php">
        ← Back to Dashboard
    </a>


    <br>
    <br>


    <a href="../logout.php">
        Logout
    </a>


</body>

</html>