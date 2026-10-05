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
| Database Configuration
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Only POST Requests Are Allowed
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: subjects.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/
$csrf_token = $_SESSION['csrf_token'] ?? '';
$submitted_csrf = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrf_token) ||
    $csrf_token === '' ||
    !is_string($submitted_csrf) ||
    !hash_equals($csrf_token, $submitted_csrf)
) {
    $_SESSION['subject_delete_error'] =
        'Invalid request. Please try again.';

    header('Location: subjects.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Subject ID
|--------------------------------------------------------------------------
*/
$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($id === false || $id === null) {

    $_SESSION['subject_delete_error'] =
        'Invalid subject selected.';

    header('Location: subjects.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Subject
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conn->prepare(
        'DELETE FROM subjects WHERE id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $affected_rows = $stmt->affected_rows;

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Check Whether Subject Existed
    |--------------------------------------------------------------------------
    */
    if ($affected_rows > 0) {

        $_SESSION['subject_delete_success'] =
            'Subject deleted successfully.';

    } else {

        $_SESSION['subject_delete_error'] =
            'Subject not found or already deleted.';
    }

} catch (mysqli_sql_exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Log Technical Error Privately
    |--------------------------------------------------------------------------
    */
    error_log(
        'Class Management System - Delete Subject Error: ' .
        $e->getMessage()
    );

    /*
    |--------------------------------------------------------------------------
    | Foreign Key Protection
    |--------------------------------------------------------------------------
    */
    if ((int) $e->getCode() === 1451) {

        $_SESSION['subject_delete_error'] =
            'This subject cannot be deleted because it is being used by other records.';

    } else {

        $_SESSION['subject_delete_error'] =
            'Unable to delete the subject. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Regenerate CSRF Token
|--------------------------------------------------------------------------
*/
$_SESSION['csrf_token'] = bin2hex(
    random_bytes(32)
);

/*
|--------------------------------------------------------------------------
| Return to Subjects Page
|--------------------------------------------------------------------------
*/
header('Location: subjects.php');
exit;