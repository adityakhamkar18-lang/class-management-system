<?php

session_start();

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("config.php");

/*
|--------------------------------------------------------------------------
| Get Attendance ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/
if (!$id || $id <= 0) {
    header("Location: attendance.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch Attendance Record
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, student_id, student_name, attendance_date, status
     FROM attendance
     WHERE id = ?"
);

if (!$stmt) {
    header("Location: attendance.php");
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| Record Not Found
|--------------------------------------------------------------------------
*/
if (!$row) {
    header("Location: attendance.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/
$attendance_date = $row['attendance_date'];
$status = $row['status'];
$error = "";


/*
|--------------------------------------------------------------------------
| Update Attendance
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $attendance_date = trim($_POST['attendance_date'] ?? "");
    $status = trim($_POST['status'] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validate Date
    |--------------------------------------------------------------------------
    */
    if ($attendance_date === "") {

        $error = "Please select an attendance date.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */
    elseif (!in_array($status, ["Present", "Absent"], true)) {

        $error = "Please select a valid attendance status.";

    }


    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */
    else {

        $update_stmt = mysqli_prepare(
            $conn,
            "UPDATE attendance
             SET attendance_date = ?, status = ?
             WHERE id = ?"
        );

        if ($update_stmt) {

            mysqli_stmt_bind_param(
                $update_stmt,
                "ssi",
                $attendance_date,
                $status,
                $id
            );

            if (mysqli_stmt_execute($update_stmt)) {

                mysqli_stmt_close($update_stmt);

                header("Location: attendance.php");
                exit();

            } else {

                $error = "Unable to update attendance. Please try again.";

            }

            mysqli_stmt_close($update_stmt);

        } else {

            $error = "Unable to update attendance. Please try again.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Attendance - Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background-color: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .form-container {
            max-width: 600px;
            margin: 50px auto;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            border-radius: 12px 12px 0 0 !important;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="form-container">

        <div class="card">

            <div class="card-header bg-primary text-white">

                <h3 class="mb-0">
                    📋 Edit Attendance
                </h3>

            </div>


            <div class="card-body">

                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
                    </div>

                <?php endif; ?>


                <form method="POST">


                    <!-- Student -->

                    <div class="mb-3">

                        <label class="form-label fw-bold">
                            Student
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $row['student_name'],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            readonly
                        >

                    </div>


                    <!-- Attendance Date -->

                    <div class="mb-3">

                        <label
                            for="attendance_date"
                            class="form-label fw-bold"
                        >
                            Attendance Date
                        </label>

                        <input
                            type="date"
                            id="attendance_date"
                            name="attendance_date"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $attendance_date,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Status -->

                    <div class="mb-3">

                        <label
                            for="status"
                            class="form-label fw-bold"
                        >
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                            required
                        >

                            <option
                                value="Present"
                                <?php
                                if ($status === "Present") {
                                    echo "selected";
                                }
                                ?>
                            >
                                Present
                            </option>

                            <option
                                value="Absent"
                                <?php
                                if ($status === "Absent") {
                                    echo "selected";
                                }
                                ?>
                            >
                                Absent
                            </option>

                        </select>

                    </div>


                    <!-- Buttons -->

                    <div class="mt-4">

                        <button
                            type="submit"
                            name="update"
                            class="btn btn-primary"
                        >
                            Update Attendance
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

</body>

</html>