<?php
session_start();

require_once "../config/database.php";


/* =================================================
   CHECK LOGIN
================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit;
}


/* =================================================
   CHECK MEAL ID
================================================= */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    die("Invalid meal group.");
}


$meal_id = (int) $_GET["id"];
$user_id = $_SESSION["user_id"];


/* =================================================
   GET MEAL + CURRENT USER ROLE
================================================= */

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
    $meal_id,
    $user_id
]);

$meal = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$meal) {

    die("You are not a member of this meal.");
}


$current_role = $meal["role"];


/*
    Manager + Junior Manager
    can create/edit announcements.

    Manager only
    can delete announcements.
*/

$can_announcement_edit =
    ($current_role === "manager" ||
     $current_role === "junior_manager");

$can_announcement_delete =
    ($current_role === "manager");


/* =================================================
   ADD ANNOUNCEMENT
================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_announcement"])
) {

    if (!$can_announcement_edit) {

        die("You do not have permission to add announcements.");
    }


    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");


    if ($title === "" || $message === "") {

        die("Please fill in the announcement title and message.");
    }


    $stmt = $pdo->prepare("
        INSERT INTO announcements
        (
            meal_group_id,
            title,
            message,
            created_by
        )
        VALUES (?, ?, ?, ?)
    ");


    $stmt->execute([
        $meal_id,
        $title,
        $message,
        $user_id
    ]);


    header("Location: meal.php?id=" . $meal_id);
    exit;
}


/* =================================================
   EDIT ANNOUNCEMENT
================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["edit_announcement"])
) {

    if (!$can_announcement_edit) {

        die("You do not have permission to edit announcements.");
    }


    $announcement_id =
        (int)($_POST["announcement_id"] ?? 0);

    $title =
        trim($_POST["title"] ?? "");

    $message =
        trim($_POST["message"] ?? "");


    if (
        $announcement_id <= 0 ||
        $title === "" ||
        $message === ""
    ) {

        die("Please fill in all announcement fields.");
    }


    /*
        Make sure this announcement
        belongs to this meal group.
    */

    $stmt = $pdo->prepare("
        UPDATE announcements

        SET
            title = ?,
            message = ?

        WHERE id = ?
          AND meal_group_id = ?
    ");


    $stmt->execute([
        $title,
        $message,
        $announcement_id,
        $meal_id
    ]);


    header("Location: meal.php?id=" . $meal_id);
    exit;
}


/* =================================================
   DELETE ANNOUNCEMENT
================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_announcement"])
) {

    if (!$can_announcement_delete) {

        die("Only the Manager can delete announcements.");
    }


    $announcement_id =
        (int)($_POST["announcement_id"] ?? 0);


    $stmt = $pdo->prepare("
        DELETE FROM announcements

        WHERE id = ?
          AND meal_group_id = ?
    ");


    $stmt->execute([
        $announcement_id,
        $meal_id
    ]);


    header("Location: meal.php?id=" . $meal_id);
    exit;
}


/* =================================================
   GET ALL MEMBERS
================================================= */

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

            WHEN mm.role = 'manager'
                THEN 1

            WHEN mm.role = 'junior_manager'
                THEN 2

            ELSE 3

        END,

        u.name
");

$stmt->execute([
    $meal_id
]);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =================================================
   GET ANNOUNCEMENTS
================================================= */

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.title,
        a.message,
        a.created_by,
        a.created_at,
        u.name AS creator_name

    FROM announcements a

    INNER JOIN users u
        ON a.created_by = u.id

    WHERE a.meal_group_id = ?

    ORDER BY a.created_at DESC, a.id DESC
");

$stmt->execute([
    $meal_id
]);

$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <?php echo htmlspecialchars($meal["name"]); ?>

    - Meal System

</title>


<style>

/* =================================================
   RESET
================================================= */

* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;
}


/* =================================================
   BODY
================================================= */

body {

    font-family: Arial, sans-serif;

    background: #f5f6fa;

    color: #222;
}


/* =================================================
   MAIN LAYOUT
================================================= */

.app-container {

    display: flex;

    min-height: 100vh;
}


