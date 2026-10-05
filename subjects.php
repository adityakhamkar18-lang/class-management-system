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
| Admin Name
|--------------------------------------------------------------------------
*/
$admin_name = (string) ($_SESSION['admin'] ?? 'Administrator');

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/
$success = '';

if (isset($_SESSION['subject_add_success'])) {
    $success = (string) $_SESSION['subject_add_success'];
    unset($_SESSION['subject_add_success']);
}

if (isset($_SESSION['subject_update_success'])) {
    $success = (string) $_SESSION['subject_update_success'];
    unset($_SESSION['subject_update_success']);
}

if (isset($_SESSION['subject_delete_success'])) {
    $success = (string) $_SESSION['subject_delete_success'];
    unset($_SESSION['subject_delete_success']);
}

$error = '';

if (isset($_SESSION['subject_delete_error'])) {
    $error = (string) $_SESSION['subject_delete_error'];
    unset($_SESSION['subject_delete_error']);
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
$search = trim((string) ($_GET['search'] ?? ''));

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$subjects = [];
$database_error = false;

/*
|--------------------------------------------------------------------------
| Load Subjects
|--------------------------------------------------------------------------
*/
try {

    if ($search !== '') {

        $search_pattern = '%' . $search . '%';

        $stmt = $conn->prepare(
            'SELECT id, subject_name, subject_code, teacher_name
             FROM subjects
             WHERE subject_name LIKE ?
                OR subject_code LIKE ?
                OR teacher_name LIKE ?
             ORDER BY id DESC'
        );

        $stmt->bind_param(
            'sss',
            $search_pattern,
            $search_pattern,
            $search_pattern
        );

    } else {

        $stmt = $conn->prepare(
            'SELECT id, subject_name, subject_code, teacher_name
             FROM subjects
             ORDER BY id DESC'
        );
    }

    $stmt->execute();

    $stmt->bind_result(
        $id,
        $subject_name,
        $subject_code,
        $teacher_name
    );

    while ($stmt->fetch()) {

        $subjects[] = [
            'id' => (int) $id,
            'subject_name' => (string) $subject_name,
            'subject_code' => (string) $subject_code,
            'teacher_name' => (string) $teacher_name
        ];
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Subjects list error: ' .
        $e->getMessage()
    );

    $database_error = true;
}

$total_subjects = count($subjects);

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

    <title>Subjects | Class Management System</title>

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

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }

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

        .search-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
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

        .subject-stat {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(37,99,235,0.18);
        }

        .subject-stat-label {
            font-size: 13px;
            opacity: 0.85;
        }

        .subject-stat-number {
            font-size: 30px;
            font-weight: 700;
            margin-top: 3px;
        }

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
            min-width: 800px;
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

        .subject-name {
            font-weight: 700;
            color: #111827;
        }

        .subject-icon {
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

        .code-badge {
            background: #e8f0ff;
            color: #1d4ed8;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        .teacher-name {
            color: #4b5563;
        }

        .action-buttons {
            white-space: nowrap;
        }

        .action-buttons .btn {
            margin-right: 5px;
        }

        .delete-form {
            display: inline-block;
            margin: 0;
        }

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

        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
            padding: 25px 20px;
        }

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

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="sidebar-brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>

        <div>
            <h5>Class Management</h5>
            <small>Admin Panel</small>
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

    <a href="subjects.php" class="active">
        <i class="bi bi-book-fill"></i>
        Subjects
    </a>

    <a href="attendance.php">
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

