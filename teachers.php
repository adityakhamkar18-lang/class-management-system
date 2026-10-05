<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Class Management System - Teachers
|--------------------------------------------------------------------------
| Displays, searches and manages teacher records.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['admin']) ||
    $_SESSION['admin'] === ''
) {
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
| Admin Name
|--------------------------------------------------------------------------
*/

$admin_name = htmlspecialchars(
    (string) $_SESSION['admin'],
    ENT_QUOTES,
    'UTF-8'
);

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['search'] ?? '')
);

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$teachers = [];
$total_teachers = 0;
$database_error = false;

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$delete_success = '';

if (
    isset($_SESSION['teacher_delete_success']) &&
    is_string($_SESSION['teacher_delete_success'])
) {
    $delete_success = $_SESSION['teacher_delete_success'];
    unset($_SESSION['teacher_delete_success']);
}

$delete_error = '';

if (
    isset($_SESSION['teacher_delete_error']) &&
    is_string($_SESSION['teacher_delete_error'])
) {
    $delete_error = $_SESSION['teacher_delete_error'];
    unset($_SESSION['teacher_delete_error']);
}

/*
|--------------------------------------------------------------------------
| Fetch Teachers
|--------------------------------------------------------------------------
*/

$stmt = null;

try {

    if ($search !== '') {

        $search_pattern = '%' . $search . '%';

        $stmt = $conn->prepare(
            'SELECT
                id,
                name,
                email,
                phone,
                subject
             FROM teachers
             WHERE
                name LIKE ?
                OR email LIKE ?
                OR phone LIKE ?
                OR subject LIKE ?
             ORDER BY id DESC'
        );

        $stmt->bind_param(
            'ssss',
            $search_pattern,
            $search_pattern,
            $search_pattern,
            $search_pattern
        );

    } else {

        $stmt = $conn->prepare(
            'SELECT
                id,
                name,
                email,
                phone,
                subject
             FROM teachers
             ORDER BY id DESC'
        );
    }

    $stmt->execute();

    /*
    |--------------------------------------------------------------------------
    | bind_result()
    |--------------------------------------------------------------------------
    | Avoids requiring mysqlnd on the hosting server.
    |--------------------------------------------------------------------------
    */

    $stmt->bind_result(
        $id,
        $name,
        $email,
        $phone,
        $subject
    );

    while ($stmt->fetch()) {

        $teachers[] = [
            'id' => (int) $id,
            'name' => (string) $name,
            'email' => (string) $email,
            'phone' => (string) $phone,
            'subject' => (string) $subject
        ];
    }

    $total_teachers = count($teachers);

    $stmt->close();
    $stmt = null;

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Teachers query error: ' .
        $e->getMessage()
    );

    if ($stmt instanceof mysqli_stmt) {
        try {
            $stmt->close();
        } catch (Throwable $ignored) {
            // Ignore cleanup errors.
        }
    }

    http_response_code(500);

    $database_error = true;
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

    <meta
        name="description"
        content="Teacher management section of the Class Management System."
    >

    <title>Teachers | Class Management System</title>

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

        .add-teacher-btn {
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

        .add-teacher-btn:hover {
            background: #1d4ed8;
            color: white;
        }

        /* =========================
           SEARCH
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
           ALERTS
        ========================= */

        .alert {
            border-radius: 10px;
            margin-bottom: 22px;
        }

        /* =========================
           TABLE
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

        .teacher-count {
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

        .teacher-table {
            margin: 0;
            min-width: 900px;
        }

        .teacher-table thead th {
            background: #111827;
            color: white;
            border: none;
            padding: 14px 15px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .teacher-table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #eef0f3;
            font-size: 13px;
        }

        .teacher-table tbody tr:hover {
            background: #f8fafc;
        }

        .teacher-id {
            color: #6b7280;
            font-weight: 600;
        }

        .teacher-name {
            font-weight: 700;
            color: #111827;
        }

        .subject-badge {
            background: #eff6ff;
            color: #2563eb;
            padding: 6px 10px;
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
            cursor: pointer;
        }

        .delete-btn:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .delete-form {
            display: inline;
            margin: 0;
            padding: 0;
        }

        /* =========================
           ERROR STATE
        ========================= */

        .error-state {
            text-align: center;
            padding: 60px 20px;
        }

        .error-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #fef2f2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .error-state h5 {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .error-state p {
            color: #6b7280;
            margin: 0;
            font-size: 13px;
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

            .add-teacher-btn {
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

    <a href="students.php">
        <i class="bi bi-people"></i>
        Students
    </a>

    <a href="teachers.php" class="active">
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
                Teachers
            </h2>

            <p>
                Manage teacher records
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
                        Teacher Management
                    </h1>

                    <p>
                        Add, view, edit and manage teacher information.
                    </p>

                </div>

                <div class="col-md-4 text-md-end">

                    <a
                        href="add_teacher.php"
                        class="add-teacher-btn"
                    >

                        <i class="bi bi-person-plus-fill"></i>

                        Add Teacher

                    </a>

                </div>

            </div>

        </div>

        <!-- FLASH SUCCESS -->

        <?php if ($delete_success !== '') { ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-check-circle-fill me-2"></i>

                <?php
                echo htmlspecialchars(
                    $delete_success,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php } ?>

        <!-- FLASH ERROR -->

        <?php if ($delete_error !== '') { ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <?php
                echo htmlspecialchars(
                    $delete_error,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php } ?>

        <!-- SEARCH -->

        <div class="search-card">

            <form
                method="GET"
                action="teachers.php"
                autocomplete="off"
            >

                <label
                    for="teacherSearch"
                    class="search-label"
                >
                    Search Teachers
                </label>

                <div class="input-group">

                    <input
                        type="search"
                        id="teacherSearch"
                        name="search"
                        class="form-control search-input"
                        placeholder="Search by name, email, phone or subject..."
                        value="<?php
                            echo htmlspecialchars(
                                $search,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        maxlength="100"
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
                            href="teachers.php"
                            class="clear-btn"
                        >

                            <i class="bi bi-x-lg me-1"></i>

                            Clear

                        </a>

                    <?php } ?>

                </div>

            </form>

        </div>

        <!-- TABLE -->

        <div class="table-card">

            <div class="table-header">

                <h5>
                    Teacher Records
                </h5>

                <?php if (!$database_error) { ?>

                    <div class="teacher-count">

                        <i class="bi bi-person-workspace"></i>

                        <?php

                        if ($search !== '') {

                            echo $total_teachers . ' result';

                            if ($total_teachers !== 1) {
                                echo 's';
                            }

                        } else {

                            echo $total_teachers . ' teacher';

                            if ($total_teachers !== 1) {
                                echo 's';
                            }

                        }

                        ?>

                    </div>

                <?php } ?>

            </div>

            <div class="table-wrapper">

                <?php if ($database_error) { ?>

                    <!-- DATABASE ERROR -->

                    <div class="error-state">

                        <div class="error-icon">

                            <i class="bi bi-exclamation-triangle"></i>

                        </div>

                        <h5>
                            Unable to load teachers
                        </h5>

                        <p>
                            Teacher records could not be loaded.
                            Please try again later.
                        </p>

                    </div>

                <?php } elseif ($total_teachers > 0) { ?>

                    <!-- TEACHER TABLE -->

                    <table class="table teacher-table">

                        <thead>

                            <tr>

                                <th>ID</th>
                                <th>Teacher</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Subject</th>
                                <th>Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($teachers as $row) { ?>

                            <tr>

                                <td>

                                    <span class="teacher-id">

                                        #<?php
                                        echo (int) $row['id'];
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <div class="teacher-name">

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

                                    <span class="subject-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $row['subject'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td class="action-buttons">

                                    <!-- EDIT -->

                                    <a
                                        href="edit_teacher.php?id=<?php echo (int) $row['id']; ?>"
                                        class="action-btn edit-btn"
                                    >

                                        <i class="bi bi-pencil"></i>

                                        Edit

                                    </a>

                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        action="delete_teacher.php"
                                        class="delete-form"
                                        onsubmit="return confirm('Are you sure you want to delete this teacher?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo (int) $row['id']; ?>"
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

                                        <button
                                            type="submit"
                                            class="action-btn delete-btn"
                                        >

                                            <i class="bi bi-trash"></i>

                                            Delete

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                <?php } else { ?>

                    <!-- EMPTY STATE -->

                    <div class="empty-state">

                        <div class="empty-icon">

                            <i class="bi bi-person-workspace"></i>

                        </div>

                        <?php if ($search !== '') { ?>

                            <h5>
                                No teachers found
                            </h5>

                            <p>

                                No teacher records match
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
                                No teachers yet
                            </h5>

                            <p>
                                Start by adding your first teacher.
                            </p>

                        <?php } ?>

                    </div>

                <?php } ?>

            </div>

        </div>

        <!-- FOOTER -->

        <footer>

            © <?php echo date('Y'); ?>
            Class Management System.
            All Rights Reserved.

        </footer>

    </div>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>