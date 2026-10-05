<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Class Management System - Login Processing
|--------------------------------------------------------------------------
| Handles administrator authentication.
|--------------------------------------------------------------------------
*/

session_start();

require_once __DIR__ . '/config.php';


/*
|--------------------------------------------------------------------------
| Allow POST Requests Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Submitted Login Data
|--------------------------------------------------------------------------
*/

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');


/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if ($username === '' || $password === '') {

    $_SESSION['login_error'] =
        'Please enter your username and password.';

    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Username Length
|--------------------------------------------------------------------------
*/

if (strlen($username) > 50) {

    $_SESSION['login_error'] =
        'Invalid username or password.';

    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Find Administrator
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            id,
            username,
            password
        FROM admin
        WHERE username = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param('s', $username);

    $stmt->execute();

    $result = $stmt->get_result();

    $admin = $result->fetch_assoc() ?: null;

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Log Technical Error
    |--------------------------------------------------------------------------
    |
    | Never display the actual database error to the visitor.
    |
    */

    error_log(
        'Class Management System - Login database error: ' .
        $e->getMessage()
    );

    $_SESSION['login_error'] =
        'Unable to process login. Please try again later.';

    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Password
|--------------------------------------------------------------------------
*/

if (
    $admin !== null &&
    isset($admin['password']) &&
    is_string($admin['password']) &&
    password_verify($password, $admin['password'])
) {

    /*
    |--------------------------------------------------------------------------
    | Prevent Session Fixation
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);


    /*
    |--------------------------------------------------------------------------
    | Store Authenticated Admin Information
    |--------------------------------------------------------------------------
    */

    $_SESSION['admin'] = (string) $admin['username'];

    $_SESSION['admin_id'] = (int) $admin['id'];

    $_SESSION['login_time'] = time();


    /*
    |--------------------------------------------------------------------------
    | Remove Any Previous Login Error
    |--------------------------------------------------------------------------
    */

    unset($_SESSION['login_error']);


    /*
    |--------------------------------------------------------------------------
    | Redirect To Dashboard
    |--------------------------------------------------------------------------
    */

    header('Location: dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Login Failed
|--------------------------------------------------------------------------
|
| Keep the error generic.
| Do not reveal whether the username exists.
|--------------------------------------------------------------------------
*/

$_SESSION['login_error'] =
    'Invalid username or password.';


/*
|--------------------------------------------------------------------------
| Redirect Back To Login
|--------------------------------------------------------------------------
*/

header('Location: login.php');
exit;