<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

require_login();

$user_id = $_SESSION["user_id"];

// --------------------------------------------------
// CHECK MEAL ID
// --------------------------------------------------

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

$stmt->execute([
    $meal_id,
    $user_id
]);

$meal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meal) {
    die("You are not a member of this meal group.");
}

$current_role = $meal["role"];

// --------------------------------------------------
// CHECK IF USER CAN EDIT
// --------------------------------------------------

$can_edit = (
    $current_role === "manager" ||
    $current_role === "junior_manager"
);

// --------------------------------------------------
// CONVERT MONTH NAME TO NUMBER
// --------------------------------------------------

$month_number = date(
    "n",
    strtotime(
        "1 " .
        $meal["month_name"] .
        " " .
        $meal["year"]
    )
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

$stmt->execute([
    $meal_id
]);

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

$stmt->execute([
    $meal_id
]);

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // --------------------------------------------------
    // VERIFY CSRF TOKEN
    // --------------------------------------------------

    verify_csrf();

    // --------------------------------------------------
    // CHECK EDIT PERMISSION
    // --------------------------------------------------

    if (!$can_edit) {

        http_response_code(403);

        if (
            isset($_SERVER["HTTP_X_REQUESTED_WITH"]) &&
            strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest"
        ) {

            header("Content-Type: application/json");

            echo json_encode([
                "success" => false,
                "message" => "You do not have permission to edit meals."
            ]);

            exit;
        }

        die("You do not have permission to edit meals.");
    }

    $member_id = isset($_POST["user_id"])
        ? (int) $_POST["user_id"]
        : 0;

    $day = isset($_POST["day"])
        ? (int) $_POST["day"]
        : 0;

    $meal_amount = isset($_POST["meal_amount"])
        ? trim($_POST["meal_amount"])
        : "";

    // --------------------------------------------------
    // VALIDATE MEMBER
    // --------------------------------------------------

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
    }

    // --------------------------------------------------
    // VALIDATE DAY
    // --------------------------------------------------

    elseif (
        $day < 1 ||
        $day > $days_in_month
    ) {

        $error = "Invalid date.";
    }

    // --------------------------------------------------
    // VALIDATE MEAL VALUE
    // --------------------------------------------------

    elseif (
        !in_array(
            $meal_amount,
            ["0", "0.5", "1", "1.5", "2", "2.5", "3"],
            true
        )
    ) {

        $error = "Invalid meal value. Allowed values: 0, 0.5, 1, 1.5, 2, 2.5, 3.";
    }

    else {

        // --------------------------------------------------
        // CONVERT MEAL VALUE
        // --------------------------------------------------

        $meal_amount = (float) $meal_amount;

        // --------------------------------------------------
        // CREATE ACTUAL DATE
        // --------------------------------------------------

        $meal_date = sprintf(
            "%04d-%02d-%02d",
            $year,
            $month_number,
            $day
        );

        // --------------------------------------------------
        // INSERT OR UPDATE MEAL
        // --------------------------------------------------

        $stmt = $pdo->prepare("
            INSERT INTO daily_meals
            (
                meal_group_id,
                user_id,
                meal_date,
                meal_amount
            )
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

        // --------------------------------------------------
        // UPDATE LOCAL ARRAY
        // --------------------------------------------------

        $meals[$member_id][$meal_date] = $meal_amount;

        // --------------------------------------------------
        // AJAX RESPONSE
        // --------------------------------------------------

        if (
            isset($_SERVER["HTTP_X_REQUESTED_WITH"]) &&
            strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest"
        ) {

            header("Content-Type: application/json");

            echo json_encode([
                "success" => true,
                "message" => "Saved",
                "user_id" => $member_id,
                "day" => $day,
                "meal_amount" => (float) $meal_amount
            ]);

            exit;
        }
    }

    // --------------------------------------------------
    // AJAX ERROR RESPONSE
    // --------------------------------------------------

    if (
        isset($_SERVER["HTTP_X_REQUESTED_WITH"]) &&
        strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest"
    ) {

        header("Content-Type: application/json");

        echo json_encode([
            "success" => false,
            "message" => $error
        ]);

        exit;
    }
}

