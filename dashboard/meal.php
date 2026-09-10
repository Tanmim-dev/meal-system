<?php

session_start();
require_once "../config/database.php";

// --------------------------------------------------
// CHECK LOGIN
// --------------------------------------------------

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

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
// PERMISSIONS
// --------------------------------------------------

// Manager + Junior Manager can add/edit announcements
$can_edit_announcement =
    ($current_role === "manager" ||
     $current_role === "junior_manager");

// Manager only can delete announcements
$can_delete_announcement =
    ($current_role === "manager");


// --------------------------------------------------
// ADD ANNOUNCEMENT
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_announcement"])
) {

    if (!$can_edit_announcement) {
        die("You do not have permission to add announcements.");
    }

    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($title === "" || $message === "") {
        die("Please fill in all announcement fields.");
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


// --------------------------------------------------
// EDIT ANNOUNCEMENT
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["edit_announcement"])
) {

    if (!$can_edit_announcement) {
        die("You do not have permission to edit announcements.");
    }

    $announcement_id =
        (int) ($_POST["announcement_id"] ?? 0);

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


// --------------------------------------------------
// DELETE ANNOUNCEMENT
// --------------------------------------------------

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_announcement"])
) {

    if (!$can_delete_announcement) {
        die("Only the Manager can delete announcements.");
    }

    $announcement_id =
        (int) ($_POST["announcement_id"] ?? 0);

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


// --------------------------------------------------
// GET ALL MEMBERS
// --------------------------------------------------

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
// GET ANNOUNCEMENTS
// --------------------------------------------------

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
    ORDER BY
        a.created_at DESC,
        a.id DESC
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
        Overview - <?php echo htmlspecialchars($meal["name"]); ?>
    </title>

    <!-- SAME GLOBAL DESIGN AS MARKET + PAYMENTS -->
    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>


<body>


<!-- ==================================================
     SIDEBAR
================================================== -->

<div class="sidebar">


    <!-- LOGO -->

    <div class="logo">

        🍚 Meal System

    </div>


    <!-- GROUP NAME -->

    <div class="nav-title">

        <?php echo htmlspecialchars($meal["name"]); ?>

    </div>


    <!-- OVERVIEW -->

    <a
        href="meal.php?id=<?php echo $meal_id; ?>"
        class="nav-link active"
    >

        🏠 Overview

    </a>


    <!-- DAILY MEALS -->

    <a
        href="daily_meals.php?id=<?php echo $meal_id; ?>"
        class="nav-link"
    >

        🍽️ Daily Meals

    </a>


    <!-- MARKET -->

    <a
        href="market.php?id=<?php echo $meal_id; ?>"
        class="nav-link"
    >

        🛒 Market / Bazar

    </a>


    <!-- GIVEN MONEY -->

    <a
        href="payments.php?id=<?php echo $meal_id; ?>"
        class="nav-link"
    >

        💰 Given Money

    </a>


    <!-- CALCULATION -->

    <a
        href="calculation.php?id=<?php echo $meal_id; ?>"
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
        href="#members"
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



<!-- ==================================================
     MAIN
================================================== -->

<div class="main">


    <!-- ==================================================
         TOPBAR
    ================================================== -->

    <div class="topbar">


        <div>

            <h1>

                🏠 Overview

            </h1>

        </div>


        <div class="user">

            <?php echo htmlspecialchars(
                $_SESSION["user_name"]
            ); ?>

            <span>
                (
                <?php echo ucfirst(
                    str_replace(
                        "_",
                        " ",
                        $current_role
                    )
                ); ?>
                )
            </span>

        </div>


    </div>



    <!-- ==================================================
         GROUP INFORMATION
    ================================================== -->

    <div class="card">


        <h2>

            <?php echo htmlspecialchars(
                $meal["name"]
            ); ?>

        </h2>


        <div class="group-info">


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
                    Join Code
                </strong>

                <br>

                <strong
                    style="color:#7c3aed;"
                >

                    <?php echo htmlspecialchars(
                        $meal["join_code"]
                    ); ?>

                </strong>

            </div>


            <div>

                <strong>
                    Your Role
                </strong>

                <br>

                <?php echo ucfirst(
                    str_replace(
                        "_",
                        " ",
                        $current_role
                    )
                ); ?>

            </div>


            <div>

                <strong>
                    Members
                </strong>

                <br>

                <?php echo count($members); ?>

            </div>


        </div>


    </div>



    <!-- ==================================================
         ANNOUNCEMENTS
    ================================================== -->

    <div class="card">


        <div class="topbar">


            <div>

                <h2>

                    📢 Announcements

                </h2>

                <p
                    style="
                        margin:5px 0 0;
                        color:#777;
                    "
                >

                    Important messages for this meal group.

                </p>

            </div>


            <?php if ($can_edit_announcement): ?>

                <button
                    type="button"
                    class="add-btn"
                    onclick="toggleAnnouncementForm()"
                >

                    + Add Announcement

                </button>

            <?php endif; ?>


        </div>



        <!-- ==================================================
             ADD ANNOUNCEMENT FORM
        ================================================== -->

        <?php if ($can_edit_announcement): ?>


            <div
                id="announcementForm"
                style="display:none;"
            >


                <form
                    method="POST"
                    class="card"
                    style="
                        background:#fafafa;
                        margin-top:20px;
                    "
                >


                    <h3>

                        Create Announcement

                    </h3>


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Title
                            </label>

                            <input
                                type="text"
                                name="title"
                                placeholder="Announcement title"
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
                                rows="4"
                                placeholder="Write your announcement..."
                                required
                            ></textarea>

                        </div>


                    </div>


                    <button
                        type="submit"
                        name="add_announcement"
                        class="add-btn"
                    >

                        📢 Publish Announcement

                    </button>


                </form>


            </div>


        <?php endif; ?>



        <!-- ==================================================
             ANNOUNCEMENT LIST
        ================================================== -->

        <?php if (count($announcements) > 0): ?>


            <?php foreach ($announcements as $announcement): ?>


                <div
                    class="card"
                    style="
                        margin-top:15px;
                        border:1px solid #eee;
                    "
                >


                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:flex-start;
                            gap:15px;
                        "
                    >


                        <div style="flex:1;">


                            <h3
                                style="
                                    margin:0 0 10px;
                                "
                            >

                                📢

                                <?php echo htmlspecialchars(
                                    $announcement["title"]
                                ); ?>

                            </h3>


                            <div
                                style="
                                    color:#444;
                                    line-height:1.6;
                                    white-space:pre-wrap;
                                "
                            >

                                <?php echo htmlspecialchars(
                                    $announcement["message"]
                                ); ?>

                            </div>


                            <div
                                style="
                                    color:#888;
                                    font-size:13px;
                                    margin-top:12px;
                                "
                            >

                                Posted by

                                <strong>

                                    <?php echo htmlspecialchars(
                                        $announcement["creator_name"]
                                    ); ?>

                                </strong>

                                ·

                                <?php echo date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $announcement["created_at"]
                                    )
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- ANNOUNCEMENT ACTIONS -->

                    <?php if ($can_edit_announcement): ?>


                        <div
                            style="
                                display:flex;
                                gap:8px;
                                margin-top:15px;
                                flex-wrap:wrap;
                            "
                        >


                            <!-- EDIT -->

                            <details>


                                <summary
                                    class="action-btn edit-btn"
                                    style="cursor:pointer;"
                                >

                                    ✏️ Edit

                                </summary>


                                <form
                                    method="POST"
                                    style="
                                        margin-top:15px;
                                        padding:15px;
                                        background:#fafafa;
                                        border-radius:10px;
                                    "
                                >


                                    <input
                                        type="hidden"
                                        name="announcement_id"
                                        value="<?php echo $announcement["id"]; ?>"
                                    >


                                    <div class="form-grid">


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
                                                rows="4"
                                                required
                                            ><?php echo htmlspecialchars(
                                                $announcement["message"]
                                            ); ?></textarea>

                                        </div>


                                    </div>


                                    <button
                                        type="submit"
                                        name="edit_announcement"
                                        class="action-btn edit-btn"
                                    >

                                        💾 Save Changes

                                    </button>


                                </form>


                            </details>



                            <!-- DELETE -->

                            <?php if ($can_delete_announcement): ?>


                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to delete this announcement?'
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
                                        class="action-btn delete-btn"
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


            <div
                style="
                    text-align:center;
                    padding:30px 15px;
                    color:#888;
                "
            >

                📭 No announcements yet.

            </div>


        <?php endif; ?>


    </div>



    <!-- ==================================================
         MEMBERS
    ================================================== -->

    <div
        class="card"
        id="members"
    >


        <div class="topbar">


            <div>

                <h2>

                    👥 Members

                </h2>

                <p
                    style="
                        margin:5px 0 0;
                        color:#777;
                    "
                >

                    Members currently in this meal group.

                </p>

            </div>


        </div>



        <div class="table-container">


            <table class="table">


                <thead>

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

                        <?php if ($current_role === "manager"): ?>

                            <th>
                                Actions
                            </th>

                        <?php endif; ?>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach ($members as $member): ?>


                        <tr>


                            <td>

                                <strong>

                                    <?php echo htmlspecialchars(
                                        $member["name"]
                                    ); ?>

                                </strong>

                            </td>


                            <td>

                                <?php echo htmlspecialchars(
                                    $member["email"]
                                ); ?>

                            </td>


                            <td>


                                <?php if (
                                    $member["role"] === "manager"
                                ): ?>


                                    <span
                                        style="
                                            color:#7c3aed;
                                            font-weight:bold;
                                        "
                                    >

                                        Manager

                                    </span>


                                <?php elseif (
                                    $member["role"] === "junior_manager"
                                ): ?>


                                    <span
                                        style="
                                            color:#2563eb;
                                            font-weight:bold;
                                        "
                                    >

                                        Junior Manager

                                    </span>


                                <?php else: ?>


                                    <span>

                                        Member

                                    </span>


                                <?php endif; ?>


                            </td>



                            <?php if ($current_role === "manager"): ?>


                                <td>


                                    <?php if (
                                        $member["role"] === "member"
                                    ): ?>


                                        <a
                                            href="../meal/promote_member.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                            class="action-btn edit-btn"
                                            onclick="
                                                return confirm(
                                                    'Promote this member to Junior Manager?'
                                                );
                                            "
                                        >

                                            Promote

                                        </a>


                                        <a
                                            href="../meal/remove_member.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                            class="action-btn delete-btn"
                                            onclick="
                                                return confirm(
                                                    'Are you sure you want to remove this member?'
                                                );
                                            "
                                        >

                                            Remove

                                        </a>


                                    <?php elseif (
                                        $member["role"] === "junior_manager"
                                    ): ?>


                                        <a
                                            href="../meal/remove_junior_manager.php?meal_id=<?php echo $meal_id; ?>&user_id=<?php echo $member["id"]; ?>"
                                            class="action-btn delete-btn"
                                            onclick="
                                                return confirm(
                                                    'Remove Junior Manager role from this member?'
                                                );
                                            "
                                        >

                                            Remove Junior Manager

                                        </a>


                                    <?php else: ?>


                                        -

                                    <?php endif; ?>


                                </td>


                            <?php endif; ?>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        </div>


    </div>



    <!-- ==================================================
         BACK
    ================================================== -->

    <a
        href="index.php"
        style="
            display:inline-block;
            margin-top:20px;
            color:#7c3aed;
            text-decoration:none;
            font-weight:600;
        "
    >

        ← Back to My Groups

    </a>


</div>



<!-- ==================================================
     JAVASCRIPT
================================================== -->

<script>

function toggleAnnouncementForm() {

    const form =
        document.getElementById("announcementForm");

    if (form.style.display === "none" ||
        form.style.display === "") {

        form.style.display = "block";

    } else {

        form.style.display = "none";

    }

}

</script>


</body>

</html>