<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$search = trim((string) ($_GET['search'] ?? ''));

$sql = "
    SELECT
        a.id,
        a.student_id,
        a.student_name,
        a.attendance_date,
        a.status
    FROM attendance a
";

if ($search !== '') {
    $sql .= "
        WHERE
            a.student_name LIKE ?
            OR a.status LIKE ?
            OR DATE_FORMAT(a.attendance_date, '%d-%m-%Y') LIKE ?
            OR CAST(a.student_id AS CHAR) LIKE ?
    ";
}

$sql .= " ORDER BY a.attendance_date DESC, a.student_name ASC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $search_like = '%' . $search . '%';

    $stmt->bind_param(
        'ssss',
        $search_like,
        $search_like,
        $search_like,
        $search_like
    );
}

$stmt->execute();

$result = $stmt->get_result();

$total_records = 0;
$present_count = 0;
$absent_count = 0;

$rows = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;

    $total_records++;

    if ($row['status'] === 'Present') {
        $present_count++;
    } elseif ($row['status'] === 'Absent') {
        $absent_count++;
    }
}

$stmt->close();

$attendance_percentage = $total_records > 0
    ? round(($present_count / $total_records) * 100, 2)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance Report - Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: #212529;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px 15px;
        }

        .sidebar h4 {
            color: #fff;
            text-align: center;
            margin-bottom: 25px;
        }

        .sidebar a {
            display: block;
            color: #dee2e6;
            text-decoration: none;
            padding: 11px 15px;
            border-radius: 6px;
            margin-bottom: 5px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #343a40;
            color: #fff;
        }

        .main-content {
            margin-left: 240px;
            padding: 30px;
        }

        .report-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .stat-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.07);
        }

        .table th {
            white-space: nowrap;
        }

        @media print {
            .sidebar,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
            }

            body {
                background: #fff;
            }

            .report-card,
            .stat-card {
                box-shadow: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                min-height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="sidebar">
    <h4>Class Management</h4>

    <a href="dashboard.php">Dashboard</a>
    <a href="students.php">Students</a>
    <a href="teachers.php">Teachers</a>
    <a href="subjects.php">Subjects</a>
    <a href="attendance.php">Attendance</a>
    <a href="marks.php">Marks</a>
    <a href="reports.php" class="active">Reports</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Attendance Report</h2>
            <p class="text-muted mb-0">
                Attendance records and summary
            </p>
        </div>

        <button
            type="button"
            class="btn btn-dark no-print"
            onclick="window.print()"
        >
            Print Report
        </button>
    </div>

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="text-muted">Total Records</div>
                <h3 class="mb-0"><?= $total_records ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="text-muted">Present</div>
                <h3 class="mb-0"><?= $present_count ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="text-muted">Attendance Percentage</div>
                <h3 class="mb-0"><?= e((string) $attendance_percentage) ?>%</h3>
            </div>
        </div>

    </div>

    <div class="card report-card">

        <div class="card-body">

            <form method="GET" class="row g-2 mb-4 no-print">

                <div class="col-md-10">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search student, status, date or student ID..."
                        value="<?= e($search) ?>"
                    >
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">
                        Search
                    </button>
                </div>

            </form>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($rows)): ?>

                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No attendance records found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $index => $row): ?>

                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <?= e((string) $row['student_id']) ?>
                                </td>

                                <td>
                                    <?= e((string) $row['student_name']) ?>
                                </td>

                                <td>
                                    <?= e((string) $row['attendance_date']) ?>
                                </td>

                                <td>
                                    <?php if ($row['status'] === 'Present'): ?>

                                        <span class="badge bg-success">
                                            Present
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            Absent
                                        </span>

                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>