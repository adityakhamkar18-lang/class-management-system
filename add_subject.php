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

$subject_name = "";
$subject_code = "";
$teacher_name = "";

// ----------------------------------------------------
// Process form submission
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save"])) {

    // Get and clean form data
    $subject_name = trim($_POST["subject_name"] ?? "");
    $subject_code = trim($_POST["subject_code"] ?? "");
    $teacher_name = trim($_POST["teacher_name"] ?? "");

    // ------------------------------------------------
    // Validate Subject Name
    // ------------------------------------------------
    if ($subject_name === "") {

        $error = "Please enter the subject name.";

    }

    elseif (strlen($subject_name) < 2) {

        $error = "Subject name must contain at least 2 characters.";

    }

    // ------------------------------------------------
    // Validate Subject Code
    // ------------------------------------------------
    elseif ($subject_code === "") {

        $error = "Please enter the subject code.";

    }

    // ------------------------------------------------
    // Validate Teacher Name
    // ------------------------------------------------
    elseif ($teacher_name === "") {

        $error = "Please enter the teacher name.";

    }

    elseif (strlen($teacher_name) < 2) {

        $error = "Teacher name must contain at least 2 characters.";

    }

    else {

        // ------------------------------------------------
        // Check duplicate subject code
        // ------------------------------------------------
        $check_code_stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM subjects
             WHERE subject_code = ?
             LIMIT 1"
        );

        if (!$check_code_stmt) {

            $error = "Database error. Please try again.";

        } else {

            mysqli_stmt_bind_param(
                $check_code_stmt,
                "s",
                $subject_code
            );

            mysqli_stmt_execute($check_code_stmt);

            $code_result = mysqli_stmt_get_result($check_code_stmt);

            if (
                $code_result &&
                mysqli_num_rows($code_result) > 0
            ) {

                $error = "This subject code already exists.";

            }

            mysqli_stmt_close($check_code_stmt);
        }

        // ------------------------------------------------
        // Check duplicate subject name
        // ------------------------------------------------
        if ($error === "") {

            $check_name_stmt = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM subjects
                 WHERE subject_name = ?
                 LIMIT 1"
            );

            if (!$check_name_stmt) {

                $error = "Database error. Please try again.";

            } else {

                mysqli_stmt_bind_param(
                    $check_name_stmt,
                    "s",
                    $subject_name
                );

                mysqli_stmt_execute($check_name_stmt);

                $name_result =
                    mysqli_stmt_get_result($check_name_stmt);

                if (
                    $name_result &&
                    mysqli_num_rows($name_result) > 0
                ) {

                    $error = "This subject already exists.";

                }

                mysqli_stmt_close($check_name_stmt);
            }
        }

        // ------------------------------------------------
        // Insert Subject
        // ------------------------------------------------
        if ($error === "") {

            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO subjects
                (subject_name, subject_code, teacher_name)
                VALUES (?, ?, ?)"
            );

            if (!$insert_stmt) {

                $error = "Unable to prepare database request.";

            } else {

                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "sss",
                    $subject_name,
                    $subject_code,
                    $teacher_name
                );

                if (mysqli_stmt_execute($insert_stmt)) {

                    mysqli_stmt_close($insert_stmt);

                    header("Location: subjects.php");
                    exit();

                } else {

                    $error =
                        "Unable to save subject. Please try again.";

                    mysqli_stmt_close($insert_stmt);
                }
            }
        }
    }
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

    <title>Add Subject - Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background-color: #f5f7fb;
            min-height: 100vh;
        }

        .form-card {
            max-width: 600px;
            margin: 50px auto;
            background: #ffffff;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-weight: 600;
            color: #212529;
        }

        .form-label {
            font-weight: 500;
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="form-card">

        <h2 class="page-title mb-4">
            Add Subject
        </h2>

        <?php if ($error !== "") { ?>

            <div
                class="alert alert-danger"
                role="alert"
            >
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>

        <?php } ?>

        <form method="POST" action="">

            <!-- Subject Name -->
            <div class="mb-3">

                <label
                    for="subject_name"
                    class="form-label"
                >
                    Subject Name
                </label>

                <input
                    type="text"
                    id="subject_name"
                    name="subject_name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $subject_name,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter subject name"
                    maxlength="100"
                    required
                >

            </div>


            <!-- Subject Code -->
            <div class="mb-3">

                <label
                    for="subject_code"
                    class="form-label"
                >
                    Subject Code
                </label>

                <input
                    type="text"
                    id="subject_code"
                    name="subject_code"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $subject_code,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter subject code"
                    maxlength="50"
                    required
                >

            </div>


            <!-- Teacher Name -->
            <div class="mb-4">

                <label
                    for="teacher_name"
                    class="form-label"
                >
                    Teacher Name
                </label>

                <input
                    type="text"
                    id="teacher_name"
                    name="teacher_name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $teacher_name,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter teacher name"
                    maxlength="100"
                    required
                >

            </div>


            <!-- Buttons -->
            <div class="button-group">

                <button
                    type="submit"
                    name="save"
                    class="btn btn-success px-4"
                >
                    Save Subject
                </button>

                <a
                    href="subjects.php"
                    class="btn btn-secondary px-4"
                >
                    Back
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>
