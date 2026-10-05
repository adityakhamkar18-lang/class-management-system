<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Class Management System - Database Configuration
|--------------------------------------------------------------------------
| Supports:
|   - Local XAMPP / localhost
|   - Production hosting using environment variables
|
| Production environment variables:
|   DB_HOST
|   DB_USER
|   DB_PASS
|   DB_NAME
|   DB_PORT   (optional - defaults to 3306)
|--------------------------------------------------------------------------
*/

// Enable MySQLi exceptions for proper error handling.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


// -------------------------------------------------------------------------
// Database configuration
// -------------------------------------------------------------------------

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'class_management';

$db_port_raw = getenv('DB_PORT');
$db_port = ($db_port_raw !== false && $db_port_raw !== '')
    ? (int) $db_port_raw
    : 3306;


// -------------------------------------------------------------------------
// Validate configuration
// -------------------------------------------------------------------------

if (
    $db_host === '' ||
    $db_user === '' ||
    $db_name === '' ||
    $db_port <= 0
) {
    error_log('Database configuration is incomplete or invalid.');

    http_response_code(500);

    exit('Database configuration is incomplete.');
}


// -------------------------------------------------------------------------
// Create database connection
// -------------------------------------------------------------------------

try {

    $conn = new mysqli(
        $db_host,
        $db_user,
        $db_pass,
        $db_name,
        $db_port
    );

    // Use UTF-8 for the entire application.
    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {

    // Never expose database credentials or technical errors to visitors.
    error_log(
        'Class Management System - Database connection failed: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit('Unable to connect to the database. Please try again later.');
}
?>