<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$group_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($group_id <= 0) {
    die("Invalid meal group.");
}

/* -------------------------------------------------
   GET GROUP + CURRENT USER ROLE
------------------------------------------------- */

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
    WHERE mg.id = ?
      AND mm.user_id = ?
");

$stmt->execute([$group_id, $user_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    die("You are not a member of this meal group.");
}

$role = $group["role"];

/*
    Manager + Junior Manager:
    Add/Edit expenses

    Manager only:
    Delete expenses
*/
$can_edit = ($role === "manager" || $role === "junior_manager");
$can_delete = ($role === "manager");


/* -------------------------------------------------
   ADD MARKET ITEM
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_item"])) {

    if (!$can_edit) {
        die("You do not have permission to add market items.");
    }

    $item_name = trim($_POST["item_name"] ?? "");
    $amount = $_POST["amount"] ?? "";
    $purchase_date = $_POST["purchase_date"] ?? "";

    if ($item_name === "" || $amount === "" || $purchase_date === "") {
        die("Please fill in all fields.");
    }

    if (!is_numeric($amount) || $amount < 0) {
        die("Invalid amount.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO market_items
        (meal_group_id, item_name, amount, purchase_date, added_by)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $group_id,
        $item_name,
        $amount,
        $purchase_date,
        $user_id
    ]);

    header("Location: market.php?id=" . $group_id);
    exit;
}


/* -------------------------------------------------
   DELETE MARKET ITEM
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_item"])) {

    if (!$can_delete) {
        die("You do not have permission to delete market items.");
    }

    $item_id = (int)$_POST["item_id"];

    $stmt = $pdo->prepare("
        DELETE FROM market_items
        WHERE id = ?
          AND meal_group_id = ?
    ");

    $stmt->execute([
        $item_id,
        $group_id
    ]);

    header("Location: market.php?id=" . $group_id);
    exit;
}


/* -------------------------------------------------
   EDIT MARKET ITEM
------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_item"])) {

    if (!$can_edit) {
        die("You do not have permission to edit market items.");
    }

    $item_id = (int)$_POST["item_id"];
    $item_name = trim($_POST["item_name"] ?? "");
    $amount = $_POST["amount"] ?? "";
    $purchase_date = $_POST["purchase_date"] ?? "";

    if ($item_name === "" || $amount === "" || $purchase_date === "") {
        die("Please fill in all fields.");
    }

    if (!is_numeric($amount) || $amount < 0) {
        die("Invalid amount.");
    }

    $stmt = $pdo->prepare("
        UPDATE market_items
        SET item_name = ?,
            amount = ?,
            purchase_date = ?
        WHERE id = ?
          AND meal_group_id = ?
    ");

    $stmt->execute([
        $item_name,
        $amount,
        $purchase_date,
        $item_id,
        $group_id
    ]);

    header("Location: market.php?id=" . $group_id);
    exit;
}


/* -------------------------------------------------
   GET MARKET ITEMS
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT 
        mi.id,
        mi.item_name,
        mi.amount,
        mi.purchase_date,
        mi.added_by,
        u.name AS added_by_name
    FROM market_items mi
    INNER JOIN users u
        ON mi.added_by = u.id
    WHERE mi.meal_group_id = ?
    ORDER BY mi.purchase_date DESC, mi.id DESC
");

$stmt->execute([$group_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -------------------------------------------------
   TOTAL MARKET EXPENSE
------------------------------------------------- */

$total_market = 0;

foreach ($items as $item) {
    $total_market += (float)$item["amount"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Market / Bazar - Meal System</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f7f7fb;
    color: #333;
}


/* SIDEBAR */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 250px;
    height: 100vh;

    background: linear-gradient(
        180deg,
        #f3e8ff,
        #fce7f3
    );

    padding: 25px 18px;

    overflow-y: auto;
}

.logo {
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 30px;
    color: #6b21a8;
}

.nav-title {
    font-size: 12px;
    color: #777;
    margin: 20px 10px 8px;
    text-transform: uppercase;
}

.nav-link {
    display: block;

    text-decoration: none;

    color: #444;

    padding: 12px 14px;

    border-radius: 10px;

    margin-bottom: 6px;

    transition: 0.2s;
}

.nav-link:hover {
    background: #ffffff;
}

.nav-link.active {
    background: #ffffff;
    color: #7c3aed;
    font-weight: bold;
}


/* MAIN */

.main {
    margin-left: 250px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 25px;
}

.topbar h1 {
    margin: 0;
    font-size: 28px;
}

.user {
    color: #666;
}


/* CARDS */

.card {
    background: white;

    border-radius: 15px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow: 0 3px 15px rgba(0,0,0,0.06);
}

.card h2 {
    margin-top: 0;
}


/* GROUP INFO */

.group-info {
    display: flex;
    gap: 25px;
    flex-wrap: wrap;

    color: #555;
}


/* FORM */

.form-grid {
    display: grid;

    grid-template-columns:
        2fr
        1fr
        1fr
        auto;

    gap: 12px;

    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 13px;
    margin-bottom: 6px;
    color: #666;
}

input {
    padding: 11px;

    border: 1px solid #ddd;

    border-radius: 8px;

    font-size: 14px;
}

button {
    border: none;

    padding: 11px 18px;

    border-radius: 8px;

    cursor: pointer;

    font-size: 14px;
}

.add-btn {
    background: #7c3aed;
    color: white;
}

.add-btn:hover {
    background: #6d28d9;
}


/* TABLE */

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 700px;
}

