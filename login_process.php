<?php

/*
|--------------------------------------------------------------------------
| Start Secure Session
|--------------------------------------------------------------------------
*/

session_start();

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

require_once "config.php";

/*
|--------------------------------------------------------------------------
| Allow POST Requests Only
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Get Submitted Login Data
|--------------------------------------------------------------------------
*/

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if ($username === "" || $password === "") {

    $_SESSION["login_error"] =
        "Please enter your username and password.";

    header("Location: login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Username Validation
|--------------------------------------------------------------------------
|
| Prevent unnecessarily large input from reaching the database.
|
*/

if (strlen($username) > 50) {

    $_SESSION["login_error"] =
        "Invalid username or password.";

    header("Location: login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Check Database Connection
|--------------------------------------------------------------------------
*/

if (!$conn || mysqli_connect_errno()) {

    $_SESSION["login_error"] =
        "Unable to connect to the system. Please try again later.";

    header("Location: login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Find Administrator
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        username,
        password
    FROM admin
    WHERE username = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

/*
|--------------------------------------------------------------------------
| Handle Statement Error
|--------------------------------------------------------------------------
*/

if (!$stmt) {

    $_SESSION["login_error"] =
        "Unable to process login. Please try again later.";

    header("Location: login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Bind Username
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $username
);

/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    $_SESSION["login_error"] =
        "Unable to process login. Please try again later.";

    header("Location: login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Get Result
|--------------------------------------------------------------------------
*/

$result = mysqli_stmt_get_result($stmt);

$admin = $result
    ? mysqli_fetch_assoc($result)
    : null;

/*
|--------------------------------------------------------------------------
| Close Statement
|--------------------------------------------------------------------------
*/

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Verify Password
|--------------------------------------------------------------------------
*/

if (
    $admin &&
    isset($admin["password"]) &&
    password_verify(
        $password,
        $admin["password"]
    )
) {

    /*
    |--------------------------------------------------------------------------
    | Regenerate Session ID
    |--------------------------------------------------------------------------
    |
    | Helps protect against session fixation attacks.
    |
    */

    session_regenerate_id(true);

    /*
    |--------------------------------------------------------------------------
    | Store Admin Session
    |--------------------------------------------------------------------------
    */

    $_SESSION["admin"] = $admin["username"];

    $_SESSION["admin_id"] = (int) $admin["id"];

    /*
    |--------------------------------------------------------------------------
    | Store Login Timestamp
    |--------------------------------------------------------------------------
    */

    $_SESSION["login_time"] = time();

    /*
    |--------------------------------------------------------------------------
    | Redirect To Dashboard
    |--------------------------------------------------------------------------
    */

    header("Location: dashboard.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Login Failed
|--------------------------------------------------------------------------
|
| Keep the message generic so we don't reveal whether the username
| exists in the database.
|
*/

$_SESSION["login_error"] =
    "Invalid username or password.";

/*
|--------------------------------------------------------------------------
| Redirect Back To Login
|--------------------------------------------------------------------------
*/

header("Location: login.php");
exit();

?>
