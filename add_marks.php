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
| Form Variables
|--------------------------------------------------------------------------
*/

$error = '';

$selected_student_id = '';
$selected_subject = '';
$entered_marks = '';

/*
|--------------------------------------------------------------------------
| Process Form
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
        $error = 'Invalid form request. Please refresh the page and try again.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | Read Form Data
        |--------------------------------------------------------------------------
        */

        $selected_student_id = filter_var(
            $_POST['student_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if ($selected_student_id === false || $selected_student_id <= 0) {
            $selected_student_id = '';
        }

        $selected_subject = trim(
            (string) ($_POST['subject_name'] ?? '')
        );

        $entered_marks = trim(
            (string) ($_POST['marks'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Student
        |--------------------------------------------------------------------------
        */

        if ($selected_student_id === '') {

            $error = 'Please select a valid student.';

        /*
        |--------------------------------------------------------------------------
        | Validate Subject
        |--------------------------------------------------------------------------
        */

        } elseif ($selected_subject === '') {

            $error = 'Please select a subject.';

        } elseif (strlen($selected_subject) > 100) {

            $error = 'Invalid subject selected.';

        /*
        |--------------------------------------------------------------------------
        | Validate Marks
        |--------------------------------------------------------------------------
        */

        } elseif ($entered_marks === '') {

            $error = 'Please enter marks.';

        } elseif (!preg_match('/^\d+$/', $entered_marks)) {

            $error = 'Marks must be a whole number between 0 and 100.';

        } else {

            $marks = (int) $entered_marks;

            if ($marks < 0 || $marks > 100) {

                $error = 'Marks must be between 0 and 100.';

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Verify Student and Get Current Name
                    |--------------------------------------------------------------------------
                    */

                    $student_stmt = $conn->prepare(
                        "SELECT name
                         FROM students
                         WHERE id = ?
                         LIMIT 1"
                    );

                    $student_stmt->bind_param(
                        'i',
                        $selected_student_id
                    );

                    $student_stmt->execute();
                    $student_stmt->store_result();

                    if ($student_stmt->num_rows === 0) {

                        $error = 'Selected student was not found.';

                        $student_stmt->close();

                    } else {

                        $student_stmt->bind_result($student_name);
                        $student_stmt->fetch();
                        $student_stmt->close();

                        /*
                        |--------------------------------------------------------------------------
                        | Verify Subject
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
                            $selected_subject
                        );

                        $subject_stmt->execute();
                        $subject_stmt->store_result();

                        if ($subject_stmt->num_rows === 0) {

                            $error = 'Selected subject was not found.';

                            $subject_stmt->close();

                        } else {

                            $subject_stmt->close();

                            /*
                            |--------------------------------------------------------------------------
                            | Check Duplicate Marks
                            |--------------------------------------------------------------------------
                            */

                            $duplicate_stmt = $conn->prepare(
                                "SELECT id
                                 FROM marks
                                 WHERE student_id = ?
                                   AND subject_name = ?
                                 LIMIT 1"
                            );

                            $duplicate_stmt->bind_param(
                                'is',
                                $selected_student_id,
                                $selected_subject
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
                                | Insert Marks
                                |--------------------------------------------------------------------------
                                */

                                $insert_stmt = $conn->prepare(
                                    "INSERT INTO marks
                                    (
                                        student_id,
                                        student_name,
                                        subject_name,
                                        marks
                                    )
                                    VALUES (?, ?, ?, ?)"
                                );

                                $insert_stmt->bind_param(
                                    'issi',
                                    $selected_student_id,
                                    $student_name,
                                    $selected_subject,
                                    $marks
                                );

                                $insert_stmt->execute();

                                $insert_stmt->close();

                                /*
                                |--------------------------------------------------------------------------
                                | Success - Post/Redirect/Get
                                |--------------------------------------------------------------------------
                                */

                                $_SESSION['mark_add_success'] =
                                    'Marks added successfully.';

                                $_SESSION['csrf_token'] =
                                    bin2hex(random_bytes(32));

                                header('Location: marks.php');
                                exit;
                            }
                        }
                    }

                } catch (mysqli_sql_exception $e) {

                    error_log(
                        'Add marks database error: ' . $e->getMessage()
                    );

                    if ((int) $e->getCode() === 1062) {

                        $error =
                            'Marks for this student and subject already exist.';

                    } else {

                        $error =
                            'Unable to save marks right now. Please try again later.';
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Students and Subjects
|--------------------------------------------------------------------------
*/

$students = [];
$subjects = [];

$students_error = '';
$subjects_error = '';

try {

    $student_list_stmt = $conn->prepare(
        "SELECT id, name
         FROM students
         ORDER BY name ASC"
    );

    $student_list_stmt->execute();
    $student_list_stmt->store_result();

    $student_list_stmt->bind_result(
        $student_id,
        $student_name
    );

    while ($student_list_stmt->fetch()) {

        $students[] = [
            'id' => (int) $student_id,
            'name' => (string) $student_name
        ];
    }

    $student_list_stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Add marks student list error: ' . $e->getMessage()
    );

    $students_error =
        'Unable to load students right now.';
}

try {

    $subject_list_stmt = $conn->prepare(
        "SELECT subject_name
         FROM subjects
         ORDER BY subject_name ASC"
    );

    $subject_list_stmt->execute();
    $subject_list_stmt->store_result();

    $subject_list_stmt->bind_result($subject_name);

    while ($subject_list_stmt->fetch()) {

        $subjects[] = (string) $subject_name;
    }

    $subject_list_stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Add marks subject list error: ' . $e->getMessage()
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

    <title>Add Marks | Class Management System</title>

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

        .form-text {
            color: #6b7280;
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

        .empty-warning {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            margin-top: 8px;
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
                <i class="bi bi-bar-chart-fill"></i>
            </div>

            <h1 class="page-title">
                Add Marks
            </h1>

            <p class="page-subtitle">
                Add academic marks for a student and subject.
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

        <?php if ($students_error !== ''): ?>

            <div
                class="alert alert-warning"
                role="alert"
            >
                <i class="bi bi-people-fill me-2"></i>
                <?php echo e($students_error); ?>
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
            action=""
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
                    for="student_id"
                    class="form-label"
                >
                    Student
                </label>

                <select
                    id="student_id"
                    name="student_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Student
                    </option>

                    <?php foreach ($students as $student): ?>

                        <option
                            value="<?php echo (int) $student['id']; ?>"
                            <?php
                            echo (
                                (string) $selected_student_id ===
                                (string) $student['id']
                            ) ? 'selected' : '';
                            ?>
                        >
                            <?php echo e($student['name']); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (count($students) === 0 && $students_error === ''): ?>

                    <div class="empty-warning">
                        <i class="bi bi-info-circle me-1"></i>
                        No students are available. Add a student first.
                    </div>

                <?php endif; ?>

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
                    id="subject_name"
                    name="subject_name"
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
                                $selected_subject === $subject
                            ) ? 'selected' : '';
                            ?>
                        >
                            <?php echo e($subject); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (count($subjects) === 0 && $subjects_error === ''): ?>

                    <div class="empty-warning">
                        <i class="bi bi-info-circle me-1"></i>
                        No subjects are available. Add a subject first.
                    </div>

                <?php endif; ?>

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
                    id="marks"
                    name="marks"
                    class="form-control"
                    min="0"
                    max="100"
                    step="1"
                    inputmode="numeric"
                    value="<?php echo e($entered_marks); ?>"
                    placeholder="Enter marks"
                    required
                >

                <div class="form-text mt-2">
                    Enter a whole number between 0 and 100.
                </div>

            </div>

            <!-- Buttons -->

            <div class="button-group">

                <button
                    type="submit"
                    name="save"
                    class="btn btn-primary px-4"
                    <?php
                    echo (
                        count($students) === 0 ||
                        count($subjects) === 0
                    ) ? 'disabled' : '';
                    ?>
                >
                    <i class="bi bi-check-lg me-1"></i>
                    Save Marks
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