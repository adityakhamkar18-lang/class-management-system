<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once "config.php";

$admin_name = htmlspecialchars($_SESSION['admin'], ENT_QUOTES, 'UTF-8');

$search = trim($_GET['search'] ?? '');

$students = [];

if ($search !== '') {

    $search_pattern = "%" . $search . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            roll_no,
            name,
            email,
            phone,
            gender,
            class_name
         FROM students
         WHERE
            name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
            OR roll_no LIKE ?
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Unable to load student records.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $search_pattern,
        $search_pattern,
        $search_pattern,
        $search_pattern
    );

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            roll_no,
            name,
            email,
            phone,
            gender,
            class_name
         FROM students
         ORDER BY id DESC"
    );

    if (!$stmt) {
        die("Unable to load student records.");
    }
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$total_students = mysqli_num_rows($result);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Students | Class Management System</title>

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
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #111827;
            color: white;
            padding: 20px 15px;
            overflow-y: auto;
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 10px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .brand img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 10px;
            background: white;
        }

        .brand h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .brand small {
            color: #9ca3af;
            font-size: 11px;
        }

        .nav-title {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 10px 8px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 13px;
            margin-bottom: 5px;
            border-radius: 9px;
            color: #d1d5db;
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a i {
            font-size: 17px;
            width: 22px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .logout-link {
            margin-top: 25px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px !important;
        }

        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* =========================
           TOPBAR
        ========================= */

        .topbar {
            min-height: 72px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 30px;
        }

        .page-title h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .page-title p {
            margin: 3px 0 0;
            font-size: 13px;
            color: #6b7280;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .admin-info strong {
            display: block;
            font-size: 14px;
        }

        .admin-info small {
            color: #6b7280;
            font-size: 11px;
        }

        /* =========================
           CONTENT
        ========================= */

        .content {
            padding: 30px;
        }

        /* =========================
           PAGE HEADER
        ========================= */

        .page-header {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
        }

        .page-header p {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .add-student-btn {
            background: #2563eb;
            border: none;
            border-radius: 9px;
            padding: 11px 18px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .add-student-btn:hover {
            background: #1d4ed8;
            color: white;
        }

        /* =========================
           SEARCH CARD
        ========================= */

        .search-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 22px;
        }

        .search-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .search-input {
            height: 46px;
            border: 1px solid #d1d5db;
            border-radius: 8px 0 0 8px;
        }

        .search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .search-btn {
            background: #2563eb;
            border: none;
            color: white;
            padding: 0 20px;
            font-weight: 600;
        }

        .search-btn:hover {
            background: #1d4ed8;
            color: white;
        }

        .clear-btn {
            display: flex;
            align-items: center;
            background: #6b7280;
            color: white;
            text-decoration: none;
            padding: 0 16px;
        }

        .clear-btn:hover {
            background: #4b5563;
            color: white;
        }

        /* =========================
           TABLE CARD
        ========================= */

        .table-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            overflow: hidden;
        }

        .table-header {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h5 {
            margin: 0;
            font-weight: 700;
        }

        .student-count {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 20px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .student-table {
            margin: 0;
            min-width: 1000px;
        }

        .student-table thead th {
            background: #111827;
            color: white;
            border: none;
            padding: 14px 15px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .student-table tbody td {
            padding: 14px 15px;
            vertical-align: middle;
            border-color: #eef0f3;
            font-size: 13px;
        }

        .student-table tbody tr:hover {
            background: #f8fafc;
        }

        .student-id {
            color: #6b7280;
            font-weight: 600;
        }

        .student-name {
            font-weight: 700;
            color: #111827;
        }

        .roll-badge {
            background: #f3f4f6;
            color: #374151;
            padding: 5px 9px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .gender-badge {
            background: #f3f4f6;
            color: #374151;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 11px;
        }

        .class-badge {
            background: #eff6ff;
            color: #2563eb;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .action-buttons {
            white-space: nowrap;
        }

        .action-btn {
            border: none;
            border-radius: 7px;
            padding: 7px 10px;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-right: 4px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .report-btn {
            background: #ecfdf5;
            color: #059669;
        }

        .report-btn:hover {
            background: #d1fae5;
            color: #047857;
        }

        .edit-btn {
            background: #fff7ed;
            color: #ea580c;
        }

        .edit-btn:hover {
            background: #ffedd5;
            color: #c2410c;
        }

        .delete-btn {
            background: #fef2f2;
            color: #dc2626;
        }

        .delete-btn:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* =========================
           EMPTY STATE
        ========================= */

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #f3f4f6;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .empty-state h5 {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: #6b7280;
            margin: 0;
            font-size: 13px;
        }

        /* =========================
           FOOTER
        ========================= */

        footer {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 12px;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 768px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                padding: 15px 20px;
            }

            .admin-info {
                display: none;
            }

            .content {
                padding: 15px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header .row {
                gap: 15px;
            }

            .add-student-btn {
                width: 100%;
                justify-content: center;
            }

            .search-input {
                border-radius: 8px;
            }

            .search-btn,
            .clear-btn {
                margin-top: 8px;
                min-height: 42px;
            }

            .table-header {
                align-items: flex-start;
                gap: 10px;
                flex-direction: column;
            }

        }

    </style>

</head>

<body>


<!-- =========================
     SIDEBAR
========================= -->

<div class="sidebar">

    <div class="brand">

        <img
            src="images/logo.png"
            alt="Class Management System Logo"
            onerror="this.style.display='none';"
        >

        <div>

            <h4>Class Manager</h4>

            <small>
                Management System
            </small>

        </div>

    </div>


    <div class="nav-title">
        Main Menu
    </div>


    <a href="dashboard.php">

        <i class="bi bi-speedometer2"></i>

        Dashboard

    </a>


    <a href="students.php" class="active">

        <i class="bi bi-people"></i>

        Students

    </a>


    <a href="teachers.php">

        <i class="bi bi-person-workspace"></i>

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


    <a href="reports.php">

        <i class="bi bi-file-earmark-text"></i>

        Reports

    </a>


    <a href="logout.php" class="logout-link">

        <i class="bi bi-box-arrow-right"></i>

        Logout

    </a>

</div>


<!-- =========================
     MAIN
========================= -->

<div class="main">


    <!-- TOPBAR -->

    <div class="topbar">

        <div class="page-title">

            <h2>
                Students
            </h2>

            <p>
                Manage student records
            </p>

        </div>


        <div class="admin-profile">

            <div class="admin-icon">

                <i class="bi bi-person"></i>

            </div>


            <div class="admin-info">

                <strong>
                    <?php echo $admin_name; ?>
                </strong>

                <small>
                    Administrator
                </small>

            </div>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <h1>
                        Student Management
                    </h1>

                    <p>
                        Add, view, edit and manage student information.
                    </p>

                </div>


                <div class="col-md-4 text-md-end">

                    <a
                        href="add_student.php"
                        class="add-student-btn"
                    >

                        <i class="bi bi-person-plus-fill"></i>

                        Add Student

                    </a>

                </div>

            </div>

        </div>


        <!-- SEARCH -->

        <div class="search-card">

            <form
                method="GET"
                action="students.php"
            >

                <label class="search-label">

                    Search Students

                </label>


                <div class="input-group">

                    <input
                        type="text"
                        name="search"
                        class="form-control search-input"
                        placeholder="Search by name, email, phone or roll number..."
                        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    >


                    <button
                        type="submit"
                        class="btn search-btn"
                    >

                        <i class="bi bi-search"></i>

                        Search

                    </button>


                    <?php if ($search !== '') { ?>

                        <a
                            href="students.php"
                            class="clear-btn"
                        >

                            <i class="bi bi-x-lg me-1"></i>

                            Clear

                        </a>

                    <?php } ?>

                </div>

            </form>

        </div>


        <!-- STUDENT TABLE -->

        <div class="table-card">


            <div class="table-header">

                <div>

                    <h5>
                        Student Records
                    </h5>

                </div>


                <div class="student-count">

                    <i class="bi bi-people-fill"></i>

                    <?php

                    if ($search !== '') {

                        echo $total_students . " result";

                        if ($total_students != 1) {
                            echo "s";
                        }

                    } else {

                        echo $total_students . " student";

                        if ($total_students != 1) {
                            echo "s";
                        }

                    }

                    ?>

                </div>

            </div>


            <div class="table-wrapper">

                <?php if ($total_students > 0) { ?>

                    <table class="table student-table">

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Roll No
                                </th>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Gender
                                </th>

                                <th>
                                    Class
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                            <tr>


                                <td>

                                    <span class="student-id">

                                        #<?php echo (int)$row['id']; ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="roll-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $row['roll_no'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <div class="student-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $row['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $row['phone'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span class="gender-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $row['gender'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="class-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $row['class_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td class="action-buttons">


                                    <a
                                        href="student_report.php?student_id=<?php echo (int)$row['id']; ?>"
                                        class="action-btn report-btn"
                                    >

                                        <i class="bi bi-bar-chart-line"></i>

                                        Report

                                    </a>


                                    <a
                                        href="edit_student.php?id=<?php echo (int)$row['id']; ?>"
                                        class="action-btn edit-btn"
                                    >

                                        <i class="bi bi-pencil"></i>

                                        Edit

                                    </a>


                                    <a
                                        href="delete_student.php?id=<?php echo (int)$row['id']; ?>"
                                        class="action-btn delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this student?');"
                                    >

                                        <i class="bi bi-trash"></i>

                                        Delete

                                    </a>


                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>


                <?php } else { ?>


                    <div class="empty-state">

                        <div class="empty-icon">

                            <i class="bi bi-people"></i>

                        </div>


                        <?php if ($search !== '') { ?>

                            <h5>
                                No students found
                            </h5>

                            <p>

                                No student records match
                                "<?php
                                echo htmlspecialchars(
                                    $search,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>".

                            </p>


                        <?php } else { ?>

                            <h5>
                                No students yet
                            </h5>

                            <p>
                                Start by adding your first student.
                            </p>

                        <?php } ?>

                    </div>


                <?php } ?>

            </div>

        </div>


        <!-- FOOTER -->

        <footer>

            © 2026 Class Management System.
            All Rights Reserved.

        </footer>


    </div>

</div>


<?php

if (isset($stmt) && $stmt) {
    mysqli_stmt_close($stmt);
}

?>

</body>

</html>