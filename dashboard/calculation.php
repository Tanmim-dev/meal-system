<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

require_login();

$user_id = $_SESSION["user_id"];

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid meal group.");
}

$group_id = (int) $_GET["id"];

$message = "";
$error = "";


/* =========================================================
   GET GROUP + CURRENT USER ROLE
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        mg.id,
        mg.name,
        mg.month_name,
        mg.year,
        mm.role
    FROM meal_groups mg
    INNER JOIN meal_members mm
        ON mg.id = mm.meal_group_id
    WHERE mg.id = ?
      AND mm.user_id = ?
    LIMIT 1
");

$stmt->execute([$group_id, $user_id]);

$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    die("You are not a member of this meal group.");
}

$role = $group["role"];

$can_edit = in_array($role, ["manager", "junior_manager"]);
$can_delete = ($role === "manager");


/* =========================================================
   HANDLE POST ACTIONS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* CSRF SECURITY */
    verify_csrf();

    $action = $_POST["action"] ?? "";


    /* =====================================================
       ADD SHARED EXPENSE
       - Others = SHARED
       - Khala Bill = SHARED
    ===================================================== */

    if ($action === "add_expense" && $can_edit) {

        $expense_type = $_POST["expense_type"] ?? "";
        $description = trim($_POST["description"] ?? "");
        $amount = $_POST["amount"] ?? "";
        $expense_date = $_POST["expense_date"] ?? "";

        if (!in_array($expense_type, ["others", "khala_bill"])) {

            $error = "Invalid expense type.";

        } elseif (!is_numeric($amount) || $amount <= 0) {

            $error = "Please enter a valid amount.";

        } elseif (empty($expense_date)) {

            $error = "Please select a date.";

        } else {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO expenses
                    (
                        meal_group_id,
                        expense_type,
                        description,
                        amount,
                        expense_date,
                        added_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $group_id,
                    $expense_type,
                    $description !== "" ? $description : null,
                    $amount,
                    $expense_date,
                    $user_id
                ]);

                $message = "Shared expense added successfully.";

            } catch (PDOException $e) {

                $error = "Failed to add expense. Please try again.";
            }
        }
    }


    /* =====================================================
       EDIT SHARED EXPENSE
    ===================================================== */

    elseif ($action === "edit_expense" && $can_edit) {

        $expense_id = $_POST["expense_id"] ?? "";
        $expense_type = $_POST["expense_type"] ?? "";
        $description = trim($_POST["description"] ?? "");
        $amount = $_POST["amount"] ?? "";
        $expense_date = $_POST["expense_date"] ?? "";

        if (!is_numeric($expense_id)) {

            $error = "Invalid expense.";

        } elseif (!in_array($expense_type, ["others", "khala_bill"])) {

            $error = "Invalid expense type.";

        } elseif (!is_numeric($amount) || $amount <= 0) {

            $error = "Please enter a valid amount.";

        } elseif (empty($expense_date)) {

            $error = "Please select a date.";

        } else {

            try {

                $stmt = $pdo->prepare("
                    UPDATE expenses
                    SET
                        expense_type = ?,
                        description = ?,
                        amount = ?,
                        expense_date = ?
                    WHERE id = ?
                      AND meal_group_id = ?
                ");

                $stmt->execute([
                    $expense_type,
                    $description !== "" ? $description : null,
                    $amount,
                    $expense_date,
                    $expense_id,
                    $group_id
                ]);

                $message = "Shared expense updated successfully.";

            } catch (PDOException $e) {

                $error = "Failed to update expense. Please try again.";
            }
        }
    }


    /* =====================================================
       DELETE SHARED EXPENSE
       MANAGER ONLY
    ===================================================== */

    elseif ($action === "delete_expense" && $can_delete) {

        $expense_id = $_POST["expense_id"] ?? "";

        if (!is_numeric($expense_id)) {

            $error = "Invalid expense.";

        } else {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM expenses
                    WHERE id = ?
                      AND meal_group_id = ?
                ");

                $stmt->execute([
                    $expense_id,
                    $group_id
                ]);

                $message = "Shared expense deleted successfully.";

            } catch (PDOException $e) {

                $error = "Failed to delete expense. Please try again.";
            }
        }
    }


    /* =====================================================
       SAVE INDIVIDUAL MEMBER "OTHERS"
    ===================================================== */

    elseif ($action === "save_member_other" && $can_edit) {

        $member_id = $_POST["member_id"] ?? "";
        $amount = $_POST["amount"] ?? "";
        $description = trim($_POST["description"] ?? "");

        if (!is_numeric($member_id)) {

            $error = "Invalid member.";

        } elseif (!is_numeric($amount) || $amount < 0) {

            $error = "Others amount cannot be negative.";

        } else {

            try {

                /* Make sure member belongs to this group */

                $check = $pdo->prepare("
                    SELECT id
                    FROM meal_members
                    WHERE meal_group_id = ?
                      AND user_id = ?
                    LIMIT 1
                ");

                $check->execute([
                    $group_id,
                    $member_id
                ]);

                if (!$check->fetch()) {

                    $error = "Invalid member.";

                } else {

                    /*
                     * One Individual Others amount per member.
                     */

                    $stmt = $pdo->prepare("
                        INSERT INTO member_expenses
                        (
                            meal_group_id,
                            user_id,
                            amount,
                            description,
                            added_by
                        )
                        VALUES (?, ?, ?, ?, ?)

                        ON DUPLICATE KEY UPDATE
                            amount = VALUES(amount),
                            description = VALUES(description),
                            added_by = VALUES(added_by)
                    ");

                    $stmt->execute([
                        $group_id,
                        $member_id,
                        $amount,
                        $description !== "" ? $description : null,
                        $user_id
                    ]);

                    $message = "Individual Others amount updated successfully.";
                }

            } catch (PDOException $e) {

                $error = "Failed to update individual Others. Please try again.";
            }
        }
    }
}


/* =========================================================
   GET SHARED EXPENSES
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        e.*,
        u.name AS added_by_name
    FROM expenses e
    LEFT JOIN users u
        ON e.added_by = u.id
    WHERE e.meal_group_id = ?
    ORDER BY e.expense_date DESC, e.id DESC
");

$stmt->execute([$group_id]);

$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   TOTAL SHARED OTHERS + KHALA BILL
========================================================= */

$total_shared_others = 0;
$total_khala = 0;

foreach ($expenses as $expense) {

    if ($expense["expense_type"] === "others") {

        $total_shared_others += (float) $expense["amount"];

    } elseif ($expense["expense_type"] === "khala_bill") {

        $total_khala += (float) $expense["amount"];
    }
}


/* =========================================================
   TOTAL MARKET / BAZAR
========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM market_items
    WHERE meal_group_id = ?
");

$stmt->execute([$group_id]);

$total_market = (float) $stmt->fetchColumn();


/* =========================================================
   TOTAL MEALS
========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(meal_amount), 0)
    FROM daily_meals
    WHERE meal_group_id = ?
");

$stmt->execute([$group_id]);

$total_meals = (float) $stmt->fetchColumn();


/* =========================================================
   TOTAL GIVEN MONEY
========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE meal_group_id = ?
");

$stmt->execute([$group_id]);

$total_given_money = (float) $stmt->fetchColumn();


/* =========================================================
   MEAL RATE
   ONLY MARKET / BAZAR AFFECTS MEAL RATE
========================================================= */

if ($total_meals > 0) {

    $meal_rate = $total_market / $total_meals;

} else {

    $meal_rate = 0;
}


/* =========================================================
   GET MEMBERS
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        mm.user_id,
        mm.role,
        u.name,
        u.email
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
        u.name ASC
");

$stmt->execute([$group_id]);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$member_count = count($members);


/* =========================================================
   SHARED OTHERS PER MEMBER
========================================================= */

if ($member_count > 0) {

    $shared_others_per_member =
        $total_shared_others / $member_count;

} else {

    $shared_others_per_member = 0;
}


/* =========================================================
   TOTAL EXPENSE

   This is the overall group expense.
   Individual Others are also included.
========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM member_expenses
    WHERE meal_group_id = ?
");

$stmt->execute([$group_id]);

$total_individual_others = (float) $stmt->fetchColumn();

$total_expense =
    $total_market
    + $total_shared_others
    + $total_khala
    + $total_individual_others;


/* =========================================================
   MEMBER MEAL TOTALS
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        user_id,
        COALESCE(SUM(meal_amount), 0) AS total_meals
    FROM daily_meals
    WHERE meal_group_id = ?
    GROUP BY user_id
");

$stmt->execute([$group_id]);

$member_meals = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $member_meals[$row["user_id"]] =
        (float) $row["total_meals"];
}


/* =========================================================
   MEMBER GIVEN MONEY
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        user_id,
        COALESCE(SUM(amount), 0) AS total_given
    FROM payments
    WHERE meal_group_id = ?
    GROUP BY user_id
");

$stmt->execute([$group_id]);

$member_payments = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $member_payments[$row["user_id"]] =
        (float) $row["total_given"];
}


/* =========================================================
   MEMBER INDIVIDUAL OTHERS
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        user_id,
        amount,
        description
    FROM member_expenses
    WHERE meal_group_id = ?
");

$stmt->execute([$group_id]);

$member_others = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $member_others[$row["user_id"]] = [
        "amount" => (float) $row["amount"],
        "description" => $row["description"]
    ];
}


/* =========================================================
   DISPLAY HELPERS
========================================================= */

function money($amount)
{
    return "৳" . number_format((float) $amount, 2);
}

function role_name($role)
{
    if ($role === "junior_manager") {
        return "Junior Manager";
    }

    return ucfirst($role);
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
        Calculation - <?= htmlspecialchars($group["name"]) ?>
    </title>

    <!-- USE SAME GLOBAL DESIGN AS PAYMENT / MARKET -->
    <link rel="stylesheet" href="../css/style.css">

</head>

<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">

    <div class="logo">
        🍚 Meal System
    </div>


    <div class="nav-title">
        <?= htmlspecialchars($group["name"]) ?>
    </div>


    <a
        href="meal.php?id=<?= $group_id ?>"
        class="nav-link"
    >
        🏠 Overview
    </a>


    <a
        href="daily_meals.php?id=<?= $group_id ?>"
        class="nav-link"
    >
        🍚 Daily Meals
    </a>


    <a
        href="market.php?id=<?= $group_id ?>"
        class="nav-link"
    >
        🛒 Market / Bazar
    </a>


    <a
        href="payments.php?id=<?= $group_id ?>"
        class="nav-link"
    >
        💰 Given Money
    </a>


    <a
        href="calculation.php?id=<?= $group_id ?>"
        class="nav-link active"
    >
        🧮 Calculation
    </a>


    <div class="sidebar-bottom">

        <a
            href="meal.php?id=<?= $group_id ?>#members"
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



<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h1>
                🧮 Calculation
            </h1>

            <p class="page-subtitle">

                <?= htmlspecialchars($group["name"]) ?>

                —

                <?= htmlspecialchars($group["month_name"]) ?>

                <?= htmlspecialchars($group["year"]) ?>

            </p>

        </div>


        <div class="user">

            <?= role_name($role) ?>

        </div>

    </div>



    <!-- =================================================
         ALERTS
    ================================================= -->

    <?php if ($message): ?>

        <div class="alert success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- =================================================
         SUMMARY
    ================================================= -->

    <div class="calculation-summary">


        <div class="card calculation-card">

            <div class="calculation-label">
                Total Market / Bazar
            </div>

            <div class="calculation-value">
                <?= money($total_market) ?>
            </div>

        </div>


        <div class="card calculation-card">

            <div class="calculation-label">
                Total Meals
            </div>

            <div class="calculation-value">
                <?= number_format($total_meals, 2) ?>
            </div>

        </div>


        <div class="card calculation-card">

            <div class="calculation-label">
                Meal Rate
            </div>

            <div class="calculation-value">
                <?= money($meal_rate) ?>
            </div>

        </div>


        <div class="card calculation-card">

            <div class="calculation-label">
                Total Given Money
            </div>

            <div class="calculation-value">
                <?= money($total_given_money) ?>
            </div>

        </div>


    </div>



    <!-- =================================================
         ADD SHARED EXPENSE
    ================================================= -->

    <?php if ($can_edit): ?>

        <div class="card calculation-section">

            <div class="section-title-row">

                <div>

                    <h2>
                        ➕ Add Shared Expense
                    </h2>

                    <p class="section-description">
                        Add expenses that are shared among all members.
                    </p>

                </div>

            </div>


            <div class="info-box">

                <strong>Shared Expense:</strong>

                Others and Khala Bill added here are shared among
                all members.

                Individual Others can be assigned separately below.

            </div>


            <form method="POST">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="add_expense"
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Expense Type
                        </label>

                        <select
                            name="expense_type"
                            required
                        >

                            <option value="others">
                                Shared Others
                            </option>

                            <option value="khala_bill">
                                Khala Bill
                            </option>

                        </select>

                    </div>



                    <div class="form-group">

                        <label>
                            Description
                        </label>

                        <input
                            type="text"
                            name="description"
                            placeholder="Example: Electricity"
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Amount
                        </label>

                        <input
                            type="number"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            required
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Date
                        </label>

                        <input
                            type="date"
                            name="expense_date"
                            value="<?= date("Y-m-d") ?>"
                            required
                        >

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="add-btn"
                    >
                        ➕ Add Shared Expense
                    </button>

                </div>

            </form>

        </div>

    <?php endif; ?>



    <!-- =================================================
         EXPENSE SUMMARY
    ================================================= -->

    <div class="card calculation-section">

        <div class="section-title-row">

            <div>

                <h2>
                    💰 Expense Summary
                </h2>

                <p class="section-description">
                    Overview of all group expenses.
                </p>

            </div>

        </div>


        <div class="table-container">

            <table class="table">

                <thead>

                    <tr>

                        <th>
                            Expense
                        </th>

                        <th>
                            Amount
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <tr>

                        <td>
                            🛒 Market / Bazar
                        </td>

                        <td>
                            <?= money($total_market) ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            💰 Shared Others
                        </td>

                        <td>
                            <?= money($total_shared_others) ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            🧹 Khala Bill
                        </td>

                        <td>
                            <?= money($total_khala) ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            👤 Individual Others
                        </td>

                        <td>
                            <?= money($total_individual_others) ?>
                        </td>

                    </tr>


                    <tr class="total-row">

                        <th>
                            Total Expense
                        </th>

                        <th>
                            <?= money($total_expense) ?>
                        </th>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>



    <!-- =================================================
         SHARED EXPENSE HISTORY
    ================================================= -->

    <div class="card calculation-section">

        <div class="section-title-row">

            <div>

                <h2>
                    🧾 Shared Expense History
                </h2>

                <p class="section-description">
                    View and manage shared expenses.
                </p>

            </div>

        </div>


        <?php if (empty($expenses)): ?>

            <div class="empty-state">
                No shared expenses added yet.
            </div>

        <?php else: ?>


            <div class="table-container">

                <table class="table">

                    <thead>

                        <tr>

                            <th>
                                Type
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Added By
                            </th>

                            <?php if ($can_edit): ?>

                                <th>
                                    Actions
                                </th>

                            <?php endif; ?>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($expenses as $expense): ?>

                        <tr>


                            <td>

                                <?php if ($expense["expense_type"] === "khala_bill"): ?>

                                    🧹 Khala Bill

                                <?php else: ?>

                                    💰 Shared Others

                                <?php endif; ?>

                            </td>



                            <td>

                                <?= $expense["description"]
                                    ? htmlspecialchars($expense["description"])
                                    : "-"
                                ?>

                            </td>



                            <td>

                                <strong>
                                    <?= money($expense["amount"]) ?>
                                </strong>

                            </td>



                            <td>

                                <?= htmlspecialchars(
                                    $expense["expense_date"]
                                ) ?>

                            </td>



                            <td>

                                <?= htmlspecialchars(
                                    $expense["added_by_name"] ?? "Unknown"
                                ) ?>

                            </td>



                            <?php if ($can_edit): ?>

                                <td>

                                    <details>

                                        <summary>
                                            Edit
                                        </summary>


                                        <div class="edit-box">


                                            <form method="POST">

                                                <?= csrf_field() ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="edit_expense"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="expense_id"
                                                    value="<?= $expense["id"] ?>"
                                                >


                                                <div class="form-group">

                                                    <label>
                                                        Type
                                                    </label>

                                                    <select
                                                        name="expense_type"
                                                    >

                                                        <option
                                                            value="others"
                                                            <?= $expense["expense_type"] === "others"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Shared Others
                                                        </option>


                                                        <option
                                                            value="khala_bill"
                                                            <?= $expense["expense_type"] === "khala_bill"
                                                                ? "selected"
                                                                : "" ?>
                                                        >
                                                            Khala Bill
                                                        </option>

                                                    </select>

                                                </div>


                                                <div class="form-group">

                                                    <label>
                                                        Description
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="description"
                                                        value="<?= htmlspecialchars(
                                                            $expense["description"] ?? ""
                                                        ) ?>"
                                                    >

                                                </div>


                                                <div class="form-group">

                                                    <label>
                                                        Amount
                                                    </label>

                                                    <input
                                                        type="number"
                                                        name="amount"
                                                        step="0.01"
                                                        min="0.01"
                                                        value="<?= htmlspecialchars(
                                                            $expense["amount"]
                                                        ) ?>"
                                                        required
                                                    >

                                                </div>


                                                <div class="form-group">

                                                    <label>
                                                        Date
                                                    </label>

                                                    <input
                                                        type="date"
                                                        name="expense_date"
                                                        value="<?= htmlspecialchars(
                                                            $expense["expense_date"]
                                                        ) ?>"
                                                        required
                                                    >

                                                </div>


                                                <div class="actions">

                                                    <button
                                                        type="submit"
                                                        class="action-btn edit-btn"
                                                    >
                                                        Save
                                                    </button>

                                            </form>


                                            <?php if ($can_delete): ?>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Delete this shared expense?');"
                                                >

                                                    <?= csrf_field() ?>

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete_expense"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="expense_id"
                                                        value="<?= $expense["id"] ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="action-btn delete-btn"
                                                    >
                                                        Delete
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                                </div>

                                        </div>

                                    </details>

                                </td>

                            <?php endif; ?>


                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>



    <!-- =================================================
         MEMBER CALCULATION
    ================================================= -->

    <div class="card calculation-section">

        <div class="section-title-row">

            <div>

                <h2>
                    👥 Member Calculation
                </h2>

                <p class="section-description">

                    Meal Rate = Market / Bazar ÷ Total Meals.

                    Shared Others is divided equally among all members.

                    Individual Others is charged only to the selected member.

                </p>

            </div>

        </div>


        <div class="info-box">

            <strong>
                Shared Others per member:
            </strong>

            <?= money($shared_others_per_member) ?>


            <span class="separator">
                |
            </span>


            <strong>
                Total Members:
            </strong>

            <?= $member_count ?>

        </div>



        <div class="table-container">

            <table class="table calculation-table">

                <thead>

                    <tr>

                        <th>
                            Member
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Total Meals
                        </th>

                        <th>
                            Meal Cost
                        </th>

                        <th>
                            Khala Bill
                        </th>

                        <th>
                            Shared Others
                        </th>

                        <th>
                            Individual Others
                        </th>

                        <th>
                            Total Expense
                        </th>

                        <th>
                            Given Money
                        </th>

                        <th>
                            Balance
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($members as $member): ?>

                    <?php

                    $member_id = (int) $member["user_id"];

                    $member_total_meals =
                        $member_meals[$member_id] ?? 0;

                    $member_meal_cost =
                        $member_total_meals * $meal_rate;


                    /*
                     * Khala Bill
                     * Same amount for every member.
                     */

                    $member_khala =
                        $total_khala;


                    /*
                     * Shared Others
                     * Divided equally among all members.
                     */

                    $member_shared_others =
                        $shared_others_per_member;


                    /*
                     * Individual Others
                     * Only this member pays this amount.
                     */

                    $member_individual_other =
                        $member_others[$member_id]["amount"] ?? 0;

                    $other_description =
                        $member_others[$member_id]["description"] ?? "";


                    /*
                     * FINAL MEMBER EXPENSE
                     */

                    $member_total_expense =
                        $member_meal_cost
                        + $member_khala
                        + $member_shared_others
                        + $member_individual_other;


                    $given =
                        $member_payments[$member_id] ?? 0;


                    /*
                     * Positive = Receive
                     * Negative = Pay
                     */

                    $balance =
                        $given - $member_total_expense;

                    ?>


                    <tr>


                        <!-- MEMBER -->

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $member["name"]
                                ) ?>
                            </strong>

                        </td>



                        <!-- ROLE -->

                        <td>

                            <?php if ($member["role"] === "manager"): ?>

                                <span class="badge badge-manager">
                                    Manager
                                </span>

                            <?php elseif ($member["role"] === "junior_manager"): ?>

                                <span class="badge badge-junior">
                                    Junior Manager
                                </span>

                            <?php else: ?>

                                <span class="badge badge-member">
                                    Member
                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- TOTAL MEALS -->

                        <td>

                            <?= number_format(
                                $member_total_meals,
                                2
                            ) ?>

                        </td>



                        <!-- MEAL COST -->

                        <td>

                            <?= money(
                                $member_meal_cost
                            ) ?>

                        </td>



                        <!-- KHALA -->

                        <td>

                            <?= money(
                                $member_khala
                            ) ?>

                        </td>



                        <!-- SHARED OTHERS -->

                        <td>

                            <strong>

                                <?= money(
                                    $member_shared_others
                                ) ?>

                            </strong>

                        </td>



                        <!-- INDIVIDUAL OTHERS -->

                        <td>

                            <?= money(
                                $member_individual_other
                            ) ?>


                            <?php if ($other_description): ?>

                                <div class="other-description">

                                    <?= htmlspecialchars(
                                        $other_description
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <?php if ($can_edit): ?>

                                <details>

                                    <summary>
                                        Change
                                    </summary>


                                    <div class="edit-box">

                                        <form method="POST">

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="save_member_other"
                                            >


                                            <input
                                                type="hidden"
                                                name="member_id"
                                                value="<?= $member_id ?>"
                                            >


                                            <div class="form-group">

                                                <label>
                                                    Individual Others Amount
                                                </label>

                                                <input
                                                    type="number"
                                                    name="amount"
                                                    step="0.01"
                                                    min="0"
                                                    value="<?= htmlspecialchars(
                                                        $member_individual_other
                                                    ) ?>"
                                                    required
                                                >

                                            </div>


                                            <div class="form-group">

                                                <label>
                                                    Description
                                                </label>

                                                <input
                                                    type="text"
                                                    name="description"
                                                    value="<?= htmlspecialchars(
                                                        $other_description
                                                    ) ?>"
                                                    placeholder="Example: Cleaning"
                                                >

                                            </div>


                                            <button
                                                type="submit"
                                                class="action-btn edit-btn"
                                            >
                                                Save
                                            </button>

                                        </form>

                                    </div>

                                </details>

                            <?php endif; ?>

                        </td>



                        <!-- TOTAL EXPENSE -->

                        <td>

                            <strong>
                                <?= money(
                                    $member_total_expense
                                ) ?>
                            </strong>

                        </td>



                        <!-- GIVEN MONEY -->

                        <td>

                            <?= money(
                                $given
                            ) ?>

                        </td>



                        <!-- BALANCE -->

                        <td>

                            <?php if ($balance > 0): ?>

                                <span class="receive">

                                    Receive
                                    <?= money($balance) ?>

                                </span>

                            <?php elseif ($balance < 0): ?>

                                <span class="pay">

                                    Pay
                                    <?= money(abs($balance)) ?>

                                </span>

                            <?php else: ?>

                                <span class="settle">
                                    Settled
                                </span>

                            <?php endif; ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>

    </div>



    <!-- BACK -->

    <a
        href="meal.php?id=<?= $group_id ?>"
        class="back-button"
    >
        ← Back to Overview
    </a>


</div>


</body>

</html>