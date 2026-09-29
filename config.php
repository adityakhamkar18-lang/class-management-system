<?php

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
|
| Local XAMPP:
|   DB_HOST=localhost
|   DB_USER=root
|   DB_PASS=
|   DB_NAME=class_management
|
| Production:
|   These values can be supplied through environment variables.
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Database Credentials
|--------------------------------------------------------------------------
*/

$host = getenv("DB_HOST") ?: "localhost";

$username = getenv("DB_USER") ?: "root";

$password = getenv("DB_PASS") ?: "";

$database = getenv("DB_NAME") ?: "class_management";

/*
|--------------------------------------------------------------------------
| Create Database Connection
|--------------------------------------------------------------------------
*/

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

/*
|--------------------------------------------------------------------------
| Check Database Connection
|--------------------------------------------------------------------------
|
| Do not expose detailed database errors to users.
|
*/

if (!$conn) {

    error_log(
        "Database connection failed: " .
        mysqli_connect_error()
    );

    die(
        "Unable to connect to the database. Please try again later."
    );

}

/*
|--------------------------------------------------------------------------
| Set UTF-8 Character Encoding
|--------------------------------------------------------------------------
*/

if (!mysqli_set_charset($conn, "utf8mb4")) {

    error_log(
        "Failed to set database character encoding: " .
        mysqli_error($conn)
    );

}

?>
