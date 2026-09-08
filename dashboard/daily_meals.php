<?php

session_start();
require_once "../config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Check meal ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid meal group.");
}

$meal_id = (int) $_GET["id"];


// --------------------------------------------------
// GET MEAL GROUP + CURRENT USER ROLE
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT 
        mg.id,
        mg.name,
        mg.month_name,
        mg.year,
        mg.join_code,
        mm.role
    FROM meal_groups mg
    INNER JOIN meal_members mm
        ON mg.id = mm.meal_group_id
    WHERE mg.id = ?
      AND mm.user_id = ?
");

$stmt->execute([$meal_id, $user_id]);

$meal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meal) {
    die("You are not a member of this meal group.");
}

$current_role = $meal["role"];


// --------------------------------------------------
// CHECK IF USER CAN EDIT
// --------------------------------------------------

$can_edit = ($current_role === "manager" || $current_role === "junior_manager");


// --------------------------------------------------
// CONVERT MONTH NAME TO NUMBER
// --------------------------------------------------

$month_number = date(
    "n",
    strtotime("1 " . $meal["month_name"] . " " . $meal["year"])
);

$year = (int) $meal["year"];


// --------------------------------------------------
// NUMBER OF DAYS IN MONTH
// --------------------------------------------------

$days_in_month = cal_days_in_month(
    CAL_GREGORIAN,
    $month_number,
    $year
);


// --------------------------------------------------
// GET ALL MEMBERS
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        mm.role
    FROM meal_members mm
    INNER JOIN users u
        ON mm.user_id = u.id
    WHERE mm.meal_group_id = ?
    ORDER BY
        CASE mm.role
            WHEN 'manager' THEN 1
            WHEN 'junior_manager' THEN 2
            WHEN 'member' THEN 3
        END,
        u.name
");

$stmt->execute([$meal_id]);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --------------------------------------------------
// GET EXISTING MEALS
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT 
        user_id,
        meal_date,
        meal_amount
    FROM daily_meals
    WHERE meal_group_id = ?
");

$stmt->execute([$meal_id]);

$meal_records = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --------------------------------------------------
// CREATE EASY-TO-USE MEAL ARRAY
// --------------------------------------------------

$meals = [];

foreach ($meal_records as $record) {

    $date = $record["meal_date"];
    $member_id = $record["user_id"];

    $meals[$member_id][$date] = $record["meal_amount"];
}