<main class="main-content">

    <div class="topbar">

        <div class="topbar-title">
            Subject Management
        </div>

        <div class="admin-profile">

            <div class="admin-icon">
                <i class="bi bi-person-fill"></i>
            </div>

            <span>
                <?php
                echo htmlspecialchars(
                    $admin_name,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </span>

        </div>

    </div>

    <div class="content-area">

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    Subject Management
                </h1>

                <p class="page-subtitle">
                    Manage subjects, subject codes and assigned teachers.
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
                    href="add_subject.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-plus-lg"></i>
                    Add Subject
                </a>

            </div>

        </div>

        <?php if ($success !== ''): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-check-circle-fill me-2"></i>

                <?php
                echo htmlspecialchars(
                    $success,
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

        <?php endif; ?>

        <?php if ($error !== ''): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <?php
                echo htmlspecialchars(
                    $error,
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

        <?php endif; ?>

        <?php if ($database_error): ?>

            <div
                class="alert alert-danger"
                role="alert"
            >
                <i class="bi bi-database-x me-2"></i>
                Unable to load subjects right now. Please try again later.
            </div>

        <?php endif; ?>

        <div class="subject-stat">

            <div class="subject-stat-label">
                Total Subjects
            </div>

            <div class="subject-stat-number">
                <?php echo $total_subjects; ?>
            </div>

        </div>

        <div class="search-card">

            <form
                method="GET"
                action="subjects.php"
            >

                <div class="row g-3 align-items-end">

                    <div class="col-lg-10">

                        <label
                            for="search"
                            class="search-label"
                        >
                            Search Subjects
                        </label>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            class="form-control search-input"
                            placeholder="Search by subject name, code or teacher..."
                            value="<?php
                                echo htmlspecialchars(
                                    $search,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            maxlength="100"
                            autocomplete="off"
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

            <?php if ($search !== ''): ?>

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
                        <?php echo $total_subjects; ?>
                        found
                    </span>

                    <a
                        href="subjects.php"
                        class="btn btn-sm btn-outline-secondary ms-2"
                    >
                        <i class="bi bi-x-lg"></i>
                        Clear
                    </a>

                </div>

            <?php endif; ?>

        </div>

        <div class="table-card">

            <div class="table-header">

                <h5>
                    <i class="bi bi-book me-2"></i>
                    Subject Records
                </h5>

                <span class="badge bg-light text-dark border">

                    <?php echo $total_subjects; ?>

                    <?php echo ($total_subjects === 1)
                        ? 'Subject'
                        : 'Subjects'; ?>

                </span>

            </div>

            <div class="table-wrapper">

                <table class="table">

                    <thead>

                        <tr>

                            <th>ID</th>
                            <th>Subject</th>
                            <th>Subject Code</th>
                            <th>Teacher</th>
                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!$database_error && $total_subjects > 0): ?>

                        <?php foreach ($subjects as $row): ?>

                            <tr>

                                <td>

                                    <strong>
                                        #<?php
                                        echo (int) $row['id'];
                                        ?>
                                    </strong>

                                </td>

                                <td>

                                    <div class="d-flex align-items-center">

                                        <div class="subject-icon">

                                            <i class="bi bi-book"></i>

                                        </div>

                                        <div class="subject-name">

                                            <?php
                                            echo htmlspecialchars(
                                                $row['subject_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </div>

                                    </div>

                                </td>

                                <td>

                                    <span class="code-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $row['subject_code'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <span class="teacher-name">

                                        <i class="bi bi-person-workspace me-1"></i>

                                        <?php
                                        echo htmlspecialchars(
                                            $row['teacher_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td class="action-buttons">

                                    <a
                                        href="edit_subject.php?id=<?php echo (int) $row['id']; ?>"
                                        class="btn btn-sm btn-outline-warning"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="delete_subject.php"
                                        class="delete-form"
                                        onsubmit="return confirm('Are you sure you want to delete this subject?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php
                                                echo (int) $row['id'];
                                            ?>"
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
                                            class="btn btn-sm btn-outline-danger"
                                        >
                                            <i class="bi bi-trash3"></i>
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php elseif (!$database_error): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="empty-state"
                            >

                                <div class="empty-icon">
                                    <i class="bi bi-book"></i>
                                </div>

                                <h5>
                                    No Subjects Found
                                </h5>

                                <p class="mb-3">

                                    <?php if ($search !== ''): ?>

                                        No subjects matched your search.

                                    <?php else: ?>

                                        No subjects have been added yet.

                                    <?php endif; ?>

                                </p>

                                <?php if ($search === ''): ?>

                                    <a
                                        href="add_subject.php"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-plus-lg"></i>
                                        Add First Subject
                                    </a>

                                <?php else: ?>

                                    <a
                                        href="subjects.php"
                                        class="btn btn-outline-secondary"
                                    >
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                        Clear Search
                                    </a>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <div class="footer">

        © <?php echo date('Y'); ?> Class Management System
        <br>
        Subject Management Panel

    </div>

</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>