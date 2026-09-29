<?php

session_start();

/*
|--------------------------------------------------------------------------
| Redirect if already logged in
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Get login error
|--------------------------------------------------------------------------
*/
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

?>

<!DOCTYPE html>

<html lang="en">
<head>

```
<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<meta name="description" content="Secure administrator login for Class Management System">

<title>Admin Login | Class Management System</title>

<style>

    /* =========================================================
       RESET
    ========================================================= */

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    /* =========================================================
       PAGE
    ========================================================= */

    body {
        min-height: 100vh;

        display: flex;
        align-items: center;
        justify-content: center;

        padding: 24px;

        font-family:
            Arial,
            Helvetica,
            sans-serif;

        background:
            linear-gradient(
                135deg,
                rgba(13, 110, 253, 0.78),
                rgba(13, 110, 253, 0.35)
            ),
            url("images/login-bg.jpg");

        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;

        position: relative;
    }

    /* Dark overlay */

    body::before {
        content: "";

        position: fixed;

        inset: 0;

        background:
            rgba(0, 0, 0, 0.20);

        z-index: 0;

        pointer-events: none;
    }

    /* =========================================================
       LOGIN CARD
    ========================================================= */

    .login-box {
        position: relative;
        z-index: 1;

        width: 430px;
        max-width: 100%;

        padding: 38px 38px 28px;

        background: rgba(255, 255, 255, 0.97);

        border: 1px solid rgba(255, 255, 255, 0.8);

        border-radius: 20px;

        box-shadow:
            0 25px 60px rgba(0, 0, 0, 0.28);

        backdrop-filter: blur(8px);
    }

    /* =========================================================
       LOGO
    ========================================================= */

    .logo-container {
        display: flex;
        justify-content: center;
        align-items: center;

        margin-bottom: 12px;
    }

    .logo {
        width: 115px;
        height: 90px;

        object-fit: contain;

        display: block;
    }

    /* =========================================================
       HEADING
    ========================================================= */

    h1 {
        text-align: center;

        color: #212529;

        font-size: 27px;
        font-weight: 700;

        line-height: 1.3;

        margin-bottom: 6px;
    }

    .subtitle {
        text-align: center;

        color: #6c757d;

        font-size: 14px;

        line-height: 1.5;

        margin-bottom: 27px;
    }

    /* =========================================================
       ERROR MESSAGE
    ========================================================= */

    .error {
        display: flex;
        align-items: center;
        gap: 9px;

        margin-bottom: 20px;

        padding: 12px 14px;

        color: #842029;

        background: #fff0f1;

        border: 1px solid #f5c2c7;

        border-radius: 9px;

        font-size: 13px;

        line-height: 1.4;
    }

    .error-icon {
        flex-shrink: 0;

        font-size: 16px;
    }

    /* =========================================================
       FORM
    ========================================================= */

    .form-group {
        margin-bottom: 18px;
    }

    label {
        display: block;

        margin-bottom: 7px;

        color: #343a40;

        font-size: 14px;

        font-weight: 600;
    }

    /* =========================================================
       INPUT
    ========================================================= */

    .input-wrapper {
        position: relative;
    }

    input[type="text"],
    input[type="password"] {
        width: 100%;

        height: 48px;

        padding: 0 14px;

        color: #212529;

        background: #ffffff;

        border: 1px solid #ced4da;

        border-radius: 9px;

        outline: none;

        font-family: inherit;

        font-size: 14px;

        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }

    input::placeholder {
        color: #9aa0a6;
    }

    input:hover {
        border-color: #adb5bd;
    }

    input:focus {
        border-color: #0d6efd;

        background: #ffffff;

        box-shadow:
            0 0 0 3px rgba(13, 110, 253, 0.13);
    }

    /* Password input */

    .password-input {
        padding-right: 72px !important;
    }

    /* =========================================================
       SHOW PASSWORD
    ========================================================= */

    .show-password {
        position: absolute;

        top: 50%;
        right: 10px;

        transform: translateY(-50%);

        border: none;

        background: transparent;

        color: #0d6efd;

        font-size: 12px;

        font-weight: 700;

        cursor: pointer;

        padding: 7px 6px;

        border-radius: 5px;

        transition:
            background 0.2s ease,
            color 0.2s ease;
    }

    .show-password:hover {
        background: #eef5ff;

        color: #084298;
    }

    .show-password:focus-visible {
        outline: 2px solid #0d6efd;

        outline-offset: 2px;
    }

    /* =========================================================
       LOGIN OPTIONS
    ========================================================= */

    .login-options {
        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 12px;

        margin-top: 2px;

        margin-bottom: 21px;
    }

    .remember-me {
        display: flex;
        align-items: center;

        gap: 7px;

        margin: 0;

        color: #495057;

        font-size: 13px;

        font-weight: 400;

        cursor: pointer;
    }

    .remember-me input {
        width: 15px;
        height: 15px;

        margin: 0;

        accent-color: #0d6efd;

        cursor: pointer;
    }

    .forgot-password {
        color: #0d6efd;

        text-decoration: none;

        font-size: 13px;

        font-weight: 600;

        white-space: nowrap;
    }

    .forgot-password:hover {
        text-decoration: underline;

        color: #084298;
    }

    /* =========================================================
       LOGIN BUTTON
    ========================================================= */

    .login-button {
        width: 100%;

        height: 49px;

        border: none;

        border-radius: 9px;

        background:
            linear-gradient(
                135deg,
                #0d6efd,
                #0b5ed7
            );

        color: #ffffff;

        font-family: inherit;

        font-size: 15px;

        font-weight: 700;

        letter-spacing: 0.2px;

        cursor: pointer;

        box-shadow:
            0 6px 16px rgba(13, 110, 253, 0.22);

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease,
            filter 0.2s ease;
    }

    .login-button:hover {
        transform: translateY(-1px);

        box-shadow:
            0 9px 20px rgba(13, 110, 253, 0.28);

        filter: brightness(1.03);
    }

    .login-button:active {
        transform: translateY(0);

        box-shadow:
            0 4px 10px rgba(13, 110, 253, 0.20);
    }

    .login-button:focus-visible {
        outline: 3px solid rgba(13, 110, 253, 0.25);

        outline-offset: 3px;
    }

    /* =========================================================
       SECURITY
    ========================================================= */

    .security {
        display: flex;

        justify-content: center;
        align-items: center;

        gap: 6px;

        margin-top: 23px;

        padding-top: 17px;

        border-top: 1px solid #e9ecef;

        color: #6c757d;

        font-size: 12px;
    }

    .security-icon {
        color: #198754;

        font-size: 13px;
    }

    /* =========================================================
       FOOTER
    ========================================================= */

    .footer {
        text-align: center;

        margin-top: 10px;

        color: #9aa0a6;

        font-size: 11px;

        line-height: 1.5;
    }

    /* =========================================================
       MOBILE
    ========================================================= */

    @media (max-width: 500px) {

        body {
            padding: 15px;
        }

        .login-box {
            width: 100%;

            padding: 30px 22px 24px;

            border-radius: 17px;
        }

        .logo {
            width: 100px;
            height: 78px;
        }

        h1 {
            font-size: 23px;
        }

        .subtitle {
            font-size: 13px;

            margin-bottom: 23px;
        }

        .login-options {
            align-items: flex-start;

            flex-direction: column;

            margin-bottom: 19px;
        }

        .forgot-password {
            align-self: flex-end;
        }

    }

    /* =========================================================
       VERY SMALL DEVICES
    ========================================================= */

    @media (max-width: 350px) {

        body {
            padding: 10px;
        }

        .login-box {
            padding: 25px 18px 20px;
        }

        h1 {
            font-size: 21px;
        }

    }

    /* =========================================================
       REDUCED MOTION
    ========================================================= */

    @media (prefers-reduced-motion: reduce) {

        * {
            transition: none !important;
        }

    }

</style>
```

