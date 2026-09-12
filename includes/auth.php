<?php

/*
==================================================
SECURE SESSION CONFIGURATION
==================================================
*/

if (session_status() === PHP_SESSION_NONE) {

    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_strict_mode", "1");
    ini_set("session.cookie_httponly", "1");
    ini_set("session.cookie_samesite", "Lax");

    /*
    Secure cookies only when using HTTPS.
    XAMPP localhost normally uses HTTP.
    */

    if (
        !empty($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    ) {
        ini_set("session.cookie_secure", "1");
    }

    session_start();
}


/*
==================================================
LOGIN PROTECTION
==================================================
*/

function require_login()
{
    if (!isset($_SESSION["user_id"])) {

        header("Location: ../login.php");
        exit;
    }
}


/*
==================================================
CSRF PROTECTION
==================================================
*/

function csrf_token()
{
    if (empty($_SESSION["csrf_token"])) {

        $_SESSION["csrf_token"] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}


/*
==================================================
CSRF FORM FIELD
==================================================
*/

function csrf_field()
{
    return
        '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(
            csrf_token(),
            ENT_QUOTES,
            "UTF-8"
        ) .
        '">';
}


/*
==================================================
VERIFY CSRF
==================================================
*/

function verify_csrf()
{
    $token = $_POST["csrf_token"] ?? "";

    if (
        empty($token) ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $token
        )
    ) {

        http_response_code(403);

        die(
            "Invalid security token. Please go back and try again."
        );
    }
}