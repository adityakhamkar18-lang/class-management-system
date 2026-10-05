<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Class Management System - Entry Point
|--------------------------------------------------------------------------
| Logged-in admin  -> Dashboard
| Not logged in    -> Login
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['admin']) && $_SESSION['admin'] !== '') {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;