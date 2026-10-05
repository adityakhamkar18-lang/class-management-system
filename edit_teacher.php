<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['admin']) ||
    $_SESSION['admin'] === ''
) {
    header('Location: login.php');
    exit;
}

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
| Initialize Variables
|--------------------------------------------------------------------------
*/
$error = '';

$name = '';
$email = '';
$phone = '';
$subject = '';

/*
|--------------------------------------------------------------------------
| Validate Teacher ID
|--------------------------------------------------------------------------
*/
$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    header('Location: teachers.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Teacher
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conn->prepare(
        'SELECT id, name, email, phone, subject
         FROM teachers
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt->store_result();

    if ($stmt->num_rows === 0) {

        $stmt->close();

        header('Location: teachers.php');
        exit;
    }

    $stmt->bind_result(
        $teacher_id,
        $db_name,
        $db_email,
        $db_phone,
        $db_subject
    );

    $stmt->fetch();
    $stmt->close();

    $name = (string) $db_name;
    $email = (string) $db_email;
    $phone = (string) $db_phone;
    $subject = (string) $db_subject;

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Fetch teacher error: ' .
        $e->getMessage()
    );

    header('Location: teachers.php');
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
    | Verify CSRF Token
    |--------------------------------------------------------------------------
    */
    $submitted_token = (string) ($_POST['csrf_token'] ?? '');

    if (
        $submitted_token === '' ||
        !hash_equals($csrf_token, $submitted_token)
    ) {
        $error =
            'Invalid request. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Form Data
        |--------------------------------------------------------------------------
        */
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | Validate Name
        |--------------------------------------------------------------------------
        */
        if ($name === '') {

            $error = "Please enter the teacher's name.";

        } elseif (strlen($name) < 2) {

            $error =
                'Teacher name must contain at least 2 characters.';

        } elseif (strlen($name) > 100) {

            $error =
                'Teacher name cannot exceed 100 characters.';
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Email
        |--------------------------------------------------------------------------
        */
        elseif ($email === '') {

            $error = "Please enter the teacher's email.";

        } elseif (strlen($email) > 150) {

            $error =
                'Email address cannot exceed 150 characters.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error =
                'Please enter a valid email address.';
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Phone
        |--------------------------------------------------------------------------
        */
        elseif ($phone === '') {

            $error =
                "Please enter the teacher's phone number.";

        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {

            $error =
                'Phone number must contain exactly 10 digits.';
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Subject
        |--------------------------------------------------------------------------
        */
        elseif ($subject === '') {

            $error =
                "Please enter the teacher's subject.";

        } elseif (strlen($subject) < 2) {

            $error =
                'Subject must contain at least 2 characters.';

        } elseif (strlen($subject) > 100) {

            $error =
                'Subject cannot exceed 100 characters.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        try {

            $check_email = $conn->prepare(
                'SELECT id
                 FROM teachers
                 WHERE email = ?
                   AND id != ?
                 LIMIT 1'
            );

            $check_email->bind_param(
                'si',
                $email,
                $id
            );

            $check_email->execute();
            $check_email->store_result();

            if ($check_email->num_rows > 0) {

                $error =
                    'This email address is already used by another teacher.';
            }

            $check_email->close();

        } catch (mysqli_sql_exception $e) {

            error_log(
                'Class Management System - Teacher email check error: ' .
                $e->getMessage()
            );

            $error =
                'Unable to verify the teacher details. Please try again later.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Teacher
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        try {

            $update_stmt = $conn->prepare(
                'UPDATE teachers
                 SET name = ?,
                     email = ?,
                     phone = ?,
                     subject = ?
                 WHERE id = ?'
            );

            $update_stmt->bind_param(
                'ssssi',
                $name,
                $email,
                $phone,
                $subject,
                $id
            );

            $update_stmt->execute();

            $update_stmt->close();

            /*
            |--------------------------------------------------------------------------
            | Prevent Form Resubmission
            |--------------------------------------------------------------------------
            */
            $_SESSION['teacher_update_success'] =
                'Teacher updated successfully.';

            header('Location: teachers.php');
            exit;

        } catch (mysqli_sql_exception $e) {

            error_log(
                'Class Management System - Update teacher error: ' .
                $e->getMessage()
            );

            if ((int) $e->getCode() === 1062) {

                $error =
                    'This email address is already used by another teacher.';

            } else {

                $error =
                    'Unable to update the teacher. Please try again later.';
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

    <title>Edit Teacher - Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #212529;
            color: #ffffff;
            padding-top: 20px;
            overflow-y: auto;
        }

        .sidebar h3 {
            text-align: center;
            margin-bottom: 25px;
            font-weight: bold;
            padding: 0 10px;
        }

        .sidebar a {
            display: block;
            color: #ffffff;
            text-decoration: none;
            padding: 13px 20px;
            font-size: 15px;
        }

        .sidebar a:hover {
            background: #343a40;
        }

        .sidebar .active {
            background: #0d6efd;
        }

        .main-content {
            margin-left: 240px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background: #ffffff;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .page-header h2 {
            margin: 0;
            font-weight: bold;
        }

        .page-header p {
            margin: 5px 0 0;
            color: #6c757d;
        }

        .form-card {
            max-width: 750px;
            margin: auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.10);
        }

        .form-label {
            font-weight: bold;
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
        }

        @media (max-width: 768px) {

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                overflow-y: visible;
            }

            .sidebar h3 {
                padding-top: 10px;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .form-card {
                padding: 20px;
            }
        }

    </style>

</head>

<body>

<div class="sidebar">

    <h3>Class Management</h3>

    <a href="dashboard.php">
        🏠 Dashboard
    </a>

    <a href="students.php">
        👨‍🎓 Students
    </a>

    <a href="teachers.php" class="active">
        👨‍🏫 Teachers
    </a>

    <a href="subjects.php">
        📚 Subjects
    </a>

    <a href="attendance.php">
        📝 Attendance
    </a>

    <a href="marks.php">
        📊 Marks
    </a>

    <a href="reports.php">
        📄 Reports
    </a>

    <a href="logout.php">
        🚪 Logout
    </a>

</div>

<div class="main-content">

    <div class="page-header">

        <h2>✏️ Edit Teacher</h2>

        <p>
            Update the teacher information below.
        </p>

    </div>

    <div class="form-card">

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
            action="edit_teacher.php?id=<?php echo (int) $id; ?>"
        >

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

            <div class="mb-3">

                <label
                    for="name"
                    class="form-label"
                >
                    Teacher Name
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
                    maxlength="100"
                    autocomplete="name"
                    required
                >

            </div>

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
                    maxlength="150"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="mb-3">

                <label
                    for="phone"
                    class="form-label"
                >
                    Phone Number
                </label>

                <input
                    type="text"
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
                    maxlength="10"
                    pattern="[0-9]{10}"
                    inputmode="numeric"
                    autocomplete="tel"
                    required
                >

            </div>

            <div class="mb-4">

                <label
                    for="subject"
                    class="form-label"
                >
                    Subject
                </label>

                <input
                    type="text"
                    id="subject"
                    name="subject"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $subject,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    maxlength="100"
                    required
                >

            </div>

            <div class="d-flex gap-2 flex-wrap">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    💾 Update Teacher
                </button>

                <a
                    href="teachers.php"
                    class="btn btn-secondary"
                >
                    ← Back
                </a>

            </div>

        </form>

    </div>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>