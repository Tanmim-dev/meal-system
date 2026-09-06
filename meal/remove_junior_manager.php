<?php

session_start();

require_once "../config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$current_user_id = $_SESSION["user_id"];


// Get meal ID and target user ID
$meal_id = $_GET["meal_id"] ?? null;
$target_user_id = $_GET["user_id"] ?? null;


// Validate IDs
if (
    !$meal_id ||
    !$target_user_id ||
    !is_numeric($meal_id) ||
    !is_numeric($target_user_id)
) {
    die("Invalid request.");
}

$meal_id = (int) $meal_id;
$target_user_id = (int) $target_user_id;


// Check current user's role
$stmt = $pdo->prepare("
    SELECT role
    FROM meal_members
    WHERE meal_group_id = ?
    AND user_id = ?
");

$stmt->execute([
    $meal_id,
    $current_user_id
]);

$current_member = $stmt->fetch(PDO::FETCH_ASSOC);


// Check current user membership
if (!$current_member) {
    die("You are not a member of this meal.");
}


// Only Manager can remove Junior Managers
if ($current_member["role"] !== "manager") {
    die("Access denied. Only the Manager can remove Junior Managers.");
}


// Check target member
$stmt = $pdo->prepare("
    SELECT role
    FROM meal_members
    WHERE meal_group_id = ?
    AND user_id = ?
");

$stmt->execute([
    $meal_id,
    $target_user_id
]);

$target_member = $stmt->fetch(PDO::FETCH_ASSOC);


// Target doesn't exist
if (!$target_member) {
    die("Member not found.");
}


// Target must be Junior Manager
if ($target_member["role"] !== "junior_manager") {
    die("This member is not a Junior Manager.");
}


// Remove Junior Manager role
// They become a normal Member again
$stmt = $pdo->prepare("
    UPDATE meal_members
    SET role = 'member'
    WHERE meal_group_id = ?
    AND user_id = ?
");

$stmt->execute([
    $meal_id,
    $target_user_id
]);


// Return to meal page
header("Location: ../dashboard/meal.php?id=" . $meal_id);
exit;

?>