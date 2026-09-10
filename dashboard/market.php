[<?php

session_start();

require_once "../config/database.php";


/* -------------------------------------------------
   CHECK LOGIN
------------------------------------------------- */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit;

}


$user_id = $_SESSION["user_id"];

$group_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;


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

$stmt->execute([
    $group_id,
    $user_id
]);

$group = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$group) {

    die("You are not a member of this meal group.");

}


$role = $group["role"];


/*
    Manager + Junior Manager:
    Add / Edit

    Manager only:
    Delete
*/

$can_edit =
    ($role === "manager" ||
     $role === "junior_manager");

$can_delete =
    ($role === "manager");


/* -------------------------------------------------
   ADD MARKET ITEM
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_item"])
) {

    if (!$can_edit) {

        die(
            "You do not have permission to add market items."
        );

    }


    $item_name =
        trim($_POST["item_name"] ?? "");

    $amount =
        $_POST["amount"] ?? "";

    $purchase_date =
        $_POST["purchase_date"] ?? "";


    if (
        $item_name === "" ||
        $amount === "" ||
        $purchase_date === ""
    ) {

        die("Please fill in all fields.");

    }


    if (
        !is_numeric($amount) ||
        $amount < 0
    ) {

        die("Invalid amount.");

    }


    $stmt = $pdo->prepare("
        INSERT INTO market_items
        (
            meal_group_id,
            item_name,
            amount,
            purchase_date,
            added_by
        )
        VALUES (?, ?, ?, ?, ?)
    ");


    $stmt->execute([
        $group_id,
        $item_name,
        $amount,
        $purchase_date,
        $user_id
    ]);


    header(
        "Location: market.php?id=" . $group_id
    );

    exit;

}


/* -------------------------------------------------
   DELETE MARKET ITEM
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_item"])
) {

    if (!$can_delete) {

        die(
            "You do not have permission to delete market items."
        );

    }


    $item_id =
        (int)($_POST["item_id"] ?? 0);


    $stmt = $pdo->prepare("
        DELETE FROM market_items
        WHERE id = ?
          AND meal_group_id = ?
    ");


    $stmt->execute([
        $item_id,
        $group_id
    ]);


    header(
        "Location: market.php?id=" . $group_id
    );

    exit;

}


/* -------------------------------------------------
   EDIT MARKET ITEM
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["edit_item"])
) {

    if (!$can_edit) {

        die(
            "You do not have permission to edit market items."
        );

    }


    $item_id =
        (int)($_POST["item_id"] ?? 0);

    $item_name =
        trim($_POST["item_name"] ?? "");

    $amount =
        $_POST["amount"] ?? "";

    $purchase_date =
        $_POST["purchase_date"] ?? "";


    if (
        $item_id <= 0 ||
        $item_name === "" ||
        $amount === "" ||
        $purchase_date === ""
    ) {

        die("Please fill in all fields.");

    }


    if (
        !is_numeric($amount) ||
        $amount < 0
    ) {

        die("Invalid amount.");

    }


    $stmt = $pdo->prepare("
        UPDATE market_items

        SET
            item_name = ?,
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


    header(
        "Location: market.php?id=" . $group_id
    );

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

    ORDER BY
        mi.purchase_date DESC,
        mi.id DESC
");


$stmt->execute([
    $group_id
]);


$items =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -------------------------------------------------
   TOTAL MARKET EXPENSE
------------------------------------------------- */

$total_market = 0;


