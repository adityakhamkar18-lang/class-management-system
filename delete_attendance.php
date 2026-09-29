<?php

session_start();

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("config.php");

/*
|--------------------------------------------------------------------------
| Get Attendance ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/
if (!$id || $id <= 0) {
    header("Location: attendance.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Delete Attendance Record
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM attendance WHERE id = ?"
);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $id);

    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| Return to Attendance Page
|--------------------------------------------------------------------------
*/
header("Location: attendance.php");
exit();

?>