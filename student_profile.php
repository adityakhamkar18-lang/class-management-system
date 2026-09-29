<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("config.php");

/*
|--------------------------------------------------------------------------
| Validate Student ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    header("Location: students.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Get Student Details
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, roll_no, name, email, phone, gender, class_name
     FROM students
     WHERE id = ?"
);

if (!$stmt) {
    die("Unable to load student report.");
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$student) {
    header("Location: students.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Attendance
|--------------------------------------------------------------------------
*/
$attendance_records = [];

$total_classes = 0;
$present_count = 0;
$absent_count = 0;

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, attendance_date, status
     FROM attendance
     WHERE student_id = ?
     ORDER BY attendance_date DESC, id DESC"
);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($attendance = mysqli_fetch_assoc($result)) {

        $attendance_records[] = $attendance;

        $total_classes++;

        if (strcasecmp(trim($attendance['status']), 'Present') === 0) {
            $present_count++;
        } elseif (strcasecmp(trim($attendance['status']), 'Absent') === 0) {
            $absent_count++;
        }
    }

    mysqli_stmt_close($stmt);
}


/*
|--------------------------------------------------------------------------
| Attendance Percentage
|--------------------------------------------------------------------------
*/
$attendance_percentage = 0;

if ($total_classes > 0) {
    $attendance_percentage =
        ($present_count / $total_classes) * 100;
}


/*
|--------------------------------------------------------------------------
| Marks
|--------------------------------------------------------------------------
*/
$marks_records = [];

$total_marks = 0;
$marks_count = 0;

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, subject_name, marks
     FROM marks
     WHERE student_id = ?
     ORDER BY id DESC"
);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($mark = mysqli_fetch_assoc($result)) {

        $marks_records[] = $mark;

        $total_marks += (float)$mark['marks'];

        $marks_count++;
    }

    mysqli_stmt_close($stmt);
}


/*
|--------------------------------------------------------------------------
| Marks Average
|--------------------------------------------------------------------------
*/
$marks_average = 0;

if ($marks_count > 0) {
    $marks_average = $total_marks / $marks_count;
}


/*
|--------------------------------------------------------------------------
| Student Initial
|--------------------------------------------------------------------------
*/
$student_initial = strtoupper(
    substr(trim($student['name']), 0, 1)
);