// --------------------------------------------------
// HANDLE MEAL UPDATE
// --------------------------------------------------

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $can_edit) {

    $member_id = isset($_POST["user_id"])
        ? (int) $_POST["user_id"]
        : 0;

    $day = isset($_POST["day"])
        ? (int) $_POST["day"]
        : 0;

    $meal_amount = isset($_POST["meal_amount"])
        ? $_POST["meal_amount"]
        : "0";

    // Validate member
    $member_check = $pdo->prepare("
        SELECT id
        FROM meal_members
        WHERE meal_group_id = ?
          AND user_id = ?
    ");

    $member_check->execute([
        $meal_id,
        $member_id
    ]);

    if (!$member_check->fetch()) {

        $error = "Invalid member.";

    } elseif ($day < 1 || $day > $days_in_month) {

        $error = "Invalid date.";

    } elseif (!in_array((string)$meal_amount, ["0", "0.5", "1"], true)) {

        $error = "Meal must be 0, 0.5, or 1.";

    } else {

        // Create actual date
        $meal_date = sprintf(
            "%04d-%02d-%02d",
            $year,
            $month_number,
            $day
        );

        // Insert or update meal
        $stmt = $pdo->prepare("
            INSERT INTO daily_meals
                (meal_group_id, user_id, meal_date, meal_amount)
            VALUES
                (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                meal_amount = VALUES(meal_amount)
        ");

        $stmt->execute([
            $meal_id,
            $member_id,
            $meal_date,
            $meal_amount
        ]);

        $message = "Meal updated successfully.";

        // Update local array immediately
        $meals[$member_id][$meal_date] = $meal_amount;
    }
}


// --------------------------------------------------
// FUNCTION TO GET MEAL VALUE
// --------------------------------------------------

function getMealValue($meals, $user_id, $date)
{
    if (isset($meals[$user_id][$date])) {
        return $meals[$user_id][$date];
    }

    return "0";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daily Meals - <?php echo htmlspecialchars($meal["name"]); ?></title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f5fb;
            color: #333;
        }

        /* -----------------------------------------
           SIDEBAR
        ----------------------------------------- */

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;

            background: linear-gradient(
                180deg,
                #8e5bb7,
                #6f4196
            );

            color: white;
            padding: 25px 15px;

            overflow-y: auto;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 30px;
            padding-left: 10px;
        }

        .meal-title {
            font-size: 14px;
            opacity: 0.8;
            padding: 0 10px;
            margin-bottom: 20px;
        }

        .nav-link {
            display: block;
            text-decoration: none;
            color: white;

            padding: 13px 15px;
            margin-bottom: 8px;

            border-radius: 10px;

            transition: 0.2s;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.15);
        }

        .nav-link.active {
            background: white;
            color: #6f4196;
            font-weight: bold;
        }

        .sidebar-bottom {
            margin-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 20px;
        }


        /* -----------------------------------------
           MAIN
        ----------------------------------------- */

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 25px;
        }

        .top-bar h1 {
            margin: 0;
            font-size: 28px;
        }

        .role {
            background: #eee5f7;
            color: #6f4196;

            padding: 8px 13px;
            border-radius: 20px;

            font-size: 13px;
            font-weight: bold;
        }


        /* -----------------------------------------
           INFO
        ----------------------------------------- */

        .info-card {
            background: white;
            border-radius: 15px;

            padding: 20px;

            margin-bottom: 25px;

            box-shadow: 0 3px 12px rgba(0,0,0,0.06);

            display: flex;
            gap: 35px;
            flex-wrap: wrap;
        }

        .info-item strong {
            display: block;
            color: #777;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-item span {
            font-weight: bold;
        }


        /* -----------------------------------------
           ALERTS
        ----------------------------------------- */

        .success {
            background: #e8f8ed;
            color: #207a3c;

            padding: 12px 15px;

            border-radius: 10px;

            margin-bottom: 20px;
        }

        .error {
            background: #fdeaea;
            color: #a32121;

            padding: 12px 15px;

            border-radius: 10px;

            margin-bottom: 20px;
        }


        /* -----------------------------------------
           TABLE
        ----------------------------------------- */

        .table-card {
            background: white;

            border-radius: 15px;

            padding: 20px;

            box-shadow: 0 3px 12px rgba(0,0,0,0.06);

            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 20px;
        }

        .table-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .edit-info {
            font-size: 13px;
            color: #777;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
        }

        table {
            border-collapse: collapse;
            width: max-content;
            min-width: 100%;
        }

        th,
        td {
            border: 1px solid #ddd;

            text-align: center;

            padding: 8px;

            white-space: nowrap;
        }

        th {
            background: #f1e9f8;
            color: #55316f;
            font-size: 13px;
        }

        .member-column {
            position: sticky;
            left: 0;

            background: white;

            min-width: 180px;

            text-align: left;

            z-index: 3;
        }

        th.member-column {
            background: #e8daf2;
        }

        .total-column {
            position: sticky;
            right: 0;

            background: #f8f3fb;

            min-width: 80px;

            font-weight: bold;

            z-index: 2;
        }

        th.total-column {
            background: #e8daf2;
        }

        .day-header {
            min-width: 52px;
        }

        .day-number {
            font-weight: bold;
        }

        .day-name {
            font-size: 10px;
            color: #777;
            display: block;
            margin-top: 3px;
        }

        .meal-cell {
            min-width: 52px;
        }

        .meal-form {
            margin: 0;
        }

        .meal-select {
            width: 45px;

            padding: 5px 2px;

            border: 1px solid #ccc;

            border-radius: 5px;

            background: white;

            text-align: center;

            cursor: pointer;
        }

        .meal-select:focus {
            outline: 2px solid #b990d1;
        }

        .readonly-meal {
            font-weight: bold;
        }

        .member-name {
            font-weight: bold;
        }

        .member-role {
            display: block;

            font-size: 11px;

            color: #888;

            margin-top: 3px;
        }

        .total-meal {
            color: #6f4196;
        }


        /* -----------------------------------------
           BUTTON
        ----------------------------------------- */

        .back-button {
            display: inline-block;

            margin-top: 20px;

            padding: 10px 16px;

            background: #6f4196;

            color: white;

            text-decoration: none;

            border-radius: 8px;
        }

        .back-button:hover {
            background: #5d347e;
        }


        /* -----------------------------------------
           MOBILE
        ----------------------------------------- */

        @media (max-width: 768px) {

            .sidebar {
                position: relative;

                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;

                padding: 20px 10px;
            }

            .top-bar {
                flex-direction: column;

                align-items: flex-start;

                gap: 10px;
            }

            .info-card {
                gap: 20px;
            }

            .table-card {
                padding: 10px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================
     SIDEBAR
========================================= -->

<div class="sidebar">

    <div class="logo">
        🍚 Meal System
    </div>

    <div class="meal-title">
        <?php echo htmlspecialchars($meal["name"]); ?>
    </div>


    <a
        href="meal.php?id=<?php echo $meal_id; ?>"
        class="nav-link"
    >
        🏠 Overview
    </a>


    <a
        href="daily_meals.php?id=<?php echo $meal_id; ?>"
        class="nav-link active"
    >
        🍚 Daily Meals
    </a>


    <a
        href="market.php?id=<?php echo $meal_id; ?>"
        class="nav-link"
    >
        🛒 Market / Bazar
    </a>


    <a
        href="payments.php?id=<?php echo $meal_id; ?>"
        class="nav-link"
    >
        💰 Given Money
    </a>


    <a
        href="calculation.php?id=<?php echo $meal_id; ?>"
        class="nav-link"
    >
        🧮 Calculation
    </a>


    <div class="sidebar-bottom">

        <a
            href="meal.php?id=<?php echo $meal_id; ?>#members"
            class="nav-link"
        >
            👥 Members
        </a>

        <a
            href="index.php"
            class="nav-link"
        >
            📋 My Meal Groups
        </a>

        <a
            href="../logout.php"
            class="nav-link"
        >
            🚪 Logout
        </a>

    </div>

</div>



<!-- =========================================
     MAIN CONTENT
========================================= -->

<div class="main">


    <div class="top-bar">

        <div>
            <h1>Daily Meals</h1>
        </div>

        <div class="role">
            <?php echo ucfirst(str_replace("_", " ", $current_role)); ?>
        </div>

    </div>



    <!-- INFO CARD -->

    <div class="info-card">

        <div class="info-item">

            <strong>MEAL GROUP</strong>

            <span>
                <?php echo htmlspecialchars($meal["name"]); ?>
            </span>

        </div>


        <div class="info-item">

            <strong>PERIOD</strong>

            <span>
                <?php
                echo htmlspecialchars($meal["month_name"])
                    . " "
                    . htmlspecialchars($meal["year"]);
                ?>
            </span>

        </div>


        <div class="info-item">

            <strong>DAYS</strong>

            <span>
                <?php echo $days_in_month; ?> Days
            </span>

        </div>


        <div class="info-item">

            <strong>YOUR ROLE</strong>

            <span>
                <?php
                echo ucfirst(
                    str_replace("_", " ", $current_role)
                );
                ?>
            </span>

        </div>

    </div>



    <!-- MESSAGES -->

    <?php if ($message): ?>

        <div class="success">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>



    <!-- TABLE -->

    <div class="table-card">

        <div class="table-header">

            <h2>
                <?php
                echo htmlspecialchars($meal["month_name"])
                    . " "
                    . htmlspecialchars($meal["year"]);
                ?>
            </h2>


            <div class="edit-info">

                <?php if ($can_edit): ?>

                    ✏️ You can edit meals

                <?php else: ?>

                    👀 View only

                <?php endif; ?>

            </div>

        </div>



        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th class="member-column">
                            Member
                        </th>


                        <?php for ($day = 1; $day <= $days_in_month; $day++): ?>

                            <?php

                            $date_string = sprintf(
                                "%04d-%02d-%02d",
                                $year,
                                $month_number,
                                $day
                            );

                            $day_name = date(
                                "D",
                                strtotime($date_string)
                            );

                            ?>

                            <th class="day-header">

                                <span class="day-number">
                                    <?php echo $day; ?>
                                </span>

                                <span class="day-name">
                                    <?php echo $day_name; ?>
                                </span>

                            </th>

                        <?php endfor; ?>


                        <th class="total-column">
                            Total
                        </th>

                    </tr>

                </thead>



                <tbody>


                    <?php foreach ($members as $member): ?>

                        <?php

                        $member_total = 0;

                        ?>

                        <tr>


                            <!-- MEMBER -->

                            <td class="member-column">

                                <span class="member-name">
                                    <?php
                                    echo htmlspecialchars(
                                        $member["name"]
                                    );
                                    ?>
                                </span>

                                <span class="member-role">

                                    <?php
                                    echo ucfirst(
                                        str_replace(
                                            "_",
                                            " ",
                                            $member["role"]
                                        )
                                    );
                                    ?>

                                </span>

                            </td>



                            <!-- DAYS -->

                            <?php for ($day = 1; $day <= $days_in_month; $day++): ?>

                                <?php

                                $date_string = sprintf(
                                    "%04d-%02d-%02d",
                                    $year,
                                    $month_number,
                                    $day
                                );

                                $meal_value = getMealValue(
                                    $meals,
                                    $member["id"],
                                    $date_string
                                );

                                $member_total += (float)$meal_value;

                                ?>

                                <td class="meal-cell">


                                    <?php if ($can_edit): ?>

                                        <form
                                            method="POST"
                                            class="meal-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?php echo $member["id"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="day"
                                                value="<?php echo $day; ?>"
                                            >


                                            <select
                                                name="meal_amount"
                                                class="meal-select"
                                                onchange="this.form.submit()"
                                            >

                                                <option
                                                    value="0"
                                                    <?php
                                                    echo ((string)$meal_value === "0.00" || (string)$meal_value === "0")
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    0
                                                </option>


                                                <option
                                                    value="0.5"
                                                    <?php
                                                    echo ((string)$meal_value === "0.50" || (string)$meal_value === "0.5")
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    0.5
                                                </option>


                                                <option
                                                    value="1"
                                                    <?php
                                                    echo ((string)$meal_value === "1.00" || (string)$meal_value === "1")
                                                        ? "selected"
                                                        : "";
                                                    ?>
                                                >
                                                    1
                                                </option>

                                            </select>

                                        </form>


                                    <?php else: ?>

                                        <span class="readonly-meal">
                                            <?php echo $meal_value; ?>
                                        </span>

                                    <?php endif; ?>


                                </td>

                            <?php endfor; ?>



                            <!-- TOTAL -->

                            <td class="total-column">

                                <span class="total-meal">
                                    <?php echo number_format($member_total, 1); ?>
                                </span>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                </tbody>

            </table>

        </div>


    </div>



    <a
        href="meal.php?id=<?php echo $meal_id; ?>"
        class="back-button"
    >
        ← Back to Overview
    </a>


</div>


</body>

</html>