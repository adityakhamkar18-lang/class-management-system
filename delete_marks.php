<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['admin']) || $_SESSION['admin'] === '') {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Allow Only POST Requests
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: marks.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/
$session_csrf = $_SESSION['csrf_token'] ?? '';
$posted_csrf  = $_POST['csrf_token'] ?? '';

if (
    !is_string($session_csrf) ||
    $session_csrf === '' ||
    !is_string($posted_csrf) ||
    $posted_csrf === '' ||
    !hash_equals($session_csrf, $posted_csrf)
) {
    $_SESSION['mark_delete_error'] = 'Invalid security token. Please try again.';
    header('Location: marks.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get and Validate Marks ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['mark_delete_error'] = 'Invalid marks record.';
    header('Location: marks.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Marks Record
|--------------------------------------------------------------------------
*/
try {
    $stmt = $conn->prepare(
        'DELETE FROM marks WHERE id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        $_SESSION['mark_delete_success'] = 'Marks record deleted successfully.';
    } else {
        $_SESSION['mark_delete_error'] = 'Marks record was not found.';
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Regenerate CSRF Token After Successful Action
    |--------------------------------------------------------------------------
    */
    if (isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

} catch (mysqli_sql_exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Log Technical Error Privately
    |--------------------------------------------------------------------------
    */
    error_log(
        'Class Management System - Delete marks failed: ' .
        $e->getMessage()
    );

    if ($e->getCode() === 1451) {
        $_SESSION['mark_delete_error'] =
            'This marks record cannot be deleted because it is being used elsewhere.';
    } else {
        $_SESSION['mark_delete_error'] =
            'Unable to delete the marks record. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Return to Marks Page
|--------------------------------------------------------------------------
*/
header('Location: marks.php');
exit;