</head>

<body>

```
<main class="login-box">

    <!-- =====================================================
         LOGO
    ====================================================== -->

    <div class="logo-container">

        <img
            src="images/logo.png"
            alt="Class Management System Logo"
            class="logo"
        >

    </div>


    <!-- =====================================================
         HEADING
    ====================================================== -->

    <h1>
        Admin Login
    </h1>

    <p class="subtitle">
        Class Management System
    </p>


    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if ($error !== ''): ?>

        <div
            class="error"
            role="alert"
            aria-live="polite"
        >

            <span
                class="error-icon"
                aria-hidden="true"
            >
                ⚠
            </span>

            <span>
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         LOGIN FORM
    ====================================================== -->

    <form
        action="login_process.php"
        method="POST"
        autocomplete="on"
    >

        <!-- Username -->

        <div class="form-group">

            <label for="username">
                Username
            </label>

            <div class="input-wrapper">

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    autocomplete="username"
                    maxlength="50"
                    required
                    autofocus
                >

            </div>

        </div>


        <!-- Password -->

        <div class="form-group">

            <label for="password">
                Password
            </label>

            <div class="input-wrapper">

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="password-input"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    maxlength="100"
                    required
                >

                <button
                    type="button"
                    class="show-password"
                    id="showPasswordButton"
                    aria-label="Show password"
                    onclick="togglePassword()"
                >
                    Show
                </button>

            </div>

        </div>


        <!-- Login Options -->

        <div class="login-options">

            <label class="remember-me">

                <input
                    type="checkbox"
                    id="remember"
                    name="remember"
                >

                <span>
                    Remember me
                </span>

            </label>


            <a
                href="forgot_password.php"
                class="forgot-password"
            >
                Forgot Password?
            </a>

        </div>


        <!-- Login Button -->

        <button
            type="submit"
            class="login-button"
        >
            Sign In
        </button>

    </form>


    <!-- =====================================================
         SECURITY
    ====================================================== -->

    <div class="security">

        <span
            class="security-icon"
            aria-hidden="true"
        >
            🔒
        </span>

        <span>
            Secure Administrator Access
        </span>

    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div class="footer">

        © <?php echo date("Y"); ?>

        Class Management System

        <br>

        Administrator Portal

    </div>

</main>


<!-- =========================================================
     SHOW / HIDE PASSWORD
========================================================== -->

<script>

    function togglePassword() {

        const password =
            document.getElementById("password");

        const button =
            document.getElementById("showPasswordButton");

        if (password.type === "password") {

            password.type = "text";

            button.textContent = "Hide";

            button.setAttribute(
                "aria-label",
                "Hide password"
            );

        } else {

            password.type = "password";

            button.textContent = "Show";

            button.setAttribute(
                "aria-label",
                "Show password"
            );

        }

    }

</script>
</body>
</html>