/* =================================================
   SIDEBAR
================================================= */

.sidebar {

    width: 250px;

    background: #ffffff;

    border-right: 1px solid #e5e5e5;

    padding: 25px 15px;

    position: fixed;

    top: 0;

    left: 0;

    bottom: 0;

    display: flex;

    flex-direction: column;
}


.logo {

    font-size: 23px;

    font-weight: bold;

    color: #6c4ce8;

    padding: 0 15px 30px;
}


.sidebar-title {

    font-size: 12px;

    color: #999;

    text-transform: uppercase;

    padding: 0 15px 10px;

    letter-spacing: 1px;
}


.nav-menu {

    display: flex;

    flex-direction: column;

    gap: 6px;
}


.nav-button {

    text-decoration: none;

    color: #444;

    padding: 13px 15px;

    border-radius: 10px;

    font-size: 15px;

    display: block;

    transition: 0.2s;
}


.nav-button:hover {

    background: #f1edff;

    color: #6c4ce8;
}


.nav-button.active {

    background: #6c4ce8;

    color: white;
}


.sidebar-bottom {

    margin-top: auto;

    display: flex;

    flex-direction: column;

    gap: 6px;
}


.logout-button {

    color: #d33;
}


.logout-button:hover {

    background: #fff0f0;

    color: #c22;
}


/* =================================================
   MAIN CONTENT
================================================= */

.main-content {

    margin-left: 250px;

    width: calc(100% - 250px);

    padding: 35px;
}


/* =================================================
   TOP BAR
================================================= */

.top-bar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}


.top-bar h1 {

    font-size: 28px;
}


.user-badge {

    background: white;

    padding: 10px 15px;

    border-radius: 10px;

    border: 1px solid #e5e5e5;

    font-size: 14px;
}


/* =================================================
   MEAL INFO
================================================= */

.meal-info {

    background: white;

    border-radius: 15px;

    padding: 25px;

    margin-bottom: 25px;

    border: 1px solid #e5e5e5;
}


.meal-info h2 {

    margin-bottom: 15px;

    font-size: 24px;
}


.info-grid {

    display: flex;

    flex-wrap: wrap;

    gap: 25px;
}


.info-item {

    font-size: 14px;

    color: #666;
}


.info-item strong {

    color: #222;
}


.join-code {

    color: #6c4ce8;

    font-weight: bold;
}


/* =================================================
   ANNOUNCEMENTS
================================================= */

.announcement-card {

    background: white;

    border-radius: 15px;

    padding: 25px;

    border: 1px solid #e5e5e5;

    margin-bottom: 25px;
}


.section-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}


.section-header h2 {

    font-size: 21px;
}


.add-button {

    background: #6c4ce8;

    color: white;

    border: none;

    text-decoration: none;

    padding: 9px 15px;

    border-radius: 8px;

    font-size: 14px;

    cursor: pointer;
}


.add-button:hover {

    background: #5938d0;
}


/* =================================================
   ANNOUNCEMENT FORM
================================================= */

.announcement-form {

    background: #faf9ff;

    border: 1px solid #e7e2ff;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 20px;
}


.form-group {

    margin-bottom: 15px;
}


.form-group label {

    display: block;

    font-size: 13px;

    color: #666;

    margin-bottom: 6px;
}


.form-group input,
.form-group textarea {

    width: 100%;

    padding: 11px;

    border: 1px solid #ddd;

    border-radius: 8px;

    font-family: Arial, sans-serif;

    font-size: 14px;
}


.form-group textarea {

    min-height: 100px;

    resize: vertical;
}


.form-actions {

    display: flex;

    gap: 8px;
}


.save-button {

    background: #6c4ce8;

    color: white;

    border: none;

    padding: 10px 18px;

    border-radius: 8px;

    cursor: pointer;
}


.cancel-button {

    background: #eee;

    color: #444;

    border: none;

    padding: 10px 18px;

    border-radius: 8px;

    cursor: pointer;
}


/* =================================================
   ANNOUNCEMENT ITEM
================================================= */