th {
    background: #f5f3ff;

    text-align: left;

    padding: 13px;

    font-size: 14px;
}

td {
    padding: 13px;

    border-bottom: 1px solid #eee;

    font-size: 14px;
}

tr:hover {
    background: #fafafa;
}


/* TOTAL */

.total-box {
    display: flex;

    justify-content: space-between;

    align-items: center;

    background: #f5f3ff;

    padding: 18px;

    border-radius: 10px;

    margin-top: 20px;
}

.total-label {
    font-weight: bold;
}

.total-amount {
    font-size: 22px;

    font-weight: bold;

    color: #7c3aed;
}


/* ACTION BUTTONS */

.action-btn {
    padding: 7px 11px;

    font-size: 12px;

    margin-right: 5px;
}

.edit-btn {
    background: #ede9fe;
    color: #6d28d9;
}

.delete-btn {
    background: #fee2e2;
    color: #dc2626;
}


/* EDIT FORM */

.edit-form {
    display: flex;

    gap: 8px;

    align-items: center;
}

.edit-form input {
    width: 120px;
}


/* MOBILE */

@media (max-width: 800px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;
    }

    .main {
        margin-left: 0;

        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .topbar {
        flex-direction: column;

        align-items: flex-start;

        gap: 10px;
    }

    .group-info {
        flex-direction: column;

        gap: 8px;
    }

}

</style>

</head>


<body>


<!-- SIDEBAR -->

<div class="sidebar">

    <div class="logo">
        🍚 Meal System
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
        🍽️ Daily Meals
    </a>

    <a
        href="market.php?id=<?= $group_id ?>"
        class="nav-link active"
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
        class="nav-link"
    >
        🧮 Calculation
    </a>


    <div class="nav-title">
        Group
    </div>

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
        📋 My Groups
    </a>

    <a
        href="../logout.php"
        class="nav-link"
    >
        🚪 Logout
    </a>

</div>


<!-- MAIN -->

