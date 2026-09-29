<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once "config.php";

$admin_name = $_SESSION['admin'] ?? 'Administrator';

$admin_name = htmlspecialchars(
    $admin_name,
    ENT_QUOTES,
    'UTF-8'
);

$search = trim($_GET['search'] ?? '');


// ----------------------------------------------------
// Fetch Attendance Records
// ----------------------------------------------------

if ($search !== '') {

    $search_like = '%' . $search . '%';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            student_id,
            student_name,
            attendance_date,
            status
         FROM attendance
         WHERE student_name LIKE ?
            OR status LIKE ?
            OR attendance_date LIKE ?
         ORDER BY attendance_date DESC, id DESC"
    );

    if (!$stmt) {
        die("Unable to load attendance records.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $search_like,
        $search_like,
        $search_like
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            student_id,
            student_name,
            attendance_date,
            status
         FROM attendance
         ORDER BY attendance_date DESC, id DESC"
    );

    if (!$stmt) {
        die("Unable to load attendance records.");
    }

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
}


// ----------------------------------------------------
// Count Records
// ----------------------------------------------------

$total_records = 0;

if ($result) {
    $total_records = mysqli_num_rows($result);
}


// ----------------------------------------------------
// Attendance Statistics
// ----------------------------------------------------

$present_count = 0;
$absent_count = 0;