.announcement {

    border: 1px solid #eee;

    border-radius: 10px;

    padding: 18px;

    margin-bottom: 12px;

    background: #fff;
}


.announcement:last-child {

    margin-bottom: 0;
}


.announcement-title {

    font-weight: bold;

    font-size: 17px;

    margin-bottom: 8px;
}


.announcement-message {

    white-space: pre-wrap;

    line-height: 1.6;

    color: #444;

    margin-bottom: 12px;
}


.announcement-meta {

    font-size: 13px;

    color: #888;
}


.announcement-actions {

    margin-top: 12px;

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


.edit-button {

    background: #ede9fe;

    color: #6c4ce8;

    border: none;

    padding: 7px 12px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 12px;
}


.delete-button {

    background: #fee2e2;

    color: #dc2626;

    border: none;

    padding: 7px 12px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 12px;
}


/* =================================================
   EMPTY ANNOUNCEMENT
================================================= */

.no-announcement {

    border: 1px solid #eee;

    border-radius: 10px;

    padding: 20px;

    text-align: center;

    color: #888;
}


/* =================================================
   MEMBER SECTION
================================================= */

.members-card {

    background: white;

    border-radius: 15px;

    padding: 25px;

    border: 1px solid #e5e5e5;
}


.members-table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 15px;
}


th,
td {

    text-align: left;

    padding: 13px;

    border-bottom: 1px solid #eee;
}


th {

    font-size: 13px;

    color: #666;
}


td {

    font-size: 14px;
}


.role-manager {

    color: #6c4ce8;

    font-weight: bold;
}


.role-junior {

    color: #2878d7;

    font-weight: bold;
}


.role-member {

    color: #555;
}


.action-link {

    font-size: 13px;

    line-height: 2;
}


.back-dashboard {

    display: inline-block;

    margin-top: 25px;

    text-decoration: none;

    color: #6c4ce8;
}


/* =================================================
   MOBILE
================================================= */

@media (max-width: 768px) {

    .sidebar {

        position: static;

        width: 100%;

        height: auto;

        border-right: none;

        border-bottom: 1px solid #e5e5e5;
    }


    .app-container {

        display: block;
    }


    .main-content {

        margin-left: 0;

        width: 100%;

        padding: 20px;
    }


    .nav-menu {

        display: grid;

        grid-template-columns: 1fr 1fr;
    }


    .sidebar-bottom {

        margin-top: 20px;
    }


    .top-bar {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }


    .info-grid {

        flex-direction: column;

        gap: 10px;
    }


    .section-header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }

}

</style>

</head>


<body>


<div class="app-container">


<!-- =================================================
     SIDEBAR
================================================= -->

<aside class="sidebar">


    <div>

        <div class="logo">

            🍚 Meal System

        </div>


        <div class="sidebar-title">

            Meal Menu

        </div>


        <nav class="nav-menu">


            <a
                href="meal.php?id=<?php echo $meal_id; ?>"
                class="nav-button active"
            >
                🏠 Overview
            </a>


            <a
                href="daily_meals.php?id=<?php echo $meal_id; ?>"
                class="nav-button"
            >
                🍚 Daily Meals
            </a>


            <a
                href="market.php?id=<?php echo $meal_id; ?>"
                class="nav-button"
            >
                🛒 Market / Bazar
            </a>


            <a
                href="payments.php?id=<?php echo $meal_id; ?>"
                class="nav-button"
            >
                💰 Given Money
            </a>


            <a
                href="calculation.php?id=<?php echo $meal_id; ?>"
                class="nav-button"
            >
                🧮 Calculation
            </a>


        </nav>

    </div>


    <div class="sidebar-bottom">


        <a
            href="#members"
            class="nav-button"
        >
            👥 Members
        </a>


        <a
            href="index.php"
            class="nav-button"
        >
            🏠 Dashboard
        </a>


        <a
            href="../logout.php"
            class="nav-button logout-button"
        >
            🚪 Logout
        </a>


    </div>


</aside>


<!-- =================================================
     MAIN CONTENT
================================================= -->