<div class="main">


    <!-- TOP BAR -->

    <div class="topbar">

        <div>
            <h1>
                🛒 Market / Bazar
            </h1>

            <div class="user">
                <?= htmlspecialchars($group["name"]) ?>
            </div>
        </div>

        <div class="user">
            <?= htmlspecialchars($_SESSION["user_name"]) ?>
            (<?= htmlspecialchars($role) ?>)
        </div>

    </div>


    <!-- GROUP INFO -->

    <div class="card">

        <div class="group-info">

            <div>
                <strong>Period:</strong>
                <?= htmlspecialchars($group["month_name"]) ?>
                <?= htmlspecialchars($group["year"]) ?>
            </div>

            <div>
                <strong>Join Code:</strong>
                <?= htmlspecialchars($group["join_code"]) ?>
            </div>

        </div>

    </div>


    <?php if ($can_edit): ?>

    <!-- ADD EXPENSE -->

    <div class="card">

        <h2>
            ➕ Add Market Expense
        </h2>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Item Name
                    </label>

                    <input
                        type="text"
                        name="item_name"
                        placeholder="Example: Rice"
                        required
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
                        min="0"
                        placeholder="2500"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Purchase Date
                    </label>

                    <input
                        type="date"
                        name="purchase_date"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <button
                    type="submit"
                    name="add_item"
                    class="add-btn"
                >
                    Add Expense
                </button>

            </div>

        </form>

    </div>

    <?php endif; ?>


    <!-- MARKET LIST -->

    <div class="card">

        <h2>
            📋 Market Expenses
        </h2>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Item
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Purchase Date
                        </th>

                        <th>
                            Added By
                        </th>

                        <?php if ($can_edit || $can_delete): ?>

                        <th>
                            Actions
                        </th>

                        <?php endif; ?>

                    </tr>

                </thead>


                <tbody>

                <?php if (count($items) > 0): ?>

                    <?php foreach ($items as $item): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($item["item_name"]) ?>
                        </td>

                        <td>
                            ৳<?= number_format($item["amount"], 2) ?>
                        </td>

                        <td>
                            <?= date(
                                "d M Y",
                                strtotime($item["purchase_date"])
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($item["added_by_name"]) ?>
                        </td>


                        <?php if ($can_edit || $can_delete): ?>

                        <td>

                            <?php if ($can_edit): ?>

                            <details>

                                <summary
                                    class="action-btn edit-btn"
                                    style="display:inline-block; cursor:pointer;"
                                >
                                    Edit
                                </summary>

                                <form
                                    method="POST"
                                    style="margin-top:10px;"
                                >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= $item["id"] ?>"
                                    >

                                    <input
                                        type="text"
                                        name="item_name"
                                        value="<?= htmlspecialchars($item["item_name"]) ?>"
                                        required
                                    >

                                    <input
                                        type="number"
                                        name="amount"
                                        step="0.01"
                                        min="0"
                                        value="<?= htmlspecialchars($item["amount"]) ?>"
                                        required
                                    >

                                    <input
                                        type="date"
                                        name="purchase_date"
                                        value="<?= htmlspecialchars($item["purchase_date"]) ?>"
                                        required
                                    >

                                    <button
                                        type="submit"
                                        name="edit_item"
                                        class="action-btn edit-btn"
                                    >
                                        Save
                                    </button>

                                </form>

                            </details>

                            <?php endif; ?>


                            <?php if ($can_delete): ?>

                            <form
                                method="POST"
                                style="display:inline;"
                                onsubmit="return confirm('Delete this market item?');"
                            >

                                <input
                                    type="hidden"
                                    name="item_id"
                                    value="<?= $item["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_item"
                                    class="action-btn delete-btn"
                                >
                                    Delete
                                </button>

                            </form>

                            <?php endif; ?>

                        </td>

                        <?php endif; ?>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="5"
                            style="text-align:center; padding:30px;"
                        >
                            No market expenses added yet.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- TOTAL -->

        <div class="total-box">

            <div class="total-label">
                Total Market Expense
            </div>

            <div class="total-amount">
                ৳<?= number_format($total_market, 2) ?>
            </div>

        </div>

    </div>


</div>


</body>

</html>