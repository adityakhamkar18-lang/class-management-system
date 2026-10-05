<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| If Already Logged In, Go To Dashboard
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['admin']) && $_SESSION['admin'] !== '') {
    header('Location: dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    strlen($_SESSION['csrf_token']) !== 64
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Reset Key
|--------------------------------------------------------------------------
|
| Set ADMIN_RESET_KEY in your hosting environment.
|
| Example:
| ADMIN_RESET_KEY=your-long-random-secret
|
*/
$admin_reset_key = getenv('ADMIN_RESET_KEY');

if ($admin_reset_key === false || $admin_reset_key === '') {
    error_log('Class Management System - ADMIN_RESET_KEY is not configured.');
    $admin_reset_key = '';
}

$error = '';
$success = '';

$username = '';
$reset_key = '';

/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim((string) ($_POST['username'] ?? ''));
    $reset_key = (string) ($_POST['reset_key'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $posted_csrf = (string) ($_POST['csrf_token'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    if (
        $posted_csrf === '' ||
        !hash_equals($csrf_token, $posted_csrf)
    ) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    elseif ($username === '') {
        $error = 'Please enter your username.';
    }

    elseif (strlen($username) > 50) {
        $error = 'Username is too long.';
    }

    elseif ($reset_key === '') {
        $error = 'Please enter the administrator reset key.';
    }

    elseif (
        $admin_reset_key === '' ||
        !hash_equals($admin_reset_key, $reset_key)
    ) {
        $error = 'Invalid reset key.';
    }

    elseif ($new_password === '') {
        $error = 'Please enter a new password.';
    }

    elseif (strlen($new_password) < 6) {
        $error = 'Password must contain at least 6 characters.';
    }

    elseif (strlen($new_password) > 100) {
        $error = 'Password is too long.';
    }

    elseif ($confirm_password === '') {
        $error = 'Please confirm your new password.';
    }

    elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    }

    /*
    |--------------------------------------------------------------------------
    | Database Operation
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | Find Admin
            |--------------------------------------------------------------------------
            */
            $stmt = $conn->prepare(
                'SELECT id FROM admin WHERE username = ? LIMIT 1'
            );

            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows !== 1) {

                $stmt->close();

                $error = 'Unable to reset the password with the information provided.';

            } else {

                $stmt->close();

                /*
                |--------------------------------------------------------------------------
                | Create Secure Password Hash
                |--------------------------------------------------------------------------
                */
                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                if ($hashed_password === false) {
                    throw new RuntimeException(
                        'Password hashing failed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Update Password
                |--------------------------------------------------------------------------
                */
                $update_stmt = $conn->prepare(
                    'UPDATE admin SET password = ? WHERE username = ? LIMIT 1'
                );

                $update_stmt->bind_param(
                    'ss',
                    $hashed_password,
                    $username
                );

                $update_stmt->execute();

                if ($update_stmt->affected_rows < 1) {
                    $update_stmt->close();

                    throw new RuntimeException(
                        'Password update did not modify the database.'
                    );
                }

                $update_stmt->close();

                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */
                $success =
                    'Password reset successfully. You can now log in with your new password.';

                $username = '';
                $reset_key = '';

                /*
                |--------------------------------------------------------------------------
                | Regenerate CSRF Token
                |--------------------------------------------------------------------------
                */
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
            }

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Log Technical Error Privately
            |--------------------------------------------------------------------------
            */
            error_log(
                'Class Management System - Forgot password error: ' .
                $e->getMessage()
            );

            if ($error === '') {
                $error =
                    'Unable to reset the password right now. Please try again later.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Never Keep Reset Key In Form After Submission
    |--------------------------------------------------------------------------
    */
    if ($error !== '') {
        $reset_key = '';
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
        name="robots"
        content="noindex,nofollow"
    >

    <meta
        name="description"
        content="Reset administrator password for Class Management System"
    >

    <title>Reset Password - Class Management System</title>

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
            font-family: Arial, Helvetica, sans-serif;

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

        .security-note {
            background: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
            padding: 12px 14px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.45;
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

    <h2>Reset Password</h2>

    <p class="subtitle">
        Reset the administrator password using your secure reset key.
    </p>

    <div class="security-note">
        The reset key is a private deployment credential.
        Do not share it publicly or commit it to GitHub.
    </div>

    <?php if ($error !== ''): ?>

        <div class="message error">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>

    <?php endif; ?>

    <?php if ($success !== ''): ?>

        <div class="message success">
            <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        action=""
        autocomplete="off"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
        >

        <label for="username">
            Admin Username
        </label>

        <input
            type="text"
            id="username"
            name="username"
            value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="Enter admin username"
            maxlength="50"
            autocomplete="username"
            required
        >

        <label
            for="reset_key"
            style="margin-top: 18px;"
        >
            Administrator Reset Key
        </label>

        <div class="input-group">

            <input
                type="password"
                id="reset_key"
                name="reset_key"
                class="password-input"
                placeholder="Enter reset key"
                autocomplete="off"
                required
            >

            <button
                type="button"
                class="show-button"
                onclick="togglePassword('reset_key', this)"
            >
                Show
            </button>

        </div>

        <label for="new_password">
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
        © <?php echo date('Y'); ?>
        Class Management System
    </div>

</div>

<script>

function togglePassword(inputId, button) {

    const input = document.getElementById(inputId);

    if (input.type === 'password') {

        input.type = 'text';
        button.textContent = 'Hide';

    } else {

        input.type = 'password';
        button.textContent = 'Show';
    }
}

</script>

</body>

</html>