if ($result && $total_records > 0) {

    mysqli_data_seek($result, 0);

    while ($stat_row = mysqli_fetch_assoc($result)) {

        if ($stat_row['status'] === 'Present') {
            $present_count++;
        }

        if ($stat_row['status'] === 'Absent') {
            $absent_count++;
        }
    }

    mysqli_data_seek($result, 0);
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

    <title>
        Attendance | Class Management System
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f7fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #212529;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #111827;
            color: white;
            padding: 20px 14px;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 18px;
        }

        .sidebar-brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
        }

        .sidebar-brand h5 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .sidebar-brand small {
            color: #9ca3af;
            font-size: 11px;
        }

        .nav-title {
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 8px 12px;
            margin-top: 8px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #d1d5db;
            text-decoration: none;
            padding: 11px 13px;
            margin: 4px 0;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a i {
            font-size: 17px;
            width: 20px;
        }

        .sidebar a:hover {
            background: #1f2937;
            color: white;
        }

        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .sidebar .logout-link {
            margin-top: 20px;
            color: #fca5a5;
        }

        .sidebar .logout-link:hover {
            background: #7f1d1d;
            color: white;
        }


        /* MAIN */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }


        /* TOPBAR */

        .topbar {
            height: 70px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .topbar-title {
            font-size: 18px;
            font-weight: 700;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #374151;
            font-size: 14px;
        }

        .admin-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e8f0ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }


        /* CONTENT */

        .content-area {
            padding: 30px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .page-subtitle {
            color: #6b7280;
            margin: 0;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }


        /* STAT CARDS */

        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            margin-bottom: 12px;
        }

        .stat-icon.blue {
            background: #e8f0ff;
            color: #2563eb;
        }

        .stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-label {
            color: #6b7280;
            font-size: 13px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-top: 3px;
        }


        /* SEARCH */

        .search-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            margin-top: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .search-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }

        .search-input {
            height: 45px;
            border-radius: 8px;
        }

        .search-btn {
            height: 45px;
            border-radius: 8px;
        }

        .search-info {
            margin-top: 15px;
            font-size: 14px;
        }


        /* TABLE */

        .table-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h5 {
            margin: 0;
            font-weight: 700;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .table {
            margin: 0;
            min-width: 900px;
        }

        .table thead th {
            background: #111827;
            color: white;
            border: none;
            padding: 14px 16px;
            font-size: 13px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: 14px;
            border-color: #eef0f3;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .student-icon {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: #eff6ff;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 9px;
        }

        .student-name {
            font-weight: 700;
            color: #111827;
        }

        .date-text {
            color: #4b5563;
            white-space: nowrap;
        }

        .status-badge {
            min-width: 80px;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        .action-buttons {
            white-space: nowrap;
        }

        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 3px;
        }


        /* EMPTY */

        .empty-state {
            text-align: center;
            padding: 60px 20px !important;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 45px;
            color: #9ca3af;
            margin-bottom: 12px;
        }

        .empty-state h5 {
            color: #374151;
            margin-bottom: 6px;
        }


        /* FOOTER */

        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
            padding: 25px 20px;
        }


        /* RESPONSIVE */

        @media (max-width: 992px) {

            .sidebar {
                width: 210px;
            }

            .main-content {
                margin-left: 210px;
            }

            .content-area {
                padding: 20px;
            }

        }


        @media (max-width: 768px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .topbar {
                padding: 0 18px;
            }

            .content-area {
                padding: 18px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            .admin-profile span {
                display: none;
            }

        }


        @media (max-width: 576px) {

            .page-title {
                font-size: 23px;
            }

            .topbar-title {
                font-size: 16px;
            }

            .search-card {
                padding: 15px;
            }

            .table-header {
                padding: 15px;
            }

        }

    </style>

</head>


<body>


<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="sidebar-brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>

        <div>

            <h5>Class Management</h5>

            <small>
                Admin Panel
            </small>

        </div>

    </div>


    <div class="nav-title">
        Main Menu
    </div>


    <a href="dashboard.php">
        <i class="bi bi-grid-1x2-fill"></i>
        Dashboard
    </a>


    <a href="students.php">
        <i class="bi bi-people-fill"></i>
        Students
    </a>


    <a href="teachers.php">
        <i class="bi bi-person-workspace"></i>
        Teachers
    </a>


    <a href="subjects.php">
        <i class="bi bi-book-fill"></i>
        Subjects
    </a>


    <a href="attendance.php" class="active">
        <i class="bi bi-calendar-check-fill"></i>
        Attendance
    </a>


    <a href="marks.php">
        <i class="bi bi-bar-chart-fill"></i>
        Marks
    </a>


    <a href="reports.php">
        <i class="bi bi-file-earmark-bar-graph-fill"></i>
        Reports
    </a>


    <div class="nav-title">
        Account
    </div>


    <a href="logout.php" class="logout-link">
        <i class="bi bi-box-arrow-right"></i>
        Logout
    </a>

</aside>


<!-- MAIN -->

<main class="main-content">


    <!-- TOPBAR -->

    <div class="topbar">

        <div class="topbar-title">
            Attendance Management
        </div>


        <div class="admin-profile">

            <div class="admin-icon">
                <i class="bi bi-person-fill"></i>
            </div>

            <span>
                <?php echo $admin_name; ?>
            </span>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content-area">


        <!-- HEADER -->

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    Attendance Management
                </h1>

                <p class="page-subtitle">
                    Track and manage student attendance records.
                </p>

            </div>


            <div class="header-actions">

                <a
                    href="dashboard.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left"></i>
                    Dashboard
                </a>


                <a
                    href="mark_attendance.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-plus-lg"></i>
                    Mark Attendance
                </a>

            </div>

        </div>


        <!-- STATISTICS -->

        <div class="row g-3">


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon blue">

                        <i class="bi bi-calendar-check"></i>

                    </div>

                    <div class="stat-label">
                        Total Records
                    </div>

                    <div class="stat-number">
                        <?php echo $total_records; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon green">

                        <i class="bi bi-check-circle-fill"></i>

                    </div>

                    <div class="stat-label">
                        Present Records
                    </div>

                    <div class="stat-number">
                        <?php echo $present_count; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat-card">

                    <div class="stat-icon red">

                        <i class="bi bi-x-circle-fill"></i>

                    </div>

                    <div class="stat-label">
                        Absent Records
                    </div>

                    <div class="stat-number">
                        <?php echo $absent_count; ?>
                    </div>

                </div>

            </div>


        </div>


        <!-- SEARCH -->

        <div class="search-card">

            <form
                method="GET"
                action="attendance.php"
            >

                <div class="row g-3 align-items-end">


                    <div class="col-lg-10">

                        <label class="search-label">
                            Search Attendance
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form-control search-input"
                            placeholder="Search by student name, date or status..."
                            value="<?php

                            echo htmlspecialchars(
                                $search,
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>"
                        >

                    </div>


                    <div class="col-lg-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100 search-btn"
                        >
                            <i class="bi bi-search"></i>
                            Search
                        </button>

                    </div>


                </div>

            </form>


            <?php if ($search !== '') { ?>

                <div class="search-info">

                    <span class="text-muted">
                        Search results for
                    </span>

                    <strong>
                        "<?php

                        echo htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>"
                    </strong>


                    <span class="badge bg-primary ms-2">

                        <?php echo $total_records; ?>

                        found

                    </span>


                    <a
                        href="attendance.php"
                        class="btn btn-sm btn-outline-secondary ms-2"
                    >
                        <i class="bi bi-x-lg"></i>
                        Clear
                    </a>

                </div>

            <?php } ?>

        </div>


        <!-- TABLE -->

        <div class="table-card">


            <div class="table-header">

                <h5>

                    <i class="bi bi-calendar-check me-2"></i>

                    Attendance Records

                </h5>


                <span class="badge bg-light text-dark border">

                    <?php echo $total_records; ?>

                    <?php echo ($total_records == 1)
                        ? 'Record'
                        : 'Records'; ?>

                </span>

            </div>


            <div class="table-wrapper">

                <table class="table">


                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Student
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    if ($result && $total_records > 0) {

                        while ($row = mysqli_fetch_assoc($result)) {

                            $record_id =
                                (int)$row['id'];

                            $student_id =
                                (int)$row['student_id'];

                            $student_name =
                                $row['student_name'];

                            $attendance_date =
                                $row['attendance_date'];

                            $status =
                                $row['status'];

                    ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <strong>
                                    #<?php echo $record_id; ?>
                                </strong>

                            </td>


                            <!-- STUDENT -->

                            <td>

                                <div class="d-flex align-items-center">

                                    <div class="student-icon">

                                        <i class="bi bi-person-fill"></i>

                                    </div>


                                    <div>

                                        <div class="student-name">

                                            <?php

                                            echo htmlspecialchars(
                                                $student_name,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );

                                            ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <!-- DATE -->

                            <td>

                                <span class="date-text">

                                    <i class="bi bi-calendar3 me-1"></i>

                                    <?php

                                    echo htmlspecialchars(
                                        $attendance_date,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>


                                <?php if ($status === 'Present') { ?>


                                    <span class="badge bg-success status-badge">

                                        <i class="bi bi-check-circle me-1"></i>

                                        Present

                                    </span>


                                <?php } elseif ($status === 'Absent') { ?>


                                    <span class="badge bg-danger status-badge">

                                        <i class="bi bi-x-circle me-1"></i>

                                        Absent

                                    </span>


                                <?php } else { ?>


                                    <span class="badge bg-secondary status-badge">

                                        <?php

                                        echo htmlspecialchars(
                                            $status,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );

                                        ?>

                                    </span>


                                <?php } ?>


                            </td>


                            <!-- ACTIONS -->

                            <td class="action-buttons">


                                <?php if ($student_id > 0) { ?>

                                    <a
                                        href="attendance_report.php?student_id=<?php echo $student_id; ?>"
                                        class="btn btn-sm btn-outline-success"
                                    >
                                        <i class="bi bi-file-earmark-text"></i>
                                        Report
                                    </a>

                                <?php } else { ?>

                                    <a
                                        href="attendance_report.php"
                                        class="btn btn-sm btn-outline-success"
                                    >
                                        <i class="bi bi-file-earmark-text"></i>
                                        Report
                                    </a>

                                <?php } ?>


                                <a
                                    href="edit_attendance.php?id=<?php echo $record_id; ?>"
                                    class="btn btn-sm btn-outline-warning"
                                >
                                    <i class="bi bi-pencil-square"></i>
                                    Edit
                                </a>


                                <a
                                    href="delete_attendance.php?id=<?php echo $record_id; ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Are you sure you want to delete this attendance record?');"
                                >
                                    <i class="bi bi-trash3"></i>
                                    Delete
                                </a>


                            </td>


                        </tr>


                    <?php

                        }

                    } else {

                    ?>


                        <tr>

                            <td
                                colspan="5"
                                class="empty-state"
                            >

                                <div class="empty-icon">

                                    <i class="bi bi-calendar-x"></i>

                                </div>


                                <h5>
                                    No Attendance Records Found
                                </h5>


                                <p class="mb-3">

                                    <?php if ($search !== '') { ?>

                                        No attendance records matched your search.

                                    <?php } else { ?>

                                        No attendance records have been added yet.

                                    <?php } ?>

                                </p>


                                <?php if ($search === '') { ?>

                                    <a
                                        href="mark_attendance.php"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-plus-lg"></i>
                                        Mark Attendance
                                    </a>

                                <?php } else { ?>

                                    <a
                                        href="attendance.php"
                                        class="btn btn-outline-secondary"
                                    >
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                        Clear Search
                                    </a>

                                <?php } ?>

                            </td>

                        </tr>


                    <?php } ?>


                    </tbody>

                </table>

            </div>

        </div>


    </div>


    <!-- FOOTER -->

    <div class="footer">

        © 2026 Class Management System

        <br>

        Attendance Management Panel

    </div>


</main>


<?php

if (isset($stmt) && $stmt) {
    mysqli_stmt_close($stmt);
}

?>

</body>

</html>