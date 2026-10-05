<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Class Management System - Edit Student
|--------------------------------------------------------------------------
| Displays an existing student and updates the record.
|--------------------------------------------------------------------------
*/

// -------------------------------------------------------------------------
// Admin Authentication
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
// CSRF Token
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
// Get Student ID
// -------------------------------------------------------------------------

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

// -------------------------------------------------------------------------
// Validate Student ID
// -------------------------------------------------------------------------

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    header('Location: students.php');
    exit;
}

// -------------------------------------------------------------------------
// Initialize
// -------------------------------------------------------------------------

$error = '';

$roll_no = '';
$name = '';
$email = '';
$phone = '';
$gender = '';
$class_name = '';


// -------------------------------------------------------------------------
// Fetch Student
// -------------------------------------------------------------------------

try {

    $stmt = $conn->prepare(
        'SELECT
            id,
            roll_no,
            name,
            email,
            phone,
            gender,
            class_name
         FROM students
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->bind_param(
        'i',
        $id
    );

    $stmt->execute();

    $stmt->store_result();

    if ($stmt->num_rows === 0) {

        $stmt->close();

        header('Location: students.php');
        exit;
    }

    $stmt->bind_result(
        $student_id,
        $student_roll_no,
        $student_name,
        $student_email,
        $student_phone,
        $student_gender,
        $student_class_name
    );

    $stmt->fetch();

    $stmt->close();

    // Set default form values.
    $roll_no = (string) $student_roll_no;
    $name = (string) $student_name;
    $email = (string) $student_email;
    $phone = (string) $student_phone;
    $gender = (string) $student_gender;
    $class_name = (string) $student_class_name;

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Fetch student error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit('Unable to load the student record. Please try again later.');
}


// -------------------------------------------------------------------------
// Process Update
// -------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------------------------------------------------------------------
    // Verify CSRF Token
    // ---------------------------------------------------------------------

    $submitted_token = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (
        $submitted_token === '' ||
        !hash_equals($csrf_token, $submitted_token)
    ) {

        $error =
            'Invalid form request. Please refresh the page and try again.';

    } else {

        // -----------------------------------------------------------------
        // Get Submitted Values
        // -----------------------------------------------------------------

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


        // -----------------------------------------------------------------
        // Validate Roll Number
        // -----------------------------------------------------------------

        if ($roll_no === '') {

            $error =
                'Please enter the roll number.';

        } elseif (strlen($roll_no) > 50) {

            $error =
                'Roll number must not exceed 50 characters.';

        }


        // -----------------------------------------------------------------
        // Validate Name
        // -----------------------------------------------------------------

        elseif ($name === '') {

            $error =
                "Please enter the student's name.";

        } elseif (strlen($name) < 2) {

            $error =
                'Student name must contain at least 2 characters.';

        } elseif (strlen($name) > 100) {

            $error =
                'Student name must not exceed 100 characters.';

        }


        // -----------------------------------------------------------------
        // Validate Email
        // -----------------------------------------------------------------

        elseif ($email !== '' && strlen($email) > 150) {

            $error =
                'Email address must not exceed 150 characters.';

        } elseif (
            $email !== '' &&
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $error =
                'Please enter a valid email address.';

        }


        // -----------------------------------------------------------------
        // Validate Phone
        // -----------------------------------------------------------------

        elseif ($phone === '') {

            $error =
                'Please enter the phone number.';

        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {

            $error =
                'Phone number must contain exactly 10 digits.';

        }


        // -----------------------------------------------------------------
        // Validate Gender
        // -----------------------------------------------------------------

        elseif (
            !in_array(
                $gender,
                ['Male', 'Female'],
                true
            )
        ) {

            $error =
                'Please select a valid gender.';

        }


        // -----------------------------------------------------------------
        // Validate Class
        // -----------------------------------------------------------------

        elseif ($class_name === '') {

            $error =
                'Please enter the class.';

        } elseif (strlen($class_name) > 100) {

            $error =
                'Class name must not exceed 100 characters.';

        }


        // -----------------------------------------------------------------
        // Database Validation + Update
        // -----------------------------------------------------------------

        if ($error === '') {

            try {

                // ---------------------------------------------------------
                // Check Duplicate Roll Number
                // ---------------------------------------------------------

                $duplicate_roll_stmt = $conn->prepare(
                    'SELECT id
                     FROM students
                     WHERE roll_no = ?
                     AND id != ?
                     LIMIT 1'
                );

                $duplicate_roll_stmt->bind_param(
                    'si',
                    $roll_no,
                    $id
                );

                $duplicate_roll_stmt->execute();

                $duplicate_roll_stmt->store_result();

                if ($duplicate_roll_stmt->num_rows > 0) {

                    $error =
                        'This roll number is already assigned to another student.';
                }

                $duplicate_roll_stmt->close();


                // ---------------------------------------------------------
                // Check Duplicate Email
                // ---------------------------------------------------------

                if (
                    $error === '' &&
                    $email !== ''
                ) {

                    $duplicate_email_stmt = $conn->prepare(
                        'SELECT id
                         FROM students
                         WHERE email = ?
                         AND id != ?
                         LIMIT 1'
                    );

                    $duplicate_email_stmt->bind_param(
                        'si',
                        $email,
                        $id
                    );

                    $duplicate_email_stmt->execute();

                    $duplicate_email_stmt->store_result();

                    if ($duplicate_email_stmt->num_rows > 0) {

                        $error =
                            'This email address is already registered.';
                    }

                    $duplicate_email_stmt->close();
                }


                // ---------------------------------------------------------
                // Update Student
                // ---------------------------------------------------------

                if ($error === '') {

                    $update_stmt = $conn->prepare(
                        'UPDATE students
                         SET
                            roll_no = ?,
                            name = ?,
                            email = ?,
                            phone = ?,
                            gender = ?,
                            class_name = ?
                         WHERE id = ?'
                    );

                    $update_stmt->bind_param(
                        'ssssssi',
                        $roll_no,
                        $name,
                        $email,
                        $phone,
                        $gender,
                        $class_name,
                        $id
                    );

                    $update_stmt->execute();

                    $update_stmt->close();

                    // -----------------------------------------------------
                    // Success
                    // -----------------------------------------------------

                    header('Location: students.php');
                    exit;
                }

            } catch (mysqli_sql_exception $e) {

                error_log(
                    'Class Management System - Update student error: ' .
                    $e->getMessage()
                );

                /*
                |--------------------------------------------------------------------------
                | MySQL duplicate-key error
                |--------------------------------------------------------------------------
                */

                if ((int) $e->getCode() === 1062) {

                    $error =
                        'The roll number or email address is already in use.';

                } else {

                    $error =
                        'Unable to update the student. Please try again later.';
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

    <title>
        Edit Student | Class Management System
    </title>

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
            background-color: #f4f6f9;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .form-container {
            max-width: 650px;
            margin: 40px auto;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            border-radius: 12px 12px 0 0 !important;
            padding: 18px 22px;
        }

        .card-body {
            padding: 30px;
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
            box-shadow:
                0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button-group .btn {
            min-width: 130px;
        }

        @media (max-width: 576px) {

            .form-container {
                margin: 20px auto;
            }

            .card-body {
                padding: 22px 18px;
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

<div class="container">

    <div class="form-container">

        <div class="card">

            <!-- Header -->

            <div class="card-header bg-primary text-white">

                <h3 class="mb-0">
                    Edit Student
                </h3>

            </div>


            <div class="card-body">

                <!-- Error Message -->

                <?php if ($error !== ''): ?>

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

                <?php endif; ?>


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
                            name="roll_no"
                            id="roll_no"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $roll_no,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            maxlength="50"
                            required
                        >

                    </div>


                    <!-- Name -->

                    <div class="mb-3">

                        <label
                            for="name"
                            class="form-label"
                        >
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
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
                            name="email"
                            id="email"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $email,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
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
                            name="phone"
                            id="phone"
                            class="form-control"
                            maxlength="10"
                            inputmode="numeric"
                            pattern="[0-9]{10}"
                            value="<?php
                                echo htmlspecialchars(
                                    $phone,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            required
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
                            name="gender"
                            id="gender"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Gender --
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
                            name="class_name"
                            id="class_name"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $class_name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            maxlength="100"
                            required
                        >

                    </div>


                    <!-- Buttons -->

                    <div class="button-group">

                        <button
                            type="submit"
                            name="update"
                            value="1"
                            class="btn btn-primary"
                        >
                            Update Student
                        </button>

                        <a
                            href="students.php"
                            class="btn btn-secondary"
                        >
                            Back
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

</body>

</html>