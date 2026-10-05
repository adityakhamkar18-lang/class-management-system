<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Class Management System - Add Student
|--------------------------------------------------------------------------
| Adds a new student to the students table.
|--------------------------------------------------------------------------
*/

// -------------------------------------------------------------------------
// Authentication
// -------------------------------------------------------------------------

if (
    !isset($_SESSION['admin']) ||
    $_SESSION['admin'] === ''
) {
    header('Location: login.php');
    exit;
}

// -------------------------------------------------------------------------
// Database
// -------------------------------------------------------------------------

require_once __DIR__ . '/config.php';

// -------------------------------------------------------------------------
// CSRF protection
// -------------------------------------------------------------------------

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// -------------------------------------------------------------------------
// Initialize variables
// -------------------------------------------------------------------------

$error = '';

$roll_no = '';
$name = '';
$email = '';
$phone = '';
$gender = '';
$class_name = '';

// -------------------------------------------------------------------------
// Process form submission
// -------------------------------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save'])
) {

    // -------------------------------------------------------------
    // Verify CSRF token
    // -------------------------------------------------------------

    $submitted_token = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (
        $submitted_token === '' ||
        !hash_equals($csrf_token, $submitted_token)
    ) {

        $error = 'Invalid form request. Please refresh the page and try again.';

    } else {

        // ---------------------------------------------------------
        // Get and clean form data
        // ---------------------------------------------------------

        $roll_no = trim(
            (string) ($_POST['roll_no'] ?? '')
        );

        $name = trim(
            (string) ($_POST['name'] ?? '')
        );

        $email = trim(
            (string) ($_POST['email'] ?? '')
        );

        $phone = trim(
            (string) ($_POST['phone'] ?? '')
        );

        $gender = trim(
            (string) ($_POST['gender'] ?? '')
        );

        $class_name = trim(
            (string) ($_POST['class_name'] ?? '')
        );

        // ---------------------------------------------------------
        // Validate Roll Number
        // ---------------------------------------------------------

        if ($roll_no === '') {

            $error = 'Please enter the roll number.';

        } elseif (strlen($roll_no) > 50) {

            $error = 'Roll number must not exceed 50 characters.';

        }

        // ---------------------------------------------------------
        // Validate Name
        // ---------------------------------------------------------

        elseif ($name === '') {

            $error = "Please enter the student's name.";

        } elseif (strlen($name) < 2) {

            $error = 'Student name must contain at least 2 characters.';

        } elseif (strlen($name) > 100) {

            $error = 'Student name must not exceed 100 characters.';

        }

        // ---------------------------------------------------------
        // Validate Email
        // ---------------------------------------------------------

        elseif ($email !== '' && strlen($email) > 150) {

            $error = 'Email address must not exceed 150 characters.';

        } elseif (
            $email !== '' &&
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $error = 'Please enter a valid email address.';

        }

        // ---------------------------------------------------------
        // Validate Phone
        // ---------------------------------------------------------

        elseif ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) {

            $error = 'Phone number must contain exactly 10 digits.';

        }

        // ---------------------------------------------------------
        // Validate Gender
        // ---------------------------------------------------------

        elseif (
            $gender !== '' &&
            !in_array(
                $gender,
                ['Male', 'Female'],
                true
            )
        ) {

            $error = 'Please select a valid gender.';

        }

        // ---------------------------------------------------------
        // Validate Class
        // ---------------------------------------------------------

        elseif ($class_name === '') {

            $error = 'Please enter the class.';

        } elseif (strlen($class_name) > 100) {

            $error = 'Class name must not exceed 100 characters.';

        }

        // ---------------------------------------------------------
        // Database operations
        // ---------------------------------------------------------

        if ($error === '') {

            try {

                // -------------------------------------------------
                // Check duplicate roll number
                // -------------------------------------------------

                $check_stmt = $conn->prepare(
                    'SELECT id FROM students WHERE roll_no = ? LIMIT 1'
                );

                $check_stmt->bind_param(
                    's',
                    $roll_no
                );

                $check_stmt->execute();

                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {

                    $error = 'This roll number already exists.';

                }

                $check_stmt->close();

                // -------------------------------------------------
                // Insert student
                // -------------------------------------------------

                if ($error === '') {

                    /*
                    |--------------------------------------------------------------------------
                    | IMPORTANT
                    |--------------------------------------------------------------------------
                    | No password is stored for students.
                    | Only the administrator needs authentication.
                    |--------------------------------------------------------------------------
                    */

                    $insert_stmt = $conn->prepare(
                        'INSERT INTO students
                        (
                            roll_no,
                            name,
                            email,
                            phone,
                            gender,
                            class_name
                        )
                        VALUES (?, ?, ?, ?, ?, ?)'
                    );

                    $insert_stmt->bind_param(
                        'ssssss',
                        $roll_no,
                        $name,
                        $email,
                        $phone,
                        $gender,
                        $class_name
                    );

                    $insert_stmt->execute();

                    $insert_stmt->close();

                    // -------------------------------------------------
                    // Success
                    // -------------------------------------------------

                    header('Location: students.php');
                    exit;
                }

            } catch (mysqli_sql_exception $e) {

                /*
                |--------------------------------------------------------------------------
                | Database errors are logged privately.
                | Technical database information is never shown to visitors.
                |--------------------------------------------------------------------------
                */

                error_log(
                    'Class Management System - Add student database error: ' .
                    $e->getMessage()
                );

                /*
                |--------------------------------------------------------------------------
                | Duplicate-key protection
                |--------------------------------------------------------------------------
                | If roll_no is made UNIQUE in the database, this catches
                | a duplicate even if another request creates the same
                | roll number at the same time.
                |--------------------------------------------------------------------------
                */

                if ((int) $e->getCode() === 1062) {

                    $error = 'This roll number already exists.';

                } else {

                    $error = 'Unable to save the student. Please try again later.';
                }
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
        name="robots"
        content="noindex, nofollow"
    >

    <title>Add Student | Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background-color: #f5f7fb;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .page-wrapper {
            min-height: 100vh;
            padding: 40px 15px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .form-card {
            width: 100%;
            max-width: 650px;
            background: #ffffff;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .page-title {
            font-weight: 700;
            color: #212529;
            margin-bottom: 5px;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
        }

        .form-control,
        .form-select {
            padding: 11px 12px;
            border-radius: 8px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button-group .btn {
            min-width: 130px;
        }

        .form-text {
            font-size: 12px;
        }

        @media (max-width: 576px) {

            .page-wrapper {
                padding: 20px 12px;
            }

            .form-card {
                padding: 25px 20px;
            }

            .button-group {
                flex-direction: column;
            }

            .button-group .btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <div class="form-card">

        <h2 class="page-title">
            Add Student
        </h2>

        <p class="page-subtitle">
            Enter the student's information below.
        </p>

        <?php if ($error !== '') { ?>

            <div
                class="alert alert-danger"
                role="alert"
            >

                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>

        <?php } ?>

        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <!-- CSRF Token -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >


            <!-- Roll Number -->

            <div class="mb-3">

                <label
                    for="roll_no"
                    class="form-label"
                >
                    Roll No
                </label>

                <input
                    type="text"
                    id="roll_no"
                    name="roll_no"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $roll_no,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    placeholder="Enter roll number"
                    maxlength="50"
                    required
                >

            </div>


            <!-- Student Name -->

            <div class="mb-3">

                <label
                    for="name"
                    class="form-label"
                >
                    Student Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    placeholder="Enter student name"
                    maxlength="100"
                    required
                >

            </div>


            <!-- Email -->

            <div class="mb-3">

                <label
                    for="email"
                    class="form-label"
                >
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    placeholder="Enter email address"
                    maxlength="150"
                >

            </div>


            <!-- Phone -->

            <div class="mb-3">

                <label
                    for="phone"
                    class="form-label"
                >
                    Phone
                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $phone,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    placeholder="Enter 10-digit phone number"
                    maxlength="10"
                    inputmode="numeric"
                    pattern="[0-9]{10}"
                >

                <div class="form-text">
                    Enter exactly 10 digits.
                </div>

            </div>


            <!-- Gender -->

            <div class="mb-3">

                <label
                    for="gender"
                    class="form-label"
                >
                    Gender
                </label>

                <select
                    id="gender"
                    name="gender"
                    class="form-select"
                >

                    <option value="">
                        Select Gender
                    </option>

                    <option
                        value="Male"
                        <?php
                        echo $gender === 'Male'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Male
                    </option>

                    <option
                        value="Female"
                        <?php
                        echo $gender === 'Female'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Female
                    </option>

                </select>

            </div>


            <!-- Class -->

            <div class="mb-4">

                <label
                    for="class_name"
                    class="form-label"
                >
                    Class
                </label>

                <input
                    type="text"
                    id="class_name"
                    name="class_name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $class_name,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    placeholder="Enter class"
                    maxlength="100"
                    required
                >

            </div>


            <!-- Buttons -->

            <div class="button-group">

                <button
                    type="submit"
                    name="save"
                    value="1"
                    class="btn btn-success px-4"
                >
                    Save Student
                </button>

                <a
                    href="students.php"
                    class="btn btn-secondary px-4"
                >
                    Back
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>
