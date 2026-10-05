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
| Database
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/
$total_students = 0;
$total_teachers = 0;
$total_subjects = 0;
$total_marks = 0;
$total_attendance = 0;
$total_present = 0;
$total_absent = 0;

try {

    /*
    |--------------------------------------------------------------------------
    | Students
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare('SELECT COUNT(*) FROM students');
    $stmt->execute();
    $stmt->bind_result($total_students);
    $stmt->fetch();
    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Teachers
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare('SELECT COUNT(*) FROM teachers');
    $stmt->execute();
    $stmt->bind_result($total_teachers);
    $stmt->fetch();
    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Subjects
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare('SELECT COUNT(*) FROM subjects');
    $stmt->execute();
    $stmt->bind_result($total_subjects);
    $stmt->fetch();
    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Marks
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare('SELECT COUNT(*) FROM marks');
    $stmt->execute();
    $stmt->bind_result($total_marks);
    $stmt->fetch();
    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare(
        "SELECT
            COUNT(*),
            COALESCE(SUM(status = 'Present'), 0),
            COALESCE(SUM(status = 'Absent'), 0)
         FROM attendance"
    );

    $stmt->execute();

    $stmt->bind_result(
        $total_attendance,
        $total_present,
        $total_absent
    );

    $stmt->fetch();
    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Reports statistics error: ' . $e->getMessage()
    );

    $total_students = 0;
    $total_teachers = 0;
    $total_subjects = 0;
    $total_marks = 0;
    $total_attendance = 0;
    $total_present = 0;
    $total_absent = 0;
}

/*
|--------------------------------------------------------------------------
| Attendance Percentage
|--------------------------------------------------------------------------
*/
$attendance_percentage = $total_attendance > 0
    ? round(($total_present / $total_attendance) * 100, 2)
    : 0;

/*
|--------------------------------------------------------------------------
| Student Performance
|--------------------------------------------------------------------------
*/
$students = [];

try {

    $stmt = $conn->prepare(
        "SELECT
            s.id,
            s.roll_no,
            s.name,
            s.class_name,
            COALESCE(AVG(m.marks), 0) AS average_marks,
            COUNT(m.id) AS subject_count
         FROM students s
         LEFT JOIN marks m
            ON m.student_id = s.id
         GROUP BY
            s.id,
            s.roll_no,
            s.name,
            s.class_name
         ORDER BY s.name ASC"
    );

    $stmt->execute();

    $stmt->bind_result(
        $student_id,
        $roll_no,
        $student_name,
        $class_name,
        $average_marks,
        $subject_count
    );

    while ($stmt->fetch()) {

        $students[] = [
            'id' => (int) $student_id,
            'roll_no' => (string) $roll_no,
            'name' => (string) $student_name,
            'class_name' => (string) ($class_name ?? ''),
            'average_marks' => (float) $average_marks,
            'subject_count' => (int) $subject_count
        ];
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Student performance report error: ' . $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Overall Marks Statistics
|--------------------------------------------------------------------------
*/
$average_marks = 0;
$highest_marks = 0;
$lowest_marks = 0;

try {

    $stmt = $conn->prepare(
        'SELECT
            COALESCE(AVG(marks), 0),
            COALESCE(MAX(marks), 0),
            COALESCE(MIN(marks), 0)
         FROM marks'
    );

    $stmt->execute();

    $stmt->bind_result(
        $average_marks,
        $highest_marks,
        $lowest_marks
    );

    $stmt->fetch();
    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Marks summary error: ' . $e->getMessage()
    );
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

    <title>Reports | Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #212529;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 15px;
            z-index: 1000;
        }

        .sidebar .logo {
            width: 55px;
            height: 55px;
            object-fit: contain;
            display: block;
            margin: 0 auto 10px;
        }

        .sidebar h4 {
            color: #fff;
            text-align: center;
            margin-bottom: 25px;
            font-size: 19px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ced4da;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: 0.2s ease;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #343a40;
            color: #fff;
        }

        .sidebar a i {
            width: 20px;
        }

        .main {
            margin-left: 240px;
            padding: 30px;
        }

        .page-header {
            background: #fff;
            border-radius: 12px;
            padding: 22px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .page-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .page-header p {
            margin: 5px 0 0;
            color: #6c757d;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .stat-card i {
            font-size: 28px;
        }

        .stat-card h3 {
            margin: 10px 0 0;
            font-weight: 700;
        }

        .stat-card p {
            margin: 3px 0 0;
            color: #6c757d;
        }

        .report-box {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table-responsive {
            border-radius: 8px;
        }

        table {
            margin-bottom: 0 !important;
        }

        .badge-average {
            font-size: 13px;
        }

        .quick-link {
            text-decoration: none;
        }

        @media (max-width: 768px) {

            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

            .sidebar a {
                display: inline-flex;
                margin-right: 4px;
            }
        }

    </style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <img
        src="images/logo.png"
        alt="Class Management System"
        class="logo"
    >

    <h4>Class Management</h4>

    <a href="dashboard.php">
        <i class="bi bi-speedometer2"></i>
        Dashboard
    </a>

    <a href="students.php">
        <i class="bi bi-people"></i>
        Students
    </a>

    <a href="teachers.php">
        <i class="bi bi-person-badge"></i>
        Teachers
    </a>

    <a href="subjects.php">
        <i class="bi bi-book"></i>
        Subjects
    </a>

    <a href="attendance.php">
        <i class="bi bi-calendar-check"></i>
        Attendance
    </a>

    <a href="marks.php">
        <i class="bi bi-bar-chart"></i>
        Marks
    </a>

    <a href="reports.php" class="active">
        <i class="bi bi-file-earmark-bar-graph"></i>
        Reports
    </a>

    <a href="logout.php">
        <i class="bi bi-box-arrow-right"></i>
        Logout
    </a>

</aside>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="main">

    <div class="page-header">

        <h2>
            <i class="bi bi-file-earmark-bar-graph"></i>
            Academic Reports
        </h2>

        <p>
            View an overview of students, marks and attendance.
        </p>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="row g-4">

        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <i class="bi bi-people"></i>

                <h3>
                    <?= $total_students ?>
                </h3>

                <p>
                    Total Students
                </p>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <i class="bi bi-person-badge"></i>

                <h3>
                    <?= $total_teachers ?>
                </h3>

                <p>
                    Total Teachers
                </p>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <i class="bi bi-book"></i>

                <h3>
                    <?= $total_subjects ?>
                </h3>

                <p>
                    Total Subjects
                </p>

            </div>

        </div>


        <div class="col-md-6 col-xl-3">

            <div class="stat-card">

                <i class="bi bi-pencil-square"></i>

                <h3>
                    <?= $total_marks ?>
                </h3>

                <p>
                    Marks Records
                </p>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ATTENDANCE + MARKS SUMMARY
    ====================================================== -->

    <div class="row g-4 mt-1">

        <div class="col-lg-6">

            <div class="report-box">

                <h4 class="mb-4">
                    <i class="bi bi-calendar-check"></i>
                    Attendance Summary
                </h4>

                <div class="row g-3">

                    <div class="col-4">

                        <div class="text-center">

                            <h3>
                                <?= $total_attendance ?>
                            </h3>

                            <small class="text-muted">
                                Total
                            </small>

                        </div>

                    </div>

                    <div class="col-4">

                        <div class="text-center">

                            <h3>
                                <?= $total_present ?>
                            </h3>

                            <small class="text-muted">
                                Present
                            </small>

                        </div>

                    </div>

                    <div class="col-4">

                        <div class="text-center">

                            <h3>
                                <?= $total_absent ?>
                            </h3>

                            <small class="text-muted">
                                Absent
                            </small>

                        </div>

                    </div>

                </div>

                <hr>

                <div class="text-center">

                    <h3>
                        <?= e((string) $attendance_percentage) ?>%
                    </h3>

                    <p class="text-muted mb-3">
                        Overall Attendance
                    </p>

                    <a
                        href="attendance_report.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-file-earmark-text"></i>
                        Attendance Report
                    </a>

                </div>

            </div>

        </div>


        <div class="col-lg-6">

            <div class="report-box">

                <h4 class="mb-4">
                    <i class="bi bi-bar-chart"></i>
                    Marks Summary
                </h4>

                <div class="row g-3">

                    <div class="col-4">

                        <div class="text-center">

                            <h3>
                                <?= number_format((float) $average_marks, 2) ?>
                            </h3>

                            <small class="text-muted">
                                Average
                            </small>

                        </div>

                    </div>

                    <div class="col-4">

                        <div class="text-center">

                            <h3>
                                <?= (int) $highest_marks ?>
                            </h3>

                            <small class="text-muted">
                                Highest
                            </small>

                        </div>

                    </div>

                    <div class="col-4">

                        <div class="text-center">

                            <h3>
                                <?= (int) $lowest_marks ?>
                            </h3>

                            <small class="text-muted">
                                Lowest
                            </small>

                        </div>

                    </div>

                </div>

                <hr>

                <div class="text-center">

                    <p class="text-muted">
                        View individual student performance below.
                    </p>

                    <a
                        href="marks.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-bar-chart-line"></i>
                        Manage Marks
                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         STUDENT PERFORMANCE
    ====================================================== -->

    <div class="report-box">

        <div
            class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4"
        >

            <div>

                <h4 class="mb-1">
                    <i class="bi bi-mortarboard"></i>
                    Student Performance
                </h4>

                <p class="text-muted mb-0">
                    Academic performance based on recorded marks.
                </p>

            </div>

            <a
                href="students.php"
                class="btn btn-outline-primary"
            >
                <i class="bi bi-people"></i>
                Students
            </a>

        </div>


        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th>#</th>

                        <th>Roll No.</th>

                        <th>Student Name</th>

                        <th>Class</th>

                        <th>Subjects</th>

                        <th>Average Marks</th>

                        <th>Report</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($students)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="text-center text-muted py-4"
                        >
                            No student records found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($students as $index => $student): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= e($student['roll_no']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= e($student['name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= e($student['class_name']) ?: '-' ?>
                            </td>

                            <td>
                                <?= $student['subject_count'] ?>
                            </td>

                            <td>

                                <span class="badge text-bg-primary badge-average">

                                    <?= number_format(
                                        $student['average_marks'],
                                        2
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <a
                                    href="student_report.php?student_id=<?= $student['id'] ?>"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-eye"></i>
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         QUICK LINKS
    ====================================================== -->

    <div class="report-box">

        <h4 class="mb-4">
            <i class="bi bi-lightning"></i>
            Quick Reports
        </h4>

        <div class="row g-3">

            <div class="col-md-4">

                <a
                    href="attendance_report.php"
                    class="btn btn-outline-primary w-100 py-3 quick-link"
                >
                    <i class="bi bi-calendar-check"></i>
                    Attendance Report
                </a>

            </div>

            <div class="col-md-4">

                <a
                    href="marks.php"
                    class="btn btn-outline-primary w-100 py-3 quick-link"
                >
                    <i class="bi bi-bar-chart"></i>
                    Marks Report
                </a>

            </div>

            <div class="col-md-4">

                <a
                    href="students.php"
                    class="btn btn-outline-primary w-100 py-3 quick-link"
                >
                    <i class="bi bi-people"></i>
                    Student Records
                </a>

            </div>

        </div>

    </div>


    <footer class="text-center text-muted py-4">

        © <?= date('Y') ?> Class Management System

    </footer>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>