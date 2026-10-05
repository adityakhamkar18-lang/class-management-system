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
| Admin Name
|--------------------------------------------------------------------------
*/

$admin_name = (string) ($_SESSION['admin'] ?? 'Administrator');

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$success_message = '';

if (isset($_SESSION['mark_add_success'])) {
    $success_message = (string) $_SESSION['mark_add_success'];
    unset($_SESSION['mark_add_success']);
}

if (isset($_SESSION['mark_update_success'])) {
    $success_message = (string) $_SESSION['mark_update_success'];
    unset($_SESSION['mark_update_success']);
}

if (isset($_SESSION['mark_delete_success'])) {
    $success_message = (string) $_SESSION['mark_delete_success'];
    unset($_SESSION['mark_delete_success']);
}

$error_message = '';

if (isset($_SESSION['mark_delete_error'])) {
    $error_message = (string) $_SESSION['mark_delete_error'];
    unset($_SESSION['mark_delete_error']);
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

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$marks_records = [];
$total_records = 0;

$total_marks = 0.0;
$highest_marks = 0.0;
$passed_count = 0;
$failed_count = 0;
$average_marks = 0.0;

$database_error = '';

/*
|--------------------------------------------------------------------------
| Fetch Marks
|--------------------------------------------------------------------------
*/

try {

    if ($search !== '') {

        $search_pattern = '%' . $search . '%';

        $stmt = $conn->prepare(
            "SELECT id, student_id, student_name, subject_name, marks
             FROM marks
             WHERE student_name LIKE ?
                OR subject_name LIKE ?
                OR CAST(marks AS CHAR) LIKE ?
             ORDER BY id DESC"
        );

        $stmt->bind_param(
            'sss',
            $search_pattern,
            $search_pattern,
            $search_pattern
        );

    } else {

        $stmt = $conn->prepare(
            "SELECT id, student_id, student_name, subject_name, marks
             FROM marks
             ORDER BY id DESC"
        );
    }

    $stmt->execute();

    $stmt->store_result();

    $stmt->bind_result(
        $id,
        $student_id,
        $student_name,
        $subject_name,
        $marks
    );

    while ($stmt->fetch()) {

        $mark_value = (float) $marks;

        $marks_records[] = [
            'id' => (int) $id,
            'student_id' => (int) $student_id,
            'student_name' => (string) $student_name,
            'subject_name' => (string) $subject_name,
            'marks' => $marks
        ];

        $total_marks += $mark_value;

        if ($total_records === 0 || $mark_value > $highest_marks) {
            $highest_marks = $mark_value;
        }

        if ($mark_value >= 40) {
            $passed_count++;
        } else {
            $failed_count++;
        }

        $total_records++;
    }

    $stmt->close();

    if ($total_records > 0) {
        $average_marks = round($total_marks / $total_records, 2);
    }

} catch (mysqli_sql_exception $e) {

    error_log(
        'Marks page database error: ' . $e->getMessage()
    );

    $database_error = 'Unable to load marks records right now. Please try again later.';
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

    <title>Marks Management | Class Management System</title>

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
        }

        /* ==============================
           SIDEBAR
        ============================== */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #111827;
            padding: 22px 15px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-brand {
            color: #ffffff;
            font-size: 21px;
            font-weight: 700;
            text-align: center;
            padding: 12px 5px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            margin-bottom: 20px;
        }

        .sidebar-brand i {
            margin-right: 8px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 7px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 15px;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: #1f2937;
            color: #ffffff;
        }

        .sidebar-menu a.active {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }

        .sidebar-menu i {
            width: 20px;
            font-size: 17px;
        }

        /* ==============================
           MAIN CONTENT
        ============================== */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* ==============================
           TOPBAR
        ============================== */

        .topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }

        .topbar-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #374151;
            font-weight: 600;
        }

        .admin-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* ==============================
           PAGE CONTENT
        ============================== */

        .content-area {
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        .page-subtitle {
            margin-top: 6px;
            margin-bottom: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ==============================
           ALERTS
        ============================== */

        .alert {
            border-radius: 10px;
        }

        /* ==============================
           STAT CARDS
        ============================== */

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #111827;
            font-size: 25px;
            font-weight: 700;
        }

        /* ==============================
           SEARCH CARD
        ============================== */

        .search-card {
            background: #ffffff;
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
            color: #374151;
            margin-bottom: 8px;
        }

        .search-input {
            height: 45px;
            border-radius: 8px;
        }

        /* ==============================
           TABLE CARD
        ============================== */

        .table-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .table-card-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-card-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #111827;
        }

        .table-card-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #111827;
            color: #ffffff;
            border: none;
            padding: 14px 16px;
            font-size: 13px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: 14px;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .student-name {
            font-weight: 600;
            color: #111827;
        }

        .subject-name {
            color: #4b5563;
        }

        .marks-badge {
            display: inline-block;
            min-width: 65px;
            padding: 7px 12px;
            border-radius: 20px;
            background: #eff6ff;
            color: #2563eb;
            font-weight: 700;
            text-align: center;
        }

        .pass-badge {
            background: #dcfce7;
            color: #166534;
        }

        .fail-badge {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            white-space: nowrap;
        }

        /* ==============================
           EMPTY STATE
        ============================== */

        .empty-state {
            text-align: center;
            padding: 55px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 45px;
            margin-bottom: 15px;
            color: #9ca3af;
        }

        .empty-state h5 {
            color: #374151;
            font-weight: 600;
        }

        /* ==============================
           FOOTER
        ============================== */

        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            padding: 25px 10px;
        }

        /* ==============================
           MOBILE
        ============================== */

        @media (max-width: 992px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 5px;
            }

            .sidebar-menu li {
                margin-bottom: 0;
            }

            .topbar {
                padding: 0 20px;
            }

            .content-area {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {

            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .topbar {
                height: auto;
                padding: 15px;
                gap: 10px;
                flex-direction: column;
                align-items: flex-start;
            }

            .content-area {
                padding: 15px;
            }

            .page-title {
                font-size: 23px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            .admin-profile {
                font-size: 14px;
            }
        }

    </style>

</head>

<body>

<aside class="sidebar">

    <div class="sidebar-brand">
        <i class="bi bi-mortarboard-fill"></i>
        Class Management
    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </li>

        <li>
            <a href="students.php">
                <i class="bi bi-people-fill"></i>
                Students
            </a>
        </li>

        <li>
            <a href="teachers.php">
                <i class="bi bi-person-workspace"></i>
                Teachers
            </a>
        </li>

        <li>
            <a href="subjects.php">
                <i class="bi bi-book-fill"></i>
                Subjects
            </a>
        </li>

        <li>
            <a href="attendance.php">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
        </li>

        <li>
            <a href="marks.php" class="active" aria-current="page">
                <i class="bi bi-bar-chart-fill"></i>
                Marks
            </a>
        </li>

        <li>
            <a href="reports.php">
                <i class="bi bi-file-earmark-text-fill"></i>
                Reports
            </a>
        </li>

        <li>
            <a href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </li>

    </ul>

</aside>

<div class="main-content">

    <div class="topbar">

        <div class="topbar-title">
            Marks Management
        </div>

        <div class="admin-profile">

            <div class="admin-icon">
                <i class="bi bi-person-fill"></i>
            </div>

            <span>
                <?php echo e($admin_name); ?>
            </span>

        </div>

    </div>

    <main class="content-area">

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    Marks Management
                </h1>

                <p class="page-subtitle">
                    Manage and monitor student academic marks.
                </p>

            </div>

            <div class="header-actions">

                <a
                    href="dashboard.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>

                <a
                    href="add_marks.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-plus-lg"></i>
                    Add Marks
                </a>

            </div>

        </div>

        <?php if ($success_message !== ''): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo e($success_message); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>

        <?php if ($error_message !== ''): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo e($error_message); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>

        <?php if ($database_error !== ''): ?>

            <div
                class="alert alert-danger"
                role="alert"
            >
                <i class="bi bi-database-x me-2"></i>
                <?php echo e($database_error); ?>
            </div>

        <?php endif; ?>

        <div class="row g-3">

            <div class="col-lg-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-bar-chart-fill"></i>
                    </div>

                    <div class="stat-label">
                        Total Records
                    </div>

                    <div class="stat-value">
                        <?php echo $total_records; ?>
                    </div>

                </div>

            </div>

            <div class="col-lg-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>

                    <div class="stat-label">
                        Average Marks
                    </div>

                    <div class="stat-value">
                        <?php echo e((string) $average_marks); ?>
                    </div>

                </div>

            </div>

            <div class="col-lg-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-trophy-fill"></i>
                    </div>

                    <div class="stat-label">
                        Highest Marks
                    </div>

                    <div class="stat-value">
                        <?php echo e((string) $highest_marks); ?>
                    </div>

                </div>

            </div>

            <div class="col-lg-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>

                    <div class="stat-label">
                        Passed Records
                    </div>

                    <div class="stat-value">
                        <?php echo $passed_count; ?>
                    </div>

                </div>

            </div>

        </div>

        <div class="search-card">

            <form
                method="GET"
                action="marks.php"
            >

                <div class="search-label">
                    Search Marks
                </div>

                <div class="row g-2">

                    <div class="col-md-10">

                        <input
                            type="text"
                            name="search"
                            class="form-control search-input"
                            placeholder="Search by student name, subject or marks..."
                            value="<?php echo e($search); ?>"
                            maxlength="100"
                            autocomplete="off"
                        >

                    </div>

                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                            style="height:45px;"
                        >
                            <i class="bi bi-search"></i>
                            Search
                        </button>

                    </div>

                </div>

                <?php if ($search !== ''): ?>

                    <div class="mt-3">

                        <span class="text-muted">
                            Search results for
                        </span>

                        <strong>
                            "<?php echo e($search); ?>"
                        </strong>

                        <span class="badge bg-primary ms-2">
                            <?php echo $total_records; ?> found
                        </span>

                        <a
                            href="marks.php"
                            class="btn btn-sm btn-outline-secondary ms-2"
                        >
                            <i class="bi bi-x-lg"></i>
                            Clear
                        </a>

                    </div>

                <?php endif; ?>

            </form>

        </div>

        <div class="table-card">

            <div class="table-card-header">

                <div>

                    <h2 class="table-card-title">
                        Marks Records
                    </h2>

                    <div class="table-card-subtitle">
                        <?php echo $total_records; ?> record(s) available
                    </div>

                </div>

                <a
                    href="add_marks.php"
                    class="btn btn-sm btn-primary"
                >
                    <i class="bi bi-plus-lg"></i>
                    Add New Record
                </a>

            </div>

            <?php if ($total_records > 0): ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th>ID</th>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Marks</th>
                                <th>Performance</th>
                                <th>Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($marks_records as $row): ?>

                            <?php

                            $mark_value = (float) $row['marks'];

                            if ($mark_value >= 40) {
                                $performance_class = 'pass-badge';
                                $performance_text = 'Pass';
                            } else {
                                $performance_class = 'fail-badge';
                                $performance_text = 'Needs Improvement';
                            }

                            ?>

                            <tr>

                                <td>
                                    <span class="text-muted">
                                        #<?php echo (int) $row['id']; ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="student-name">

                                        <i class="bi bi-person-fill text-primary"></i>

                                        <?php echo e((string) $row['student_name']); ?>

                                    </div>

                                </td>

                                <td>

                                    <div class="subject-name">

                                        <i class="bi bi-book text-secondary"></i>

                                        <?php echo e((string) $row['subject_name']); ?>

                                    </div>

                                </td>

                                <td>

                                    <span class="marks-badge">

                                        <?php echo e((string) $row['marks']); ?>

                                        / 100

                                    </span>

                                </td>

                                <td>

                                    <span class="marks-badge <?php echo $performance_class; ?>">

                                        <?php echo e($performance_text); ?>

                                    </span>

                                </td>

                                <td class="action-buttons">

                                    <a
                                        href="edit_marks.php?id=<?php echo (int) $row['id']; ?>"
                                        class="btn btn-sm btn-outline-warning"
                                        title="Edit Marks"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="delete_marks.php"
                                        class="d-inline delete-form"
                                        onsubmit="return confirm('Are you sure you want to delete this marks record?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo (int) $row['id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo e($csrf_token); ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete Marks"
                                        >
                                            <i class="bi bi-trash3"></i>
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="bi bi-bar-chart"></i>

                    <?php if ($search !== ''): ?>

                        <h5>
                            No Marks Records Found
                        </h5>

                        <p>
                            No records matched your search.
                        </p>

                        <a
                            href="marks.php"
                            class="btn btn-outline-primary"
                        >
                            <i class="bi bi-arrow-left"></i>
                            Show All Records
                        </a>

                    <?php elseif ($database_error === ''): ?>

                        <h5>
                            No Marks Records Yet
                        </h5>

                        <p>
                            Start by adding the first student's marks.
                        </p>

                        <a
                            href="add_marks.php"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-plus-lg"></i>
                            Add Marks
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

    <footer class="footer">

        © <?php echo date('Y'); ?> Class Management System.
        All rights reserved.

    </footer>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
