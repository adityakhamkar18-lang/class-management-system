<?php

session_start();

/*
|--------------------------------------------------------------------------
| If already logged in, go to dashboard
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
require_once "config.php";

$error = "";
$success = "";
$username = "";

/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($username === "") {

        $error = "Please enter your username.";

    } elseif (strlen($username) > 50) {

        $error = "Username is too long.";

    } elseif ($new_password === "") {

        $error = "Please enter a new password.";

    } elseif (strlen($new_password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } elseif (strlen($new_password) > 100) {

        $error = "Password is too long.";

    } elseif ($confirm_password === "") {

        $error = "Please confirm your new password.";

    } elseif ($new_password !== $confirm_password) {

        $error = "Passwords do not match.";

    }

    /*
    |--------------------------------------------------------------------------
    | Database Operation
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check database connection
            |--------------------------------------------------------------------------
            */

            if (!isset($conn) || !$conn instanceof mysqli) {
                throw new Exception("Database connection unavailable.");
            }

            /*
            |--------------------------------------------------------------------------
            | Check if MySQL connection is still alive
            |--------------------------------------------------------------------------
            */

            if (!mysqli_ping($conn)) {

                mysqli_close($conn);

                require "config.php";

                if (!isset($conn) || !$conn instanceof mysqli) {
                    throw new Exception("Database reconnection failed.");
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Find Admin
            |--------------------------------------------------------------------------
            */

            $check_sql = "SELECT id FROM admin WHERE username = ? LIMIT 1";

            $check_stmt = mysqli_prepare($conn, $check_sql);

            if (!$check_stmt) {
                throw new Exception("Unable to prepare database request.");
            }

            mysqli_stmt_bind_param(
                $check_stmt,
                "s",
                $username
            );

            if (!mysqli_stmt_execute($check_stmt)) {

                mysqli_stmt_close($check_stmt);

                throw new Exception("Unable to check username.");
            }

            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) === 0) {

                mysqli_stmt_close($check_stmt);

                $error = "Username not found.";

            } else {

                mysqli_stmt_close($check_stmt);

                /*
                |--------------------------------------------------------------------------
                | Hash New Password
                |--------------------------------------------------------------------------
                */

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                if ($hashed_password === false) {
                    throw new Exception("Unable to secure the new password.");
                }

                /*
                |--------------------------------------------------------------------------
                | Update Password
                |--------------------------------------------------------------------------
                */

                $update_sql = "
                    UPDATE admin
                    SET password = ?
                    WHERE username = ?
                    LIMIT 1
                ";

                $update_stmt = mysqli_prepare(
                    $conn,
                    $update_sql
                );

                if (!$update_stmt) {
                    throw new Exception("Unable to prepare password update.");
                }

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ss",
                    $hashed_password,
                    $username
                );

                if (!mysqli_stmt_execute($update_stmt)) {

                    mysqli_stmt_close($update_stmt);

                    throw new Exception("Unable to update password.");
                }

                if (mysqli_stmt_affected_rows($update_stmt) < 1) {

                    mysqli_stmt_close($update_stmt);

                    throw new Exception("Password could not be updated.");
                }

                mysqli_stmt_close($update_stmt);

                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */

                $success =
                    "Password reset successfully. You can now login with your new password.";

                $username = "";
            }

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Log real technical error
            |--------------------------------------------------------------------------
            */

            error_log(
                "Forgot password error: " . $e->getMessage()
            );

            /*
            |--------------------------------------------------------------------------
            | Show safe message to user
            |--------------------------------------------------------------------------
            */

            if ($error === "") {

                $error =
                    "Unable to reset the password right now. Please make sure MySQL is running and try again.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Reset administrator password for Class Management System"
    >

    <title>
        Reset Password - Class Management System
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    rgba(0, 0, 0, 0.58),
                    rgba(13, 110, 253, 0.58)
                ),
                url("images/login-bg.jpg");

            background-size: cover;

            background-position: center;

            background-attachment: fixed;

            padding: 20px;
        }

        .box {

            width: 430px;

            max-width: 100%;

            background: rgba(255, 255, 255, 0.98);

            padding: 35px;

            border-radius: 20px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, 0.30);
        }

        .logo-container {

            text-align: center;

            margin-bottom: 12px;
        }

        .logo {

            width: 120px;

            height: 90px;

            object-fit: contain;
        }

        h2 {

            text-align: center;

            margin: 5px 0 8px;

            color: #212529;

            font-size: 27px;
        }

        .subtitle {

            text-align: center;

            color: #6c757d;

            font-size: 14px;

            margin: 0 0 25px;

            line-height: 1.5;
        }

        .message {

            padding: 13px 14px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-size: 14px;

            text-align: center;

            line-height: 1.4;
        }

        .error {

            background: #f8d7da;

            color: #842029;

            border: 1px solid #f5c2c7;
        }

        .success {

            background: #d1e7dd;

            color: #0f5132;

            border: 1px solid #badbcc;
        }

        label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #343a40;

            font-size: 14px;
        }

        .input-group {

            position: relative;

            margin-bottom: 18px;
        }

        input {

            width: 100%;

            padding: 13px 14px;

            border: 1px solid #ced4da;

            border-radius: 9px;

            font-size: 15px;

            outline: none;

            transition: 0.2s;

            background: #fff;
        }

        input:focus {

            border-color: #0d6efd;

            box-shadow:
                0 0 0 3px rgba(13, 110, 253, 0.14);
        }

        .password-input {

            padding-right: 70px;
        }

        .show-button {

            position: absolute;

            right: 10px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #0d6efd;

            font-weight: 600;

            font-size: 13px;

            cursor: pointer;

            padding: 5px;
        }

        .show-button:hover {

            color: #084298;
        }

        .help-text {

            color: #6c757d;

            font-size: 12px;

            margin-top: -8px;

            margin-bottom: 18px;
        }

        .reset-button {

            width: 100%;

            padding: 13px;

            background: #0d6efd;

            color: white;

            border: none;

            border-radius: 9px;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s;
        }

        .reset-button:hover {

            background: #0b5ed7;

            transform: translateY(-1px);

            box-shadow:
                0 5px 15px rgba(13, 110, 253, 0.25);
        }

        .back-button {

            display: block;

            text-align: center;

            margin-top: 17px;

            color: #0d6efd;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;
        }

        .back-button:hover {

            text-decoration: underline;
        }

        .footer {

            text-align: center;

            margin-top: 22px;

            color: #6c757d;

            font-size: 12px;
        }

        @media (max-width: 500px) {

            body {
                padding: 15px;
            }

            .box {

                padding: 28px 22px;

                border-radius: 16px;
            }

            h2 {
                font-size: 24px;
            }

        }

    </style>

