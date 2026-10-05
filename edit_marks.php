<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] === '') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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
| Get Marks ID
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    header('Location: marks.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$student_id = 0;
$student_name = '';
$subject_name = '';
$marks = '';

$subjects = [];
$subjects_error = '';

/*
|--------------------------------------------------------------------------
| Fetch Existing Marks Record
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare(
        "SELECT id, student_id, student_name, subject_name, marks
         FROM marks
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param('i', $id);

    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {

        $stmt->close();

        header('Location: marks.php');
        exit;
    }

    $stmt->bind_result(
        $record_id,
        $student_id,
        $student_name,
        $subject_name,
        $marks
    );

    $stmt->fetch();
    $stmt->close();

    $student_id = (int) $student_id;
    $student_name = (string) $student_name;
    $subject_name = (string) $subject_name;
    $marks = (string) $marks;

} catch (mysqli_sql_exception $e) {

    error_log(
        'Edit marks fetch error: ' . $e->getMessage()
    );

    header('Location: marks.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Process Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Verification
    |--------------------------------------------------------------------------
    */

    $submitted_token = (string) ($_POST['csrf_token'] ?? '');

    if (
        $submitted_token === '' ||
        !hash_equals($csrf_token, $submitted_token)
    ) {

        $error =
            'Invalid form request. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Form Data
        |--------------------------------------------------------------------------
        */

        $subject_name = trim(
            (string) ($_POST['subject_name'] ?? '')
        );

        $marks_input = trim(
            (string) ($_POST['marks'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Subject
        |--------------------------------------------------------------------------
        */

        if ($subject_name === '') {

            $error = 'Please select a subject.';

        } elseif (strlen($subject_name) > 100) {

            $error = 'Invalid subject selected.';

        /*
        |--------------------------------------------------------------------------
        | Validate Marks
        |--------------------------------------------------------------------------
        */

        } elseif ($marks_input === '') {

            $error = 'Please enter marks.';

        } elseif (!preg_match('/^\d+$/', $marks_input)) {

            $error =
                'Marks must be a whole number between 0 and 100.';

        } else {

            $marks_value = (int) $marks_input;

            if ($marks_value < 0 || $marks_value > 100) {

                $error = 'Marks must be between 0 and 100.';

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Verify Subject Exists
                    |--------------------------------------------------------------------------
                    */

                    $subject_stmt = $conn->prepare(
                        "SELECT subject_name
                         FROM subjects
                         WHERE subject_name = ?
                         LIMIT 1"
                    );

                    $subject_stmt->bind_param(
                        's',
                        $subject_name
                    );

                    $subject_stmt->execute();
                    $subject_stmt->store_result();

                    if ($subject_stmt->num_rows === 0) {

                        $error =
                            'Selected subject was not found.';

                        $subject_stmt->close();

                    } else {

                        $subject_stmt->close();

                        /*
                        |--------------------------------------------------------------------------
                        | Check Duplicate Marks
                        |--------------------------------------------------------------------------
                        |
                        | Same student + same subject is not allowed,
                        | except for the record currently being edited.
                        |
                        */

                        $duplicate_stmt = $conn->prepare(
                            "SELECT id
                             FROM marks
                             WHERE student_id = ?
                               AND subject_name = ?
                               AND id <> ?
                             LIMIT 1"
                        );

                        $duplicate_stmt->bind_param(
                            'isi',
                            $student_id,
                            $subject_name,
                            $id
                        );

                        $duplicate_stmt->execute();
                        $duplicate_stmt->store_result();

                        if ($duplicate_stmt->num_rows > 0) {

                            $error =
                                'Marks for this student and subject already exist.';

                            $duplicate_stmt->close();

                        } else {

                            $duplicate_stmt->close();

                            /*
                            |--------------------------------------------------------------------------
                            | Update Marks
                            |--------------------------------------------------------------------------
                            */

                            $update_stmt = $conn->prepare(
                                "UPDATE marks
                                 SET subject_name = ?, marks = ?
                                 WHERE id = ?"
                            );

                            $update_stmt->bind_param(
                                'sii',
                                $subject_name,
                                $marks_value,
                                $id
                            );

                            $update_stmt->execute();

                            $update_stmt->close();

                            /*
                            |--------------------------------------------------------------------------
                            | Success - Post/Redirect/Get
                            |--------------------------------------------------------------------------
                            */

                            $_SESSION['mark_update_success'] =
                                'Marks updated successfully.';

                            $_SESSION['csrf_token'] =
                                bin2hex(random_bytes(32));

                            header('Location: marks.php');
                            exit;
                        }
                    }

                } catch (mysqli_sql_exception $e) {

                    error_log(
                        'Edit marks database error: ' . $e->getMessage()
                    );

                    if ((int) $e->getCode() === 1062) {

                        $error =
                            'Marks for this student and subject already exist.';

                    } else {

                        $error =
                            'Unable to update marks right now. Please try again later.';
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Subjects
|--------------------------------------------------------------------------
*/

try {

    $subject_list_stmt = $conn->prepare(
        "SELECT subject_name
         FROM subjects
         ORDER BY subject_name ASC"
    );

    $subject_list_stmt->execute();
    $subject_list_stmt->store_result();

    $subject_list_stmt->bind_result($available_subject);

    while ($subject_list_stmt->fetch()) {

        $subjects[] = (string) $available_subject;
    }

    $subject_list_stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Edit marks subject list error: ' . $e->getMessage()
    );

    $subjects_error =
        'Unable to load subjects right now.';
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

    <title>Edit Marks | Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6f9;
            font-family: Arial, Helvetica, sans-serif;
            color: #212529;
            min-height: 100vh;
        }

        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .form-card {
            width: 100%;
            max-width: 620px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 15px;
            border-radius: 14px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 27px;
        }

        .page-title {
            margin: 0;
            color: #111827;
            font-size: 27px;
            font-weight: 700;
        }

        .page-subtitle {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 8px;
            border-color: #d1d5db;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12);
        }

        .readonly-field {
            background: #f8fafc;
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .button-group .btn {
            min-height: 45px;
        }

        @media (max-width: 576px) {

            .page-wrapper {
                padding: 15px;
            }

            .form-card {
                padding: 25px 20px;
            }

            .page-title {
                font-size: 23px;
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

        <div class="form-header">

            <div class="form-icon">
                <i class="bi bi-pencil-square"></i>
            </div>

            <h1 class="page-title">
                Edit Marks
            </h1>

            <p class="page-subtitle">
                Update the student's academic marks.
            </p>

        </div>

        <?php if ($error !== ''): ?>

            <div
                class="alert alert-danger"
                role="alert"
            >
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo e($error); ?>
            </div>

        <?php endif; ?>

        <?php if ($subjects_error !== ''): ?>

            <div
                class="alert alert-warning"
                role="alert"
            >
                <i class="bi bi-book-fill me-2"></i>
                <?php echo e($subjects_error); ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="edit_marks.php?id=<?php echo (int) $id; ?>"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($csrf_token); ?>"
            >

            <!-- Student -->

            <div class="mb-4">

                <label
                    for="student_name"
                    class="form-label"
                >
                    Student
                </label>

                <input
                    type="text"
                    id="student_name"
                    class="form-control readonly-field"
                    value="<?php echo e($student_name); ?>"
                    readonly
                >

                <div class="form-text">
                    Student cannot be changed while editing this marks record.
                </div>

            </div>

            <!-- Subject -->

            <div class="mb-4">

                <label
                    for="subject_name"
                    class="form-label"
                >
                    Subject
                </label>

                <select
                    name="subject_name"
                    id="subject_name"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Subject
                    </option>

                    <?php foreach ($subjects as $subject): ?>

                        <option
                            value="<?php echo e($subject); ?>"
                            <?php
                            echo (
                                $subject === $subject_name
                            ) ? 'selected' : '';
                            ?>
                        >
                            <?php echo e($subject); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Marks -->

            <div class="mb-4">

                <label
                    for="marks"
                    class="form-label"
                >
                    Marks
                </label>

                <input
                    type="number"
                    name="marks"
                    id="marks"
                    class="form-control"
                    min="0"
                    max="100"
                    step="1"
                    inputmode="numeric"
                    value="<?php echo e($marks); ?>"
                    required
                >

                <div class="form-text">
                    Enter a whole number between 0 and 100.
                </div>

            </div>

            <!-- Buttons -->

            <div class="button-group">

                <button
                    type="submit"
                    name="update"
                    class="btn btn-primary px-4"
                    <?php echo count($subjects) === 0 ? 'disabled' : ''; ?>
                >
                    <i class="bi bi-check-lg me-1"></i>
                    Update Marks
                </button>

                <a
                    href="marks.php"
                    class="btn btn-outline-secondary px-4"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Marks
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>