foreach ($items as $item) {

    $total_market +=
        (float)$item["amount"];

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
        Market / Bazar - Meal System
    </title>


    <!-- GLOBAL STYLESHEET -->

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>


<body>


<!-- =================================================
     SIDEBAR
================================================= -->

<div class="sidebar">


    <div class="logo">

        🍚 Meal System

    </div>


    <!-- OVERVIEW -->

    <a
        href="meal.php?id=<?= $group_id ?>"
        class="nav-link"
    >

        🏠 Overview

    </a>


    <!-- DAILY MEALS -->

    <a
        href="daily_meals.php?id=<?= $group_id ?>"
        class="nav-link"
    >

        🍽️ Daily Meals

    </a>


    <!-- MARKET -->

    <a
        href="market.php?id=<?= $group_id ?>"
        class="nav-link active"
    >

        🛒 Market / Bazar

    </a>


    <!-- GIVEN MONEY -->

    <a
        href="payments.php?id=<?= $group_id ?>"
        class="nav-link"
    >

        💰 Given Money

    </a>


    <!-- CALCULATION -->

    <a
        href="calculation.php?id=<?= $group_id ?>"
        class="nav-link"
    >

        🧮 Calculation

    </a>


    <!-- GROUP -->

    <div class="nav-title">

        Group

    </div>


    <!-- MEMBERS -->

    <a
        href="meal.php?id=<?= $group_id ?>#members"
        class="nav-link"
    >

        👥 Members

    </a>


    <!-- MY GROUPS -->

    <a
        href="index.php"
        class="nav-link"
    >

        📋 My Groups

    </a>


    <!-- LOGOUT -->

    <a
        href="../logout.php"
        class="nav-link"
    >

        🚪 Logout

    </a>


</div>


<!-- =================================================
     MAIN CONTENT
================================================= -->

<div class="main">


    <!-- =================================================
         TOP BAR
    ================================================= -->

    <div class="topbar">


        <div>

            <h1>

                🛒 Market / Bazar

            </h1>


            <div class="user">

                <?= htmlspecialchars(
                    $group["name"]
                ) ?>

            </div>

        </div>


        <div class="user">

            <?= htmlspecialchars(
                $_SESSION["user_name"]
            ) ?>

            (

            <?= htmlspecialchars(
                $role
            ) ?>

            )

        </div>


    </div>


    <!-- =================================================
         GROUP INFORMATION
    ================================================= -->

    <div class="card">


        <div class="group-info">


            <div>

                <strong>
                    Period:
                </strong>

                <?= htmlspecialchars(
                    $group["month_name"]
                ) ?>

                <?= htmlspecialchars(
                    $group["year"]
                ) ?>

            </div>


            <div>

                <strong>
                    Join Code:
                </strong>

                <?= htmlspecialchars(
                    $group["join_code"]
                ) ?>

            </div>


        </div>


    </div>


    <!-- =================================================
         ADD MARKET EXPENSE
    ================================================= -->

    <?php if ($can_edit): ?>


        <div class="card">


            <h2>

                ➕ Add Market Expense

            </h2>


            <form method="POST">


                <div class="form-grid">


                    <!-- ITEM NAME -->

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


                    <!-- AMOUNT -->

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


                    <!-- DATE -->

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


                    <!-- BUTTON -->

                    <button
                        type="submit"
                        name="add_item"
                        class="add-btn"
                    >

                        ➕ Add Expense

                    </button>


                </div>


            </form>


        </div>


    <?php endif; ?>


    <!-- =================================================
         MARKET EXPENSES
    ================================================= -->

    <div class="card">


        <h2>

            📋 Market Expenses

        </h2>


        <?php if (count($items) > 0): ?>


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


                            <?php if (
                                $can_edit ||
                                $can_delete
                            ): ?>

                                <th>
                                    Actions
                                </th>

                            <?php endif; ?>


                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $items
                            as $item
                        ): ?>


                            <tr>


                                <!-- ITEM -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $item["item_name"]
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- AMOUNT -->

                                <td>

                                    <strong>

                                        ৳<?= number_format(
                                            $item["amount"],
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $item["purchase_date"]
                                        )
                                    ) ?>

                                </td>


                                <!-- ADDED BY -->

                                <td>

                                    <?= htmlspecialchars(
                                        $item["added_by_name"]
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <?php if (
                                    $can_edit ||
                                    $can_delete
                                ): ?>


                                    <td>


                                        <!-- EDIT -->

                                        <?php if (
                                            $can_edit
                                        ): ?>


                                            <details>


                                                <summary
                                                    class="action-btn edit-btn"
                                                >

                                                    ✏️ Edit

                                                </summary>


                                                <form
                                                    method="POST"
                                                    class="edit-form"
                                                >


                                                    <input
                                                        type="hidden"
                                                        name="item_id"
                                                        value="<?= $item["id"] ?>"
                                                    >


                                                    <!-- ITEM NAME -->

                                                    <input
                                                        type="text"
                                                        name="item_name"
                                                        value="<?= htmlspecialchars(
                                                            $item["item_name"]
                                                        ) ?>"
                                                        placeholder="Item name"
                                                        required
                                                    >


                                                    <!-- AMOUNT -->

                                                    <input
                                                        type="number"
                                                        name="amount"
                                                        step="0.01"
                                                        min="0"
                                                        value="<?= htmlspecialchars(
                                                            $item["amount"]
                                                        ) ?>"
                                                        required
                                                    >


                                                    <!-- DATE -->

                                                    <input
                                                        type="date"
                                                        name="purchase_date"
                                                        value="<?= htmlspecialchars(
                                                            $item["purchase_date"]
                                                        ) ?>"
                                                        required
                                                    >


                                                    <!-- SAVE -->

                                                    <button
                                                        type="submit"
                                                        name="edit_item"
                                                        class="action-btn edit-btn"
                                                    >

                                                        💾 Save

                                                    </button>


                                                </form>


                                            </details>


                                        <?php endif; ?>


                                        <!-- DELETE -->

                                        <?php if (
                                            $can_delete
                                        ): ?>


                                            <form
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="
                                                    return confirm(
                                                        'Delete this market item?'
                                                    );
                                                "
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

                                                    🗑️ Delete

                                                </button>


                                            </form>


                                        <?php endif; ?>


                                    </td>


                                <?php endif; ?>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <div
                style="
                    text-align:center;
                    padding:40px 20px;
                "
            >


                <div
                    style="
                        font-size:45px;
                        margin-bottom:10px;
                    "
                >

                    🛒

                </div>


                <h3>

                    No Market Expenses Yet

                </h3>


                <p>

                    No market or bazar expenses have
                    been added for this meal group.

                </p>


            </div>


        <?php endif; ?>


        <!-- =================================================
             TOTAL
        ================================================= -->

        <div class="total-box">


            <div class="total-label">

                Total Market Expense

            </div>


            <div class="total-amount">

                ৳<?= number_format(
                    $total_market,
                    2
                ) ?>

            </div>


        </div>


    </div>


</div>


</body>

</html>]