</head>

<body>

<div class="box">

    <div class="logo-container">

        <img
            src="images/logo.png"
            alt="Class Management System Logo"
            class="logo"
        >

    </div>

    <h2>
        Reset Password
    </h2>

    <p class="subtitle">
        Enter your admin username and create a new password.
    </p>


    <?php if ($error !== ""): ?>

        <div class="message error">

            <?php
            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ""): ?>

        <div class="message success">

            <?php
            echo htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action=""
        autocomplete="off"
    >

        <label for="username">
            Admin Username
        </label>

        <input
            type="text"
            id="username"
            name="username"
            value="<?php
                echo htmlspecialchars(
                    $username,
                    ENT_QUOTES,
                    "UTF-8"
                );
            ?>"
            placeholder="Enter admin username"
            maxlength="50"
            autocomplete="username"
            required
        >


        <label
            for="new_password"
            style="margin-top: 18px;"
        >
            New Password
        </label>

        <div class="input-group">

            <input
                type="password"
                id="new_password"
                name="new_password"
                class="password-input"
                placeholder="Enter new password"
                minlength="6"
                maxlength="100"
                autocomplete="new-password"
                required
            >

            <button
                type="button"
                class="show-button"
                onclick="togglePassword('new_password', this)"
            >
                Show
            </button>

        </div>


        <div class="help-text">
            Password must contain at least 6 characters.
        </div>


        <label for="confirm_password">
            Confirm New Password
        </label>

        <div class="input-group">

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                class="password-input"
                placeholder="Confirm new password"
                minlength="6"
                maxlength="100"
                autocomplete="new-password"
                required
            >

            <button
                type="button"
                class="show-button"
                onclick="togglePassword('confirm_password', this)"
            >
                Show
            </button>

        </div>


        <button
            type="submit"
            class="reset-button"
        >
            Reset Password
        </button>

    </form>


    <a
        href="login.php"
        class="back-button"
    >
        ← Back to Login
    </a>


    <div class="footer">

        © <?php echo date("Y"); ?>

        Class Management System

    </div>

</div>


<script>

function togglePassword(inputId, button) {

    const input =
        document.getElementById(inputId);

    if (input.type === "password") {

        input.type = "text";

        button.textContent = "Hide";

    } else {

        input.type = "password";

        button.textContent = "Show";

    }

}

</script>

</body>

</html>