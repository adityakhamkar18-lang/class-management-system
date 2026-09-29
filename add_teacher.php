<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("config.php");

// ----------------------------------------------------
// Initialize variables
// ----------------------------------------------------
$error = "";

$name = "";
$email = "";
$phone = "";
$subject = "";

// ----------------------------------------------------
// Process form submission
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save"])) {

    // Get and clean form data
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $subject = trim($_POST["subject"] ?? "");

    // ------------------------------------------------
    // Validate Name
    // ------------------------------------------------
    if ($name === "") {

        $error = "Please enter the teacher's name.";

    } elseif (strlen($name) < 2) {

        $error = "Teacher name must contain at least 2 characters.";

    // ------------------------------------------------
    // Validate Email
    // ------------------------------------------------
    } elseif ($email === "") {

        $error = "Please enter the teacher's email.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    // ------------------------------------------------
    // Validate Phone
    // ------------------------------------------------
    } elseif ($phone === "") {

        $error = "Please enter the teacher's phone number.";

    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

        $error = "Phone number must contain exactly 10 digits.";

    // ------------------------------------------------
    // Validate Subject
    // ------------------------------------------------
    } elseif ($subject === "") {

        $error = "Please enter the teacher's subject.";

    }

    // ------------------------------------------------
    // Insert Teacher
    // ------------------------------------------------
    if ($error === "") {

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO teachers (name, email, phone, subject) VALUES (?, ?, ?, ?)"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $name,
                $email,
                $phone,
                $subject
            );

            if (mysqli_stmt_execute($stmt)) {

                header("Location: teachers.php");
                exit();

            } else {

                $error = "Unable to add teacher. Please try again.";
            }

            mysqli_stmt_close($stmt);

        } else {

            $error = "Database error. Please check your teachers table.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Teacher - Class Management System</title>

    <!-- Bootstrap -->
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

        /* ------------------------------------------------
           Sidebar
        ------------------------------------------------ */

        .sidebar {
            width: 240px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #212529;
            color: white;
            padding-top: 20px;
        }

        .sidebar h3 {
            text-align: center;
            margin-bottom: 25px;
            font-weight: bold;
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

        /* ------------------------------------------------
           Main Content
        ------------------------------------------------ */

        .main-content {
            margin-left: 240px;
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .page-header h2 {
            margin: 0;
            font-weight: bold;
        }

        .page-header p {
            margin: 5px 0 0;
            color: #6c757d;
        }

        /* ------------------------------------------------
           Form Card
        ------------------------------------------------ */

        .form-card {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.10);
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
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
        }

        /* ------------------------------------------------
           Mobile
        ------------------------------------------------ */

        @media (max-width: 768px) {

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
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

<!-- ====================================================
     SIDEBAR
===================================================== -->

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


<!-- ====================================================
     MAIN CONTENT
===================================================== -->

<div class="main-content">

    <!-- Page Header -->

    <div class="page-header">

        <h2>➕ Add Teacher</h2>

        <p>
            Add a new teacher to the Class Management System.
        </p>

    </div>


    <!-- Form Card -->

    <div class="form-card">

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="">


            <!-- Teacher Name -->

            <div class="mb-3">

                <label class="form-label">
                    Teacher Name
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    placeholder="Enter teacher name"
                    value="<?php echo htmlspecialchars($name); ?>"
                    required
                >

            </div>


            <!-- Email -->

            <div class="mb-3">

                <label class="form-label">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="Enter teacher email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >

            </div>


            <!-- Phone -->

            <div class="mb-3">

                <label class="form-label">
                    Phone Number
                </label>

                <input
                    type="text"
                    name="phone"
                    class="form-control"
                    placeholder="Enter 10-digit phone number"
                    value="<?php echo htmlspecialchars($phone); ?>"
                    maxlength="10"
                    inputmode="numeric"
                    required
                >

            </div>


            <!-- Subject -->

            <div class="mb-4">

                <label class="form-label">
                    Subject
                </label>

                <input
                    type="text"
                    name="subject"
                    class="form-control"
                    placeholder="Enter subject taught"
                    value="<?php echo htmlspecialchars($subject); ?>"
                    required
                >

            </div>


            <!-- Buttons -->

            <div class="d-flex gap-2">

                <button
                    type="submit"
                    name="save"
                    class="btn btn-primary"
                >
                    💾 Save Teacher
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


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>