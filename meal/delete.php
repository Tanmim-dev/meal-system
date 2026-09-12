<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

require_login();

$user_id = $_SESSION["user_id"] ?? 0;

/*
==================================================
ONLY POST REQUEST ALLOWED
==================================================
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard/index.php");
    exit;
}

/*
==================================================
CSRF CHECK
==================================================
*/

verify_csrf();

/*
==================================================
GET DATA
==================================================
*/

$meal_id = filter_input(INPUT_POST, "meal_id", FILTER_VALIDATE_INT);

$confirm_name = trim($_POST["confirm_name"] ?? "");

if (!$meal_id || $confirm_name === "") {
    die("Invalid request.");
}

/*
==================================================
CHECK MANAGER + GET MEAL
==================================================
*/

$stmt = $pdo->prepare("
    SELECT
        mg.id,
        mg.name,
        mm.role
    FROM meal_groups mg
    INNER JOIN meal_members mm
        ON mm.meal_group_id = mg.id
    WHERE mg.id = ?
      AND mm.user_id = ?
    LIMIT 1
");

$stmt->execute([
    $meal_id,
    $user_id
]);

$meal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meal) {
    http_response_code(403);
    die("You are not a member of this meal group.");
}

/*
==================================================
MANAGER ONLY
==================================================
*/

if ($meal["role"] !== "manager") {
    http_response_code(403);
    die("Only the Manager can delete the full meal group.");
}

/*
==================================================
EXACT NAME CONFIRMATION
==================================================
*/

if ($confirm_name !== $meal["name"]) {
    die("Meal name confirmation failed. Nothing was deleted.");
}

/*
==================================================
DELETE EVERYTHING
==================================================
*/

try {

    $pdo->beginTransaction();

    /*
    ==============================================
    SAVE ACTIVITY LOG FIRST
    ==============================================
    */

    $log_action =
        "Deleted meal group: " . $meal["name"];

    $stmt = $pdo->prepare("
        INSERT INTO activity_logs
        (
            meal_group_id,
            user_id,
            action
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $meal_id,
        $user_id,
        $log_action
    ]);

    /*
    ==============================================
    DELETE DAILY MEALS
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM daily_meals
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE MARKET ITEMS
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM market_items
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE PAYMENTS
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM payments
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE EXPENSES
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM expenses
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE MEMBER EXPENSES
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM member_expenses
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE ANNOUNCEMENTS
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM announcements
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE ACTIVITY LOGS
    ==============================================
    
    We saved the deletion log above.
    But because activity_logs belongs to the meal
    group, it must also be deleted with the group.
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM activity_logs
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE MEMBERS
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM meal_members
        WHERE meal_group_id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    DELETE MEAL GROUP
    ==============================================
    */

    $stmt = $pdo->prepare("
        DELETE FROM meal_groups
        WHERE id = ?
    ");

    $stmt->execute([$meal_id]);

    /*
    ==============================================
    COMMIT
    ==============================================
    */

    $pdo->commit();

    /*
    ==============================================
    REDIRECT
    ==============================================
    */

    header("Location: ../dashboard/index.php?deleted=1");
    exit;

} catch (PDOException $e) {

    /*
    ==============================================
    ROLLBACK
    ==============================================
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    die("Unable to delete the meal group. Please try again.");
}