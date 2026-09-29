<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("config.php");

/*
|--------------------------------------------------------------------------
| Fetch Students
|--------------------------------------------------------------------------
*/
$students_query = mysqli_query(
    $conn,
    "SELECT id, name FROM students ORDER BY name ASC"
);

if (!$students_query) {
    die("Unable to load students.");
}

/*
|--------------------------------------------------------------------------
| Save Attendance
|--------------------------------------------------------------------------
*/
if (isset($_POST['save'])) {

    $student_id = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    $attendance_date = trim($_POST['attendance_date'] ?? '');
    $status = trim($_POST['status'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    if (!$student_id || $student_id <= 0) {

        $error = "Please select a valid student.";

    } elseif ($attendance_date === '') {

        $error = "Please select an attendance date.";

    } elseif (!DateTime::createFromFormat('Y-m-d', $attendance_date)) {

        $error = "Please enter a valid attendance date.";

    } elseif (!in_array($status, ['Present', 'Absent'], true)) {

        $error = "Please select a valid attendance status.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Student Exists
        |--------------------------------------------------------------------------
        */
        $student_stmt = mysqli_prepare(
            $conn,
            "SELECT id, name
             FROM students
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $student_stmt,
            "i",
            $student_id
        );

        mysqli_stmt_execute($student_stmt);

        $student_result = mysqli_stmt_get_result($student_stmt);
        $student = mysqli_fetch_assoc($student_result);

        mysqli_stmt_close($student_stmt);

        if (!$student) {

            $error = "Selected student does not exist.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Attendance
            |--------------------------------------------------------------------------
            */
            $check_stmt = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM attendance
                 WHERE student_id = ?
                 AND attendance_date = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $check_stmt,
                "is",
                $student_id,
                $attendance_date
            );

            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) > 0) {

                $error = "Attendance for this student has already been marked for this date.";

            }

            mysqli_stmt_close($check_stmt);

            /*
            |--------------------------------------------------------------------------
            | Insert Attendance
            |--------------------------------------------------------------------------
            */
            if (!isset($error)) {

                $student_name = $student['name'];

                $insert_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO attendance
                    (student_id, student_name, attendance_date, status)
                    VALUES (?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "isss",
                    $student_id,
                    $student_name,
                    $attendance_date,
                    $status
                );

                if (mysqli_stmt_execute($insert_stmt)) {

                    mysqli_stmt_close($insert_stmt);

                    header("Location: attendance.php");
                    exit();

                } else {

                    $error = "Unable to save attendance. Please try again.";

                    mysqli_stmt_close($insert_stmt);
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Preserve Form Values
|--------------------------------------------------------------------------
*/
$selected_student = $_POST['student_id'] ?? '';
$selected_date = $_POST['attendance_date'] ?? '';
$selected_status = $_POST['status'] ?? 'Present';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mark Attendance</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body>

<div class="container mt-5 mb-5">

    <div class="row justify-content-center">

        <div class="col-md-7 col-lg-6">

            <div class="card shadow">

                <div class="card-header bg-danger text-white">

                    <h4 class="mb-0">
                        Mark Attendance
                    </h4>

                </div>

                <div class="card-body">

                    <?php if (isset($error)) { ?>

                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>

                    <?php } ?>

                    <form method="POST">

                        <!-- Student -->
                        <div class="mb-3">

                            <label class="form-label">
                                Student
                            </label>

                            <select
                                name="student_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Student
                                </option>

                                <?php while ($student_row = mysqli_fetch_assoc($students_query)) { ?>

                                    <option
                                        value="<?php echo (int)$student_row['id']; ?>"
                                        <?php
                                        if ((string)$selected_student === (string)$student_row['id']) {
                                            echo 'selected';
                                        }
                                        ?>
                                    >
                                        <?php echo htmlspecialchars($student_row['name']); ?>
                                    </option>

                                <?php } ?>

                            </select>

                        </div>

                        <!-- Date -->
                        <div class="mb-3">

                            <label class="form-label">
                                Attendance Date
                            </label>

                            <input
                                type="date"
                                name="attendance_date"
                                class="form-control"
                                value="<?php echo htmlspecialchars($selected_date); ?>"
                                max="<?php echo date('Y-m-d'); ?>"
                                required
                            >

                        </div>

                        <!-- Status -->
                        <div class="mb-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-select"
                                required
                            >

                                <option
                                    value="Present"
                                    <?php echo ($selected_status === 'Present') ? 'selected' : ''; ?>
                                >
                                    Present
                                </option>

                                <option
                                    value="Absent"
                                    <?php echo ($selected_status === 'Absent') ? 'selected' : ''; ?>
                                >
                                    Absent
                                </option>

                            </select>

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                name="save"
                                class="btn btn-success"
                            >
                                Save Attendance
                            </button>

                            <a
                                href="attendance.php"
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

</div>

</body>

</html>