// --------------------------------------------------
// FUNCTION TO GET MEAL VALUE
// --------------------------------------------------

function getMealValue(
    $meals,
    $user_id,
    $date
) {

    if (
        isset(
            $meals[$user_id][$date]
        )
    ) {

        return $meals[$user_id][$date];
    }

    return "0";
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

    <title>
        Daily Meals -
        <?php echo htmlspecialchars($meal["name"]); ?>
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* ------------------------------------------
           DAILY MEALS SPECIFIC DESIGN
        ------------------------------------------ */

        .daily-meals-card {
            overflow: hidden;
        }

        .daily-meals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .daily-meals-header h2 {
            margin: 0 0 5px;
        }

        .page-description {
            margin: 0;
            color: #777;
        }

        .edit-status {
            background: #f3e8ff;
            color: #7c3aed;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .daily-meals-table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
        }

        .daily-meals-table {
            min-width: 1000px;
            border-collapse: collapse;
        }

        .daily-meals-table th,
        .daily-meals-table td {
            text-align: center;
            vertical-align: middle;
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
        }

        .daily-meals-table th {
            background: #faf5ff;
            color: #555;
            font-size: 13px;
        }

        .daily-member-column {
            min-width: 170px;
            text-align: left !important;
            position: sticky;
            left: 0;
            z-index: 2;
            background: white;
        }

        .daily-meals-table thead .daily-member-column {
            background: #faf5ff;
            z-index: 3;
        }

        .daily-day-column {
            min-width: 70px;
        }

        .daily-day-number {
            display: block;
            font-size: 14px;
            font-weight: bold;
        }

        .daily-day-name {
            display: block;
            font-size: 11px;
            color: #999;
            margin-top: 3px;
        }

        .daily-total-column {
            min-width: 70px;
            font-weight: bold;
            background: #fafafa;
        }

        .daily-member-name {
            font-weight: 600;
            color: #333;
        }

        .daily-member-role {
            font-size: 12px;
            color: #999;
            margin-top: 3px;
            text-transform: capitalize;
        }

        .daily-meal-cell {
            min-width: 70px;
        }

        .daily-meal-form {
            margin: 0;
            padding: 0;
        }

        /* ------------------------------------------
           NEW MEAL INPUT
        ------------------------------------------ */

        .daily-meal-input {
            width: 58px;
            padding: 7px 5px;
            border: 1px solid #ddd;
            border-radius: 7px;
            background: white;
            color: #333;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
            box-sizing: border-box;
        }

        .daily-meal-input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.12);
        }

        .daily-meal-input.saving {
            opacity: 0.6;
        }

        .daily-meal-input.saved {
            border-color: #22c55e;
        }

        .daily-readonly-meal {
            display: inline-flex;
            min-width: 40px;
            height: 32px;
            padding: 0 5px;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            background: #f5f5f5;
            font-weight: 600;
            color: #555;
            box-sizing: border-box;
        }

        .meal-save-status {
            position: fixed;
            right: 25px;
            bottom: 25px;
            padding: 11px 17px;
            border-radius: 10px;
            background: #22c55e;
            color: white;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: all 0.2s ease;
            z-index: 9999;
        }

        .meal-save-status.show {
            opacity: 1;
            transform: translateY(0);
        }

        .meal-save-status.error {
            background: #ef4444;
        }

        @media (max-width: 768px) {

            .daily-meals-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .edit-status {
                width: 100%;
                box-sizing: border-box;
            }

            .daily-member-column {
                min-width: 140px;
            }

            .daily-meal-input {
                width: 58px;
            }

        }

    </style>

</head>

<body>

<!-- ==================================================
     SIDEBAR
================================================== -->

<div class="sidebar">

    <div class="logo">
        🍚 Meal System
    </div>

    <div class="nav-title">
        <?php
        echo htmlspecialchars(
            $meal["name"]
        );
        ?>
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
        🍽️ Daily Meals
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

    <div class="nav-title">
        Group
    </div>

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
        📋 My Groups
    </a>

    <a
        href="../logout.php"
        class="nav-link"
    >
        🚪 Logout
    </a>

