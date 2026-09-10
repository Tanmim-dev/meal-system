<?php

session_start();

require_once "../config/database.php";


/* -------------------------------------------------
   LOGIN CHECK
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
    Add/Edit payments

    Manager only:
    Delete payments
*/

$can_edit =
    ($role === "manager" || $role === "junior_manager");

$can_delete =
    ($role === "manager");


/* -------------------------------------------------
   ADD PAYMENT
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_payment"])
) {

    if (!$can_edit) {

        die("You do not have permission to add payments.");

    }


    $payment_user_id =
        (int)($_POST["payment_user_id"] ?? 0);

    $amount =
        $_POST["amount"] ?? "";

    $payment_date =
        $_POST["payment_date"] ?? "";

    $note =
        trim($_POST["note"] ?? "");


    if (
        $payment_user_id <= 0 ||
        $amount === "" ||
        $payment_date === ""
    ) {

        die("Please fill in all required fields.");

    }


    if (!is_numeric($amount) || $amount <= 0) {

        die("Invalid payment amount.");

    }


    /* Make sure selected user belongs to this group */

    $stmt = $pdo->prepare("
        SELECT id
        FROM meal_members
        WHERE meal_group_id = ?
          AND user_id = ?
    ");

    $stmt->execute([
        $group_id,
        $payment_user_id
    ]);


    if (!$stmt->fetch()) {

        die("Invalid member.");

    }


    /* Insert payment */

    $stmt = $pdo->prepare("
        INSERT INTO payments
        (
            meal_group_id,
            user_id,
            amount,
            payment_date,
            note,
            added_by
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $group_id,
        $payment_user_id,
        $amount,
        $payment_date,
        $note !== "" ? $note : null,
        $user_id
    ]);


    header(
        "Location: payments.php?id=" . $group_id
    );

    exit;

}


