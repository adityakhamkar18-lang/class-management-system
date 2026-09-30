<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| CLASS MANAGEMENT SYSTEM
| DATABASE CONFIGURATION
|--------------------------------------------------------------------------
| 
| Local:
|   Uses XAMPP defaults.
|
| Hosting:
|   Uses environment variables supplied by the hosting provider.
|
|--------------------------------------------------------------------------
*/

// ------------------------------------------------------------
// Get database settings from environment variables
// ------------------------------------------------------------

$db_host = getenv("DB_HOST");
$db_user = getenv("DB_USER");
$db_pass = getenv("DB_PASSWORD");
$db_name = getenv("DB_NAME");
$db_port = getenv("DB_PORT");

// ------------------------------------------------------------
// Local fallback
// ------------------------------------------------------------

if (empty($db_host)) {
    $db_host = "localhost";
}

if (empty($db_user)) {
    $db_user = "root";
}

if ($db_pass === false) {
    $db_pass = "";
}

if (empty($db_name)) {
    $db_name = "class_management";
}

if (empty($db_port)) {
    $db_port = 3306;
}

// ------------------------------------------------------------
// Connect to database
// ------------------------------------------------------------

try {

    $conn = mysqli_connect(
        $db_host,
        $db_user,
        $db_pass,
        $db_name,
        (int)$db_port
    );

    // UTF-8 support
    mysqli_set_charset($conn, "utf8mb4");

} catch (mysqli_sql_exception $e) {

    // Log technical error privately
    error_log(
        "Class Management System DB Error: " . $e->getMessage()
    );

    // Safe message for visitors
    die(
        "Database connection failed. Please check the database configuration."
    );
}

?>