</div>


<!-- ==================================================
     MAIN CONTENT
================================================== -->

<div class="main">

    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h1>
                🍽️ Daily Meals
            </h1>

        </div>

        <div class="user">

            <?php
            echo htmlspecialchars(
                $_SESSION["user_name"]
            );
            ?>

            <span>
                ·
                <?php
                echo ucfirst(
                    str_replace(
                        "_",
                        " ",
                        $current_role
                    )
                );
                ?>
            </span>

        </div>

    </div>


    <!-- GROUP INFORMATION -->

    <div class="card">

        <h2>
            <?php
            echo htmlspecialchars(
                $meal["name"]
            );
            ?>
        </h2>

        <div class="group-info">

            <div>

                <strong>
                    Meal Group
                </strong>

                <br>

                <?php
                echo htmlspecialchars(
                    $meal["name"]
                );
                ?>

            </div>

            <div>

                <strong>
                    Period
                </strong>

                <br>

                <?php
                echo htmlspecialchars(
                    $meal["month_name"]
                );

                echo " ";

                echo htmlspecialchars(
                    $meal["year"]
                );
                ?>

            </div>

            <div>

                <strong>
                    Days
                </strong>

                <br>

                <?php
                echo $days_in_month;
                ?>
                Days

            </div>

            <div>

                <strong>
                    Your Role
                </strong>

                <br>

                <?php
                echo ucfirst(
                    str_replace(
                        "_",
                        " ",
                        $current_role
                    )
                );
                ?>

            </div>

        </div>

    </div>


    <!-- SUCCESS / ERROR -->

    <?php if ($message): ?>

        <div class="success-message">

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error-message">

            <?php
            echo htmlspecialchars(
                $error
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- DAILY MEALS -->

    <div class="card daily-meals-card">

        <div class="daily-meals-header">

            <div>

                <h2>

                    <?php
                    echo htmlspecialchars(
                        $meal["month_name"]
                    );

                    echo " ";

                    echo htmlspecialchars(
                        $meal["year"]
                    );
                    ?>

                </h2>

                <p class="page-description">
                    Record daily meals for all group members.
                </p>

            </div>

            <div class="edit-status">

                <?php if ($can_edit): ?>

                    ✏️ You can edit meals

                <?php else: ?>

                    👀 View only

                <?php endif; ?>

            </div>

        </div>


        <!-- TABLE -->

        <div class="daily-meals-table-container">

            <table class="table daily-meals-table">

                <thead>

                    <tr>

                        <th class="daily-member-column">
                            Member
                        </th>

                        <?php

                        for (
                            $day = 1;
                            $day <= $days_in_month;
                            $day++
                        ):

                            $date_string = sprintf(
                                "%04d-%02d-%02d",
                                $year,
                                $month_number,
                                $day
                            );

                            $day_name = date(
                                "D",
                                strtotime(
                                    $date_string
                                )
                            );

                        ?>

                            <th class="daily-day-column">

                                <span class="daily-day-number">
                                    <?php echo $day; ?>
                                </span>

                                <span class="daily-day-name">
                                    <?php echo $day_name; ?>
                                </span>

                            </th>

                        <?php endfor; ?>

                        <th class="daily-total-column">
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

                            <td class="daily-member-column">

                                <div class="daily-member-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $member["name"]
                                    );
                                    ?>

                                </div>

                                <div class="daily-member-role">

                                    <?php
                                    echo ucfirst(
                                        str_replace(
                                            "_",
                                            " ",
                                            $member["role"]
                                        )
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- DAILY MEALS -->

                            <?php

                            for (
                                $day = 1;
                                $day <= $days_in_month;
                                $day++
                            ):

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

                                $member_total +=
                                    (float) $meal_value;

                            ?>

                                <td class="daily-meal-cell">

                                    <?php if ($can_edit): ?>

                                        <form
                                            method="POST"
                                            class="daily-meal-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars(
                                                    csrf_token(),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>"
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
                                                class="daily-meal-input"
                                                data-user-id="<?php echo $member["id"]; ?>"
                                                data-day="<?php echo $day; ?>"
                                            >
                                                <?php
                                                $allowed_meal_values = [
                                                    "0",
                                                    "0.5",
                                                    "1",
                                                    "1.5",
                                                    "2",
                                                    "2.5",
                                                    "3"
                                                ];

                                                foreach ($allowed_meal_values as $allowed_value):
                                                ?>
                                                    <option
                                                        value="<?php echo $allowed_value; ?>"
                                                        <?php
                                                        if ((float) $meal_value === (float) $allowed_value) {
                                                            echo "selected";
                                                        }
                                                        ?>
                                                    >
                                                        <?php echo $allowed_value; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                        </form>

                                    <?php else: ?>

                                        <span class="daily-readonly-meal">

                                            <?php
                                            echo rtrim(
                                                rtrim(
                                                    number_format(
                                                        (float) $meal_value,
                                                        2,
                                                        ".",
                                                        ""
                                                    ),
                                                    "0"
                                                ),
                                                "."
                                            );
                                            ?>

                                        </span>

                                    <?php endif; ?>

                                </td>

                            <?php endfor; ?>


                            <!-- TOTAL -->

                            <td class="daily-total-column">

                                <strong>

                                    <?php
                                    echo number_format(
                                        $member_total,
                                        2
                                    );
                                    ?>

                                </strong>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- BACK BUTTON -->

    <a
        href="meal.php?id=<?php echo $meal_id; ?>"
        class="action-btn"
        style="
            display:inline-block;
            margin-top:20px;
            text-decoration:none;
        "
    >
        ← Back to Overview
    </a>

</div>


<!-- SAVE STATUS -->

<div
    id="mealSaveStatus"
    class="meal-save-status"
>
    ✓ Meal saved
</div>


<!-- ==================================================
     AJAX MEAL SAVING
================================================== -->

<script>

document.querySelectorAll(".daily-meal-input").forEach(function(input) {

    input.addEventListener("change", function() {

        const mealInput = this;

        const form = mealInput.closest(".daily-meal-form");

        const userId = form.querySelector(
            'input[name="user_id"]'
        ).value;

        const day = form.querySelector(
            'input[name="day"]'
        ).value;

        const csrfToken = form.querySelector(
            'input[name="csrf_token"]'
        ).value;

        const mealAmount = mealInput.value;

        const status = document.getElementById(
            "mealSaveStatus"
        );

        // Show saving state

        mealInput.classList.add("saving");

        mealInput.disabled = true;

        // Create form data

        const formData = new FormData();

        formData.append(
            "csrf_token",
            csrfToken
        );

        formData.append(
            "user_id",
            userId
        );

        formData.append(
            "day",
            day
        );

        formData.append(
            "meal_amount",
            mealAmount
        );

        // Save without reloading

        fetch(
            window.location.href,
            {
                method: "POST",

                headers: {
                    "X-Requested-With":
                        "XMLHttpRequest"
                },

                body: formData
            }
        )

        .then(function(response) {

            return response.json();

        })

        .then(function(data) {

            if (data.success) {

                mealInput.classList.remove(
                    "saving"
                );

                mealInput.classList.add(
                    "saved"
                );

                status.textContent =
                    "✓ Meal saved";

                status.classList.remove(
                    "error"
                );

                status.classList.add(
                    "show"
                );

                setTimeout(function() {

                    mealInput.classList.remove(
                        "saved"
                    );

                    status.classList.remove(
                        "show"
                    );

                }, 1200);

            }

            else {

                throw new Error(
                    data.message ||
                    "Could not save meal."
                );

            }

        })

        .catch(function(error) {

            console.error(error);

            status.textContent =
                "✕ " +
                (
                    error.message ||
                    "Could not save meal"
                );

            status.classList.add(
                "error"
            );

            status.classList.add(
                "show"
            );

            setTimeout(function() {

                status.classList.remove(
                    "show"
                );

            }, 2500);

        })

        .finally(function() {

            mealInput.disabled = false;

            mealInput.classList.remove(
                "saving"
            );

        });

    });

});

</script>

</body>
</html>