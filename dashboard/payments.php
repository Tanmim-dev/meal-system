<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

require_login();

$user_id = $_SESSION["user_id"];

$group_id = isset($_GET["id"])
    ? (int) $_GET["id"]
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
   GET GROUP MEMBERS
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        mm.user_id,
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
        u.name ASC
");

$stmt->execute([
    $group_id
]);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* -------------------------------------------------
   ADD PAYMENT
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_payment"])
) {

    verify_csrf();

    if (!$can_edit) {
        die("You do not have permission to add payments.");
    }


    $payment_user_id =
        (int) ($_POST["user_id"] ?? 0);

    $amount =
        trim($_POST["amount"] ?? "");

    $payment_date =
        trim($_POST["payment_date"] ?? "");

    $note =
        trim($_POST["note"] ?? "");


    /* -------------------------------------------------
       VALIDATION
    ------------------------------------------------- */

    if (
        $payment_user_id <= 0 ||
        $amount === "" ||
        $payment_date === ""
    ) {
        die("Please fill in all required fields.");
    }


    if (
        !is_numeric($amount) ||
        (float) $amount < 0
    ) {
        die("Invalid payment amount.");
    }


    if ((float) $amount > 99999999.99) {
        die("Payment amount is too large.");
    }


    /* Check member belongs to this group */

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
        die("Invalid member selected.");
    }


    /* Validate date */

    $date_object = DateTime::createFromFormat(
        "Y-m-d",
        $payment_date
    );

    if (
        !$date_object ||
        $date_object->format("Y-m-d") !== $payment_date
    ) {
        die("Invalid payment date.");
    }


    /* -------------------------------------------------
       INSERT PAYMENT
    ------------------------------------------------- */

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
        $note,
        $user_id
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
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_payment"])
) {

    verify_csrf();

    if (!$can_delete) {
        die("You do not have permission to delete payments.");
    }


    $payment_id =
        (int) ($_POST["payment_id"] ?? 0);


    if ($payment_id <= 0) {
        die("Invalid payment.");
    }


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
   EDIT PAYMENT
------------------------------------------------- */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["edit_payment"])
) {

    verify_csrf();

    if (!$can_edit) {
        die("You do not have permission to edit payments.");
    }


    $payment_id =
        (int) ($_POST["payment_id"] ?? 0);

    $payment_user_id =
        (int) ($_POST["user_id"] ?? 0);

    $amount =
        trim($_POST["amount"] ?? "");

    $payment_date =
        trim($_POST["payment_date"] ?? "");

    $note =
        trim($_POST["note"] ?? "");


    /* -------------------------------------------------
       VALIDATION
    ------------------------------------------------- */

    if (
        $payment_id <= 0 ||
        $payment_user_id <= 0 ||
        $amount === "" ||
        $payment_date === ""
    ) {
        die("Please fill in all required fields.");
    }


    if (
        !is_numeric($amount) ||
        (float) $amount < 0
    ) {
        die("Invalid payment amount.");
    }


    if ((float) $amount > 99999999.99) {
        die("Payment amount is too large.");
    }


    /* Check selected member */

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
        die("Invalid member selected.");
    }


    /* Validate date */

    $date_object = DateTime::createFromFormat(
        "Y-m-d",
        $payment_date
    );

    if (
        !$date_object ||
        $date_object->format("Y-m-d") !== $payment_date
    ) {
        die("Invalid payment date.");
    }


    /* -------------------------------------------------
       UPDATE PAYMENT
    ------------------------------------------------- */

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
        $note,
        $payment_id,
        $group_id
    ]);


    header(
        "Location: payments.php?id=" . $group_id
    );

    exit;
}


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
        p.created_at,

        u.name AS member_name,

        added.name AS added_by_name

    FROM payments p

    INNER JOIN users u
        ON p.user_id = u.id

    LEFT JOIN users added
        ON p.added_by = added.id

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
   TOTAL GIVEN MONEY
------------------------------------------------- */

$total_given = 0;

foreach ($payments as $payment) {

    $total_given +=
        (float) $payment["amount"];
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

                💰 Given Money

            </h1>


            <div class="user">

                <?= htmlspecialchars(
                    $group["name"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        </div>


        <div class="user">

            <?= htmlspecialchars(
                $_SESSION["user_name"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            (

            <?= htmlspecialchars(
                $role,
                ENT_QUOTES,
                "UTF-8"
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
                    $group["month_name"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                <?= htmlspecialchars(
                    $group["year"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>


            <div>

                <strong>
                    Join Code:
                </strong>

                <?= htmlspecialchars(
                    $group["join_code"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>


        </div>


    </div>


    <!-- =================================================
         TOTAL GIVEN MONEY
    ================================================= -->

    <div class="card">


        <div class="total-box">


            <div class="total-label">

                💰 Total Given Money

            </div>


            <div class="total-amount">

                ৳<?= number_format(
                    $total_given,
                    2
                ) ?>

            </div>


        </div>


    </div>


    <!-- =================================================
         ADD PAYMENT
    ================================================= -->

    <?php if ($can_edit): ?>


        <div class="card">


            <h2>

                ➕ Add Given Money

            </h2>


            <form method="POST">


                <?= csrf_field() ?>


                <div class="form-grid">


                    <!-- MEMBER -->

                    <div class="form-group">


                        <label>

                            Member

                        </label>


                        <select
                            name="user_id"
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
                                    value="<?= (int) $member["user_id"] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $member["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                    <?php if (
                                        $member["role"] === "manager"
                                    ): ?>

                                        (Manager)

                                    <?php elseif (
                                        $member["role"] === "junior_manager"
                                    ): ?>

                                        (Junior Manager)

                                    <?php endif; ?>

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
                            min="0"
                            max="99999999.99"
                            placeholder="Example: 2000"
                            required
                        >


                    </div>


                    <!-- DATE -->

                    <div class="form-group">


                        <label>

                            Payment Date

                        </label>


                        <input
                            type="date"
                            name="payment_date"
                            value="<?= date("Y-m-d") ?>"
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
                            maxlength="255"
                            placeholder="Example: First payment"
                        >


                    </div>


                    <!-- BUTTON -->

                    <button
                        type="submit"
                        name="add_payment"
                        class="add-btn"
                    >

                        ➕ Add Payment

                    </button>


                </div>


            </form>


        </div>


    <?php endif; ?>


    <!-- =================================================
         PAYMENT HISTORY
    ================================================= -->

    <div class="card">


        <h2>

            📋 Given Money History

        </h2>


        <?php if (count($payments) > 0): ?>


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
                                Payment Date
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


                        <?php foreach (
                            $payments
                            as $payment
                        ): ?>


                            <tr>


                                <!-- MEMBER -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $payment["member_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- AMOUNT -->

                                <td>

                                    <strong>

                                        ৳<?= number_format(
                                            $payment["amount"],
                                            2
                                        ) ?>

                                    </strong>

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

                                    <?php if (
                                        $payment["note"] !== ""
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $payment["note"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    <?php else: ?>

                                        <span
                                            style="color:#999;"
                                        >

                                            —

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ADDED BY -->

                                <td>

                                    <?= htmlspecialchars(
                                        $payment["added_by_name"] ?? "Unknown",
                                        ENT_QUOTES,
                                        "UTF-8"
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


                                                    <?= csrf_field() ?>


                                                    <input
                                                        type="hidden"
                                                        name="payment_id"
                                                        value="<?= (int) $payment["id"] ?>"
                                                    >


                                                    <!-- MEMBER -->

                                                    <select
                                                        name="user_id"
                                                        required
                                                    >

                                                        <?php foreach (
                                                            $members
                                                            as $member
                                                        ): ?>

                                                            <option
                                                                value="<?= (int) $member["user_id"] ?>"
                                                                <?= (
                                                                    (int) $member["user_id"] ===
                                                                    (int) $payment["user_id"]
                                                                )
                                                                    ? "selected"
                                                                    : ""
                                                                ?>
                                                            >

                                                                <?= htmlspecialchars(
                                                                    $member["name"],
                                                                    ENT_QUOTES,
                                                                    "UTF-8"
                                                                ) ?>

                                                                <?php if (
                                                                    $member["role"] === "manager"
                                                                ): ?>

                                                                    (Manager)

                                                                <?php elseif (
                                                                    $member["role"] === "junior_manager"
                                                                ): ?>

                                                                    (Junior Manager)

                                                                <?php endif; ?>

                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>


                                                    <!-- AMOUNT -->

                                                    <input
                                                        type="number"
                                                        name="amount"
                                                        step="0.01"
                                                        min="0"
                                                        max="99999999.99"
                                                        value="<?= htmlspecialchars(
                                                            $payment["amount"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>"
                                                        required
                                                    >


                                                    <!-- DATE -->

                                                    <input
                                                        type="date"
                                                        name="payment_date"
                                                        value="<?= htmlspecialchars(
                                                            $payment["payment_date"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>"
                                                        required
                                                    >


                                                    <!-- NOTE -->

                                                    <input
                                                        type="text"
                                                        name="note"
                                                        maxlength="255"
                                                        value="<?= htmlspecialchars(
                                                            $payment["note"] ?? "",
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>"
                                                        placeholder="Note"
                                                    >


                                                    <!-- SAVE -->

                                                    <button
                                                        type="submit"
                                                        name="edit_payment"
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
                                                        'Delete this payment?'
                                                    );
                                                "
                                            >


                                                <?= csrf_field() ?>


                                                <input
                                                    type="hidden"
                                                    name="payment_id"
                                                    value="<?= (int) $payment["id"] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    name="delete_payment"
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

                    💰

                </div>


                <h3>

                    No Payments Yet

                </h3>


                <p>

                    No given money has been recorded
                    for this meal group.

                </p>


            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>