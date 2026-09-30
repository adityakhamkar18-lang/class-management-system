<?php

session_start();

/*
|--------------------------------------------------------------------------
| CLASS MANAGEMENT SYSTEM
| ENTRY POINT
|--------------------------------------------------------------------------
| If the admin is already logged in, go to dashboard.
| Otherwise, go to login page.
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit();
}

header("Location: login.php");
exit();

?>