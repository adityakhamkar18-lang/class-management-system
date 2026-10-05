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
| CSRF Token
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Validate Subject ID
|--------------------------------------------------------------------------
*/
$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($id === false || $id === null) {
    header('Location: subjects.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/
$error = '';

$subject_name = '';
$subject_code = '';
$teacher_name = '';

/*
|--------------------------------------------------------------------------
| Fetch Existing Subject
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conn->prepare(
        'SELECT subject_name, subject_code, teacher_name
         FROM subjects
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();

        header('Location: subjects.php');
        exit;
    }

    $stmt->bind_result(
        $subject_name,
        $subject_code,
        $teacher_name
    );

    $stmt->fetch();
    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Edit Subject Load Error: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to load the subject. Please try again later.');
}

/*
|--------------------------------------------------------------------------
| Process Update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    $submitted_csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_csrf) ||
        !hash_equals($csrf_token, $submitted_csrf)
    ) {

        $error =
            'Invalid request. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Form Data
        |--------------------------------------------------------------------------
        */
        $subject_name = trim(
            (string) ($_POST['subject_name'] ?? '')
        );

        $subject_code = trim(
            (string) ($_POST['subject_code'] ?? '')
        );

        $teacher_name = trim(
            (string) ($_POST['teacher_name'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        if ($subject_name === '') {

            $error = 'Please enter the subject name.';

        } elseif (mb_strlen($subject_name) < 2) {

            $error =
                'Subject name must contain at least 2 characters.';

        } elseif (mb_strlen($subject_name) > 100) {

            $error =
                'Subject name must not exceed 100 characters.';

        } elseif ($subject_code === '') {

            $error = 'Please enter the subject code.';

        } elseif (mb_strlen($subject_code) < 1) {

            $error = 'Subject code cannot be empty.';

        } elseif (mb_strlen($subject_code) > 50) {

            $error =
                'Subject code must not exceed 50 characters.';

        } elseif ($teacher_name === '') {

            $error = 'Please enter the teacher name.';

        } elseif (mb_strlen($teacher_name) < 2) {

            $error =
                'Teacher name must contain at least 2 characters.';

        } elseif (mb_strlen($teacher_name) > 100) {

            $error =
                'Teacher name must not exceed 100 characters.';
        }

        /*
        |--------------------------------------------------------------------------
        | Database Validation and Update
        |--------------------------------------------------------------------------
        */
        if ($error === '') {

            try {

                /*
                |--------------------------------------------------------------------------
                | Check Duplicate Subject Name
                |--------------------------------------------------------------------------
                */
                $check_name = $conn->prepare(
                    'SELECT id
                     FROM subjects
                     WHERE subject_name = ?
                       AND id != ?
                     LIMIT 1'
                );

                $check_name->bind_param(
                    'si',
                    $subject_name,
                    $id
                );

                $check_name->execute();
                $check_name->store_result();

                if ($check_name->num_rows > 0) {

                    $error =
                        'This subject name already exists.';
                }

                $check_name->close();

                /*
                |--------------------------------------------------------------------------
                | Check Duplicate Subject Code
                |--------------------------------------------------------------------------
                */
                if ($error === '') {

                    $check_code = $conn->prepare(
                        'SELECT id
                         FROM subjects
                         WHERE subject_code = ?
                           AND id != ?
                         LIMIT 1'
                    );

                    $check_code->bind_param(
                        'si',
                        $subject_code,
                        $id
                    );

                    $check_code->execute();
                    $check_code->store_result();

                    if ($check_code->num_rows > 0) {

                        $error =
                            'This subject code already exists.';
                    }

                    $check_code->close();
                }

                /*
                |--------------------------------------------------------------------------
                | Update Subject
                |--------------------------------------------------------------------------
                */
                if ($error === '') {

                    $update_stmt = $conn->prepare(
                        'UPDATE subjects
                         SET subject_name = ?,
                             subject_code = ?,
                             teacher_name = ?
                         WHERE id = ?'
                    );

                    $update_stmt->bind_param(
                        'sssi',
                        $subject_name,
                        $subject_code,
                        $teacher_name,
                        $id
                    );

                    $update_stmt->execute();
                    $update_stmt->close();

                    /*
                    |--------------------------------------------------------------------------
                    | Success Message
                    |--------------------------------------------------------------------------
                    */
                    $_SESSION['subject_update_success'] =
                        'Subject updated successfully.';

                    /*
                    |--------------------------------------------------------------------------
                    | Regenerate CSRF Token
                    |--------------------------------------------------------------------------
                    */
                    $_SESSION['csrf_token'] = bin2hex(
                        random_bytes(32)
                    );

                    header('Location: subjects.php');
                    exit;
                }

            } catch (mysqli_sql_exception $e) {

                error_log(
                    'Class Management System - Update Subject Error: ' .
                    $e->getMessage()
                );

                if ((int) $e->getCode() === 1062) {

                    $error =
                        'A subject with the same name or code already exists.';

                } else {

                    $error =
                        'Unable to update the subject. Please try again.';
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

    <title>Edit Subject - Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background-color: #f5f7fb;
            min-height: 100vh;
        }

        .form-card {
            max-width: 600px;
            margin: 50px auto;
            background: #ffffff;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-weight: 600;
            color: #212529;
        }

        .form-label {
            font-weight: 500;
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="form-card">

        <h2 class="page-title mb-4">
            Edit Subject
        </h2>

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

        <form method="POST" action="">

            <!-- CSRF Protection -->
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

            <!-- Subject Name -->
            <div class="mb-3">

                <label
                    for="subject_name"
                    class="form-label"
                >
                    Subject Name
                </label>

                <input
                    type="text"
                    id="subject_name"
                    name="subject_name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $subject_name,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    maxlength="100"
                    autocomplete="off"
                    required
                >

            </div>

            <!-- Subject Code -->
            <div class="mb-3">

                <label
                    for="subject_code"
                    class="form-label"
                >
                    Subject Code
                </label>

                <input
                    type="text"
                    id="subject_code"
                    name="subject_code"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $subject_code,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    maxlength="50"
                    autocomplete="off"
                    required
                >

            </div>

            <!-- Teacher Name -->
            <div class="mb-4">

                <label
                    for="teacher_name"
                    class="form-label"
                >
                    Teacher Name
                </label>

                <input
                    type="text"
                    id="teacher_name"
                    name="teacher_name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $teacher_name,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    maxlength="100"
                    autocomplete="off"
                    required
                >

            </div>

            <!-- Buttons -->
            <div class="button-group">

                <button
                    type="submit"
                    name="update"
                    value="1"
                    class="btn btn-primary px-4"
                >
                    Update Subject
                </button>

                <a
                    href="subjects.php"
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