/* -------------------------------------------------
   EDIT PAYMENT
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["edit_payment"])
) {

    if (!$can_edit) {

        die("You do not have permission to edit payments.");

    }


    $payment_id =
        (int)($_POST["payment_id"] ?? 0);

    $payment_user_id =
        (int)($_POST["payment_user_id"] ?? 0);

    $amount =
        $_POST["amount"] ?? "";

    $payment_date =
        $_POST["payment_date"] ?? "";

    $note =
        trim($_POST["note"] ?? "");


    if (
        $payment_id <= 0 ||
        $payment_user_id <= 0 ||
        $amount === "" ||
        $payment_date === ""
    ) {

        die("Please fill in all required fields.");

    }


    if (!is_numeric($amount) || $amount <= 0) {

        die("Invalid payment amount.");

    }


    /* Make sure member belongs to group */

    $stmt = $pdo->prepare("
        SELECT id
        FROM meal_members
        WHERE meal_group_id = ?
          AND user_id = ?
    ");

    $stmt->execute([
        $group_id,
        $payment_user_id
    ]);


    if (!$stmt->fetch()) {

        die("Invalid member.");

    }


    /* Update payment */

    $stmt = $pdo->prepare("
        UPDATE payments
        SET
            user_id = ?,
            amount = ?,
            payment_date = ?,
            note = ?
        WHERE id = ?
          AND meal_group_id = ?
    ");

    $stmt->execute([
        $payment_user_id,
        $amount,
        $payment_date,
        $note !== "" ? $note : null,
        $payment_id,
        $group_id
    ]);


    header(
        "Location: payments.php?id=" . $group_id
    );

    exit;

}


/* -------------------------------------------------
   DELETE PAYMENT
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_payment"])
) {

    if (!$can_delete) {

        die("You do not have permission to delete payments.");

    }


    $payment_id =
        (int)($_POST["payment_id"] ?? 0);


    $stmt = $pdo->prepare("
        DELETE FROM payments
        WHERE id = ?
          AND meal_group_id = ?
    ");

    $stmt->execute([
        $payment_id,
        $group_id
    ]);


    header(
        "Location: payments.php?id=" . $group_id
    );

    exit;

}


/* -------------------------------------------------
   GET ALL MEMBERS
------------------------------------------------- */

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
            ELSE 3
        END,
        u.name
");

$stmt->execute([
    $group_id
]);

$members =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -------------------------------------------------
   GET PAYMENTS
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.user_id,
        p.amount,
        p.payment_date,
        p.note,
        p.added_by,

        u.name AS member_name,

        adder.name AS added_by_name

    FROM payments p

    INNER JOIN users u
        ON p.user_id = u.id

    INNER JOIN users adder
        ON p.added_by = adder.id

    WHERE p.meal_group_id = ?

    ORDER BY
        p.payment_date DESC,
        p.id DESC
");

$stmt->execute([
    $group_id
]);

$payments =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -------------------------------------------------
   TOTAL MONEY GIVEN
------------------------------------------------- */

$total_given = 0;


foreach ($payments as $payment) {

    $total_given +=
        (float)$payment["amount"];

}


/* -------------------------------------------------
   MEMBER TOTALS
------------------------------------------------- */

$member_totals = [];


foreach ($members as $member) {

    $member_totals[$member["id"]] = 0;

}


foreach ($payments as $payment) {

    if (
        isset(
            $member_totals[$payment["user_id"]]
        )
    ) {

        $member_totals[$payment["user_id"]] +=
            (float)$payment["amount"];

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

    <title>
        Given Money - Meal System
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
        class="nav-link"
    >

        🛒 Market / Bazar

    </a>


    <!-- GIVEN MONEY -->

    <a
        href="payments.php?id=<?= $group_id ?>"
        class="nav-link active"
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


    <!-- GROUP SECTION -->

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

                💰 Given Money

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


    <?php if ($can_edit): ?>


    <!-- =================================================
         ADD PAYMENT
    ================================================= -->

    <div class="card">


        <h2>

            ➕ Add Given Money

        </h2>


        <form method="POST">


            <div class="form-grid">


                <!-- MEMBER -->

                <div class="form-group">


                    <label>
                        Member
                    </label>


                    <select
                        name="payment_user_id"
                        required
                    >


                        <option value="">

                            Select Member

                        </option>


                        <?php foreach (
                            $members
                            as $member
                        ): ?>


                            <option
                                value="<?= $member["id"] ?>"
                            >

                                <?= htmlspecialchars(
                                    $member["name"]
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $member["role"]
                                ) ?>

                            </option>


                        <?php endforeach; ?>


                    </select>


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
                        min="0.01"
                        placeholder="5000"
                        required
                    >


                </div>


                <!-- DATE -->

                <div class="form-group">


                    <label>
                        Date
                    </label>


                    <input
                        type="date"
                        name="payment_date"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >


                </div>


                <!-- NOTE -->

                <div class="form-group">


                    <label>
                        Note
                    </label>


                    <input
                        type="text"
                        name="note"
                        placeholder="Monthly payment"
                    >


                </div>


                <!-- ADD BUTTON -->

                <button
                    type="submit"
                    name="add_payment"
                    class="add-btn"
                >

                    Add Money

                </button>


            </div>


        </form>


    </div>


    <?php endif; ?>


    <!-- =================================================
         MEMBER CONTRIBUTIONS
    ================================================= -->

    <div class="card">


        <h2>

            👥 Member Contributions

        </h2>


        <div class="member-summary">


            <?php foreach (
                $members
                as $member
            ): ?>


                <div class="member-box">


                    <div class="member-name">

                        <?= htmlspecialchars(
                            $member["name"]
                        ) ?>

                    </div>


                    <div>

                        <?= htmlspecialchars(
                            $member["role"]
                        ) ?>

                    </div>


                    <div class="member-money">

                        ৳<?= number_format(
                            $member_totals[
                                $member["id"]
                            ] ?? 0,
                            2
                        ) ?>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    </div>


    <!-- =================================================
         PAYMENT HISTORY
    ================================================= -->

    <div class="card">


        <h2>

            📋 Payment History

        </h2>


        <div class="table-container">


            <table>


                <thead>

                    <tr>


                        <th>
                            Member
                        </th>


                        <th>
                            Amount
                        </th>


                        <th>
                            Date
                        </th>


                        <th>
                            Note
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


                <?php if (
                    count($payments) > 0
                ): ?>


                    <?php foreach (
                        $payments
                        as $payment
                    ): ?>


                        <tr>


                            <!-- MEMBER -->

                            <td>

                                <?= htmlspecialchars(
                                    $payment["member_name"]
                                ) ?>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                ৳<?= number_format(
                                    $payment["amount"],
                                    2
                                ) ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= date(
                                    "d M Y",
                                    strtotime(
                                        $payment["payment_date"]
                                    )
                                ) ?>

                            </td>


                            <!-- NOTE -->

                            <td>

                                <?= htmlspecialchars(
                                    $payment["note"] ?? "-"
                                ) ?>

                            </td>


                            <!-- ADDED BY -->

                            <td>

                                <?= htmlspecialchars(
                                    $payment["added_by_name"]
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

                                                Edit

                                            </summary>


                                            <form
                                                method="POST"
                                                class="edit-form"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="payment_id"
                                                    value="<?= $payment["id"] ?>"
                                                >


                                                <!-- MEMBER -->

                                                <select
                                                    name="payment_user_id"
                                                    required
                                                >


                                                    <?php foreach (
                                                        $members
                                                        as $member
                                                    ): ?>


                                                        <option
                                                            value="<?= $member["id"] ?>"

                                                            <?= (
                                                                $member["id"]
                                                                ==
                                                                $payment["user_id"]
                                                            )
                                                                ? "selected"
                                                                : ""
                                                            ?>
                                                        >

                                                            <?= htmlspecialchars(
                                                                $member["name"]
                                                            ) ?>

                                                        </option>


                                                    <?php endforeach; ?>


                                                </select>


                                                <!-- AMOUNT -->

                                                <input
                                                    type="number"
                                                    name="amount"
                                                    step="0.01"
                                                    min="0.01"
                                                    value="<?= htmlspecialchars(
                                                        $payment["amount"]
                                                    ) ?>"
                                                    required
                                                >


                                                <!-- DATE -->

                                                <input
                                                    type="date"
                                                    name="payment_date"
                                                    value="<?= htmlspecialchars(
                                                        $payment["payment_date"]
                                                    ) ?>"
                                                    required
                                                >


                                                <!-- NOTE -->

                                                <input
                                                    type="text"
                                                    name="note"
                                                    value="<?= htmlspecialchars(
                                                        $payment["note"] ?? ""
                                                    ) ?>"
                                                    placeholder="Note"
                                                >


                                                <!-- SAVE -->

                                                <button
                                                    type="submit"
                                                    name="edit_payment"
                                                    class="action-btn edit-btn"
                                                >

                                                    Save

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
                                                    'Delete this payment?'
                                                );
                                            "
                                        >


                                            <input
                                                type="hidden"
                                                name="payment_id"
                                                value="<?= $payment["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="delete_payment"
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
                            colspan="<?= (
                                $can_edit ||
                                $can_delete
                            )
                                ? 6
                                : 5
                            ?>"
                            style="
                                text-align:center;
                                padding:30px;
                            "
                        >

                            No payments added yet.

                        </td>


                    </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


        <!-- =================================================
             TOTAL GIVEN MONEY
        ================================================= -->

        <div class="total-box">


            <div class="total-label">

                Total Given Money

            </div>


            <div class="total-amount">

                ৳<?= number_format(
                    $total_given,
                    2
                ) ?>

            </div>


        </div>


    </div>


</div>


</body>

</html>