<main class="main-content">


    <!-- TOP BAR -->

    <div class="top-bar">


        <h1>

            <?php echo htmlspecialchars($meal["name"]); ?>

        </h1>


        <div class="user-badge">

            👤

            <?php echo htmlspecialchars(
                $_SESSION["user_name"]
            ); ?>

        </div>


    </div>


    <!-- =================================================
         MEAL INFORMATION
    ================================================= -->

    <section class="meal-info">


        <h2>

            <?php echo htmlspecialchars(
                $meal["name"]
            ); ?>

        </h2>


        <div class="info-grid">


            <div class="info-item">

                <strong>Period:</strong>

                <?php echo htmlspecialchars(
                    $meal["month_name"]
                ); ?>

                <?php echo htmlspecialchars(
                    $meal["year"]
                ); ?>

            </div>


            <div class="info-item">

                <strong>Your Role:</strong>

                <?php echo htmlspecialchars(
                    $meal["role"]
                ); ?>

            </div>


            <div class="info-item">

                <strong>Join Code:</strong>

                <span class="join-code">

                    <?php echo htmlspecialchars(
                        $meal["join_code"]
                    ); ?>

                </span>

            </div>


        </div>


    </section>


    <!-- =================================================
         ANNOUNCEMENTS
    ================================================= -->

    <section class="announcement-card">


        <div class="section-header">


            <h2>

                📢 Announcements

            </h2>


            <?php if ($can_announcement_edit): ?>


                <button
                    type="button"
                    class="add-button"
                    onclick="showAnnouncementForm()"
                >

                    + Add Announcement

                </button>


            <?php endif; ?>


        </div>


        <!-- ADD FORM -->

        <?php if ($can_announcement_edit): ?>


        <div
            id="announcementForm"
            class="announcement-form"
            style="display:none;"
        >


            <h3 style="margin-bottom:15px;">

                Create Announcement

            </h3>


            <form method="POST">


                <div class="form-group">

                    <label>

                        Title

                    </label>


                    <input
                        type="text"
                        name="title"
                        placeholder="Example: Market payment reminder"
                        maxlength="255"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>

                        Message

                    </label>


                    <textarea
                        name="message"
                        placeholder="Write your announcement..."
                        required
                    ></textarea>

                </div>


                <div class="form-actions">


                    <button
                        type="submit"
                        name="add_announcement"
                        class="save-button"
                    >

                        Publish Announcement

                    </button>


                    <button
                        type="button"
                        class="cancel-button"
                        onclick="hideAnnouncementForm()"
                    >

                        Cancel

                    </button>


                </div>


            </form>


        </div>


        <?php endif; ?>


        <!-- ANNOUNCEMENT LIST -->

        <?php if (count($announcements) > 0): ?>


            <?php foreach ($announcements as $announcement): ?>


            <div class="announcement">


                <div class="announcement-title">

                    📢

                    <?php echo htmlspecialchars(
                        $announcement["title"]
                    ); ?>

                </div>


                <div class="announcement-message">

                    <?php echo htmlspecialchars(
                        $announcement["message"]
                    ); ?>

                </div>


                <div class="announcement-meta">

                    Posted by

                    <strong>

                        <?php echo htmlspecialchars(
                            $announcement["creator_name"]
                        ); ?>

                    </strong>


                    •


                    <?php echo date(
                        "d M Y, h:i A",
                        strtotime(
                            $announcement["created_at"]
                        )
                    ); ?>

                </div>


                <!-- ACTIONS -->

                <?php if ($can_announcement_edit): ?>


                <div class="announcement-actions">


                    <!-- EDIT -->

                    <details>

                        <summary class="edit-button">

                            ✏️ Edit

                        </summary>


                        <form
                            method="POST"
                            style="
                                margin-top:12px;
                                background:#fafafa;
                                padding:15px;
                                border-radius:10px;
                            "
                        >


                            <input
                                type="hidden"
                                name="announcement_id"
                                value="<?php echo $announcement["id"]; ?>"
                            >


                            <div class="form-group">

                                <label>

                                    Title

                                </label>


                                <input
                                    type="text"
                                    name="title"
                                    value="<?php echo htmlspecialchars(
                                        $announcement["title"]
                                    ); ?>"
                                    maxlength="255"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>

                                    Message

                                </label>


                                <textarea
                                    name="message"
                                    required
                                ><?php echo htmlspecialchars(
                                    $announcement["message"]
                                ); ?></textarea>

                            </div>


                            <button
                                type="submit"
                                name="edit_announcement"
                                class="save-button"
                            >

                                Save Changes

                            </button>


                        </form>


                    </details>


                    <!-- DELETE -->

                    <?php if ($can_announcement_delete): ?>


                    <form
                        method="POST"
                        style="display:inline;"
                        onsubmit="
                            return confirm(
                                'Delete this announcement?'
                            );
                        "
                    >


                        <input
                            type="hidden"
                            name="announcement_id"
                            value="<?php echo $announcement["id"]; ?>"
                        >


                        <button
                            type="submit"
                            name="delete_announcement"
                            class="delete-button"
                        >

                            🗑️ Delete

                        </button>


                    </form>


                    <?php endif; ?>


                </div>


                <?php endif; ?>


            </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="no-announcement">

                🔔 No announcements yet.

                <br>

                Announcements from the Manager or
                Junior Manager will appear here.

            </div>


        <?php endif; ?>


    </section>


    <!-- =================================================
         MEMBERS
    ================================================= -->

    <section
        class="members-card"
        id="members"
    >


        <div class="section-header">


            <h2>

                👥 Members

            </h2>


        </div>


        <?php if (count($members) > 0): ?>


            <div class="members-table-wrapper">


                <table>


                    <tr>

                        <th>
                            Name
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>


                    <?php foreach ($members as $member): ?>


                    <tr>


                        <td>

                            <?php echo htmlspecialchars(
                                $member["name"]
                            ); ?>

                        </td>


                        <td>

                            <?php echo htmlspecialchars(
                                $member["email"]
                            ); ?>

                        </td>


                        <td>


                            <?php if (
                                $member["role"]
                                === "manager"
                            ): ?>


                                <span class="role-manager">

                                    Manager

                                </span>


                            <?php elseif (
                                $member["role"]
                                === "junior_manager"
                            ): ?>


                                <span class="role-junior">

                                    Junior Manager

                                </span>


                            <?php else: ?>


                                <span class="role-member">

                                    Member

                                </span>


                            <?php endif; ?>


                        </td>


                        <td>


                            <?php

                            /*
                                Manager controls.
                            */

                            if (
                                $meal["role"]
                                === "manager"
                            ):

                            ?>


                                <?php if (
                                    $member["role"]
                                    === "member"
                                ): ?>


                                    <a
                                        class="action-link"
                                        href="../meal/promote_member.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                        onclick="
                                            return confirm(
                                                'Promote this member to Junior Manager?'
                                            );
                                        "
                                    >

                                        Promote to Junior Manager

                                    </a>


                                    <br>


                                    <a
                                        class="action-link"
                                        href="../meal/remove_member.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                        onclick="
                                            return confirm(
                                                'Are you sure you want to remove this member?'
                                            );
                                        "
                                    >

                                        Remove

                                    </a>


                                <?php elseif (
                                    $member["role"]
                                    === "junior_manager"
                                ): ?>


                                    <a
                                        class="action-link"
                                        href="../meal/remove_junior_manager.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                        onclick="
                                            return confirm(
                                                'Remove Junior Manager role?'
                                            );
                                        "
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


            </div>


        <?php else: ?>


            <p>

                No members found.

            </p>


        <?php endif; ?>


    </section>


    <a
        href="index.php"
        class="back-dashboard"
    >

        ← Back to Dashboard

    </a>


</main>


</div>


<script>

/* =================================================
   ANNOUNCEMENT FORM
================================================= */

function showAnnouncementForm() {

    document.getElementById(
        "announcementForm"
    ).style.display = "block";

}


function hideAnnouncementForm() {

    document.getElementById(
        "announcementForm"
    ).style.display = "none";

}

</script>


</body>

</html>