if ($student_initial === '') {
    $student_initial = '?';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Student Report -
        <?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background-color: #f5f7fb;
        }

        .page-title {
            font-weight: 700;
        }

        .profile-card,
        .stat-card,
        .table-card {
            background: #ffffff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .profile-header {
            background: #0d6efd;
            color: #ffffff;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }

        .profile-icon {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: #ffffff;
            color: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .stat-card {
            padding: 20px;
            text-align: center;
            height: 100%;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
        }

        .info-label {
            color: #777777;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 17px;
            word-break: break-word;
        }

        .table-card {
            padding: 20px;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 20px;
        }

        .attendance-progress {
            height: 10px;
            border-radius: 10px;
        }

        @media (max-width: 576px) {

            .page-header {
                align-items: flex-start !important;
            }

            .page-header .btn {
                margin-top: 5px;
            }

            .profile-header {
                padding: 25px 20px;
            }

            .table-card {
                padding: 15px;
            }

        }

    </style>

</head>

<body>

<div class="container mt-4 mb-5">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

        <div>

            <h2 class="page-title mb-1">
                Student Profile & Report
            </h2>

            <p class="text-muted mb-0">
                Complete academic information
            </p>

        </div>

        <div class="d-flex gap-2 flex-wrap">

            <a
                href="students.php"
                class="btn btn-secondary"
            >
                ← Students
            </a>

            <a
                href="edit_student.php?id=<?php echo (int)$student['id']; ?>"
                class="btn btn-warning"
            >
                Edit Student
            </a>

        </div>

    </div>


    <!-- =====================================================
         STUDENT PROFILE
    ====================================================== -->

    <div class="card profile-card mb-4">

        <div class="profile-header">

            <div class="profile-icon">

                <?php echo htmlspecialchars($student_initial, ENT_QUOTES, 'UTF-8'); ?>

            </div>

            <h3 class="mb-1">

                <?php
                echo htmlspecialchars(
                    $student['name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </h3>

            <p class="mb-0">

                Roll No:
                <?php
                echo htmlspecialchars(
                    $student['roll_no'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </p>

        </div>


        <div class="card-body p-4">

            <h4 class="section-title">
                Personal Information
            </h4>

            <div class="row g-4">


                <div class="col-md-6">

                    <div class="info-label">
                        Roll Number
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student['roll_no'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Full Name
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student['email'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Phone
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student['phone'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Gender
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student['gender'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="info-label">
                        Class
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $student['class_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ATTENDANCE STATISTICS
    ====================================================== -->

    <div class="row g-4 mb-4">


        <!-- Total Classes -->

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Total Classes
                </h6>

                <div class="stat-number">
                    <?php echo $total_classes; ?>
                </div>

            </div>

        </div>


        <!-- Present -->

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Present
                </h6>

                <div class="stat-number text-success">
                    <?php echo $present_count; ?>
                </div>

            </div>

        </div>


        <!-- Absent -->

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Absent
                </h6>

                <div class="stat-number text-danger">
                    <?php echo $absent_count; ?>
                </div>

            </div>

        </div>


        <!-- Attendance Percentage -->

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Attendance %
                </h6>

                <div class="stat-number text-primary">

                    <?php
                    echo number_format(
                        $attendance_percentage,
                        1
                    );
                    ?>%

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ATTENDANCE PROGRESS
    ====================================================== -->

    <div class="card table-card mb-4">

        <h5 class="section-title">
            Attendance Progress
        </h5>

        <div class="progress attendance-progress">

            <div
                class="progress-bar
                <?php
                echo ($attendance_percentage >= 75)
                    ? 'bg-success'
                    : 'bg-danger';
                ?>"
                role="progressbar"
                style="width: <?php echo min(100, max(0, $attendance_percentage)); ?>%;"
                aria-valuenow="<?php echo number_format($attendance_percentage, 1); ?>"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>

        </div>

        <div class="d-flex justify-content-between mt-2">

            <small class="text-muted">
                0%
            </small>

            <small class="text-muted">
                <?php
                echo number_format(
                    $attendance_percentage,
                    1
                );
                ?>%
            </small>

            <small class="text-muted">
                100%
            </small>

        </div>

    </div>


    <!-- =====================================================
         MARKS STATISTICS
    ====================================================== -->

    <div class="row g-4 mb-4">


        <!-- Total Marks -->

        <div class="col-md-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Total Marks
                </h6>

                <div class="stat-number text-info">

                    <?php
                    echo number_format(
                        $total_marks,
                        2
                    );
                    ?>

                </div>

            </div>

        </div>


        <!-- Average Marks -->

        <div class="col-md-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Average Marks
                </h6>

                <div class="stat-number text-success">

                    <?php
                    echo number_format(
                        $marks_average,
                        2
                    );
                    ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ATTENDANCE RECORDS
    ====================================================== -->

    <div class="table-card mb-4">

        <h4 class="section-title">
            📅 Attendance Records
        </h4>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th>ID</th>

                        <th>Date</th>

                        <th>Status</th>

                    </tr>

                </thead>

                <tbody>


                <?php if (count($attendance_records) > 0) { ?>

                    <?php foreach ($attendance_records as $attendance) { ?>

                        <tr>

                            <td>

                                <?php
                                echo (int)$attendance['id'];
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $attendance['attendance_date'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </td>


                            <td>

                                <?php

                                if (
                                    strcasecmp(
                                        trim($attendance['status']),
                                        'Present'
                                    ) === 0
                                ) {

                                    echo '<span class="badge bg-success">
                                            Present
                                          </span>';

                                } elseif (
                                    strcasecmp(
                                        trim($attendance['status']),
                                        'Absent'
                                    ) === 0
                                ) {

                                    echo '<span class="badge bg-danger">
                                            Absent
                                          </span>';

                                } else {

                                    echo '<span class="badge bg-secondary">'
                                        . htmlspecialchars(
                                            $attendance['status'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        . '</span>';
                                }

                                ?>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted p-4"
                        >
                            No attendance records found.
                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         MARKS RECORDS
    ====================================================== -->

    <div class="table-card">

        <h4 class="section-title">
            📊 Marks Records
        </h4>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th>ID</th>

                        <th>Subject</th>

                        <th>Marks</th>

                    </tr>

                </thead>

                <tbody>


                <?php if (count($marks_records) > 0) { ?>

                    <?php foreach ($marks_records as $mark) { ?>

                        <tr>

                            <td>

                                <?php
                                echo (int)$mark['id'];
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $mark['subject_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </td>


                            <td>

                                <span class="badge bg-primary">

                                    <?php
                                    echo htmlspecialchars(
                                        $mark['marks'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </span>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted p-4"
                        >
                            No marks records found.
                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


</div>

</body>

</html>

