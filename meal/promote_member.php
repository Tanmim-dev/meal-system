<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

require_login();

$current_user_id = $_SESSION["user_id"];


// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Invalid request method.");
}


// CSRF protection
verify_csrf();


// Get IDs
$meal_id = $_POST["meal_id"] ?? null;
$target_user_id = $_POST["user_id"] ?? null;


// Validate
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


// Check membership
if (!$current_member) {
    die("You are not a member of this meal.");
}


// Only Manager can promote
if ($current_member["role"] !== "manager") {
    die("Access denied. Only the Manager can promote members.");
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


// Only normal members can be promoted
if ($target_member["role"] !== "member") {
    die("Only normal members can be promoted.");
}


// Promote member
$stmt = $pdo->prepare("
    UPDATE meal_members
    SET role = 'junior_manager'
    WHERE meal_group_id = ?
    AND user_id = ?
    AND role = 'member'
");

$stmt->execute([
    $meal_id,
    $target_user_id
]);


// Return to meal page
header("Location: ../dashboard/meal.php?id=" . $meal_id);
exit;

?>