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

$selected_student_id = "";
$selected_subject = "";
$entered_marks = "";

// ----------------------------------------------------
// Process form submission
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save"])) {

    // Get and clean form data
    $selected_student_id = isset($_POST["student_id"])
        ? intval($_POST["student_id"])
        : 0;

    $selected_subject = isset($_POST["subject_name"])
        ? trim($_POST["subject_name"])
        : "";

    $entered_marks = isset($_POST["marks"])
        ? trim($_POST["marks"])
        : "";

    // ------------------------------------------------
    // Validate Student
    // ------------------------------------------------
    if ($selected_student_id <= 0) {

        $error = "Please select a valid student.";

    }

    // ------------------------------------------------
    // Validate Subject
    // ------------------------------------------------
    elseif ($selected_subject === "") {

        $error = "Please select a subject.";

    }

    // ------------------------------------------------
    // Validate Marks
    // ------------------------------------------------
    elseif ($entered_marks === "" || !is_numeric($entered_marks)) {

        $error = "Please enter valid marks.";

    }

    elseif ($entered_marks < 0 || $entered_marks > 100) {

        $error = "Marks must be between 0 and 100.";

    }

    else {

        $marks = intval($entered_marks);

        // ------------------------------------------------
        // Get Student Name using Student ID
        // ------------------------------------------------
        $student_stmt = mysqli_prepare(
            $conn,
            "SELECT name FROM students WHERE id = ? LIMIT 1"
        );

        if (!$student_stmt) {

            $error = "Database error. Please try again.";

        } else {

            mysqli_stmt_bind_param(
                $student_stmt,
                "i",
                $selected_student_id
            );

            mysqli_stmt_execute($student_stmt);

            $student_result = mysqli_stmt_get_result($student_stmt);

            if (!$student_result || mysqli_num_rows($student_result) === 0) {

                $error = "Selected student was not found.";

            } else {

                $student = mysqli_fetch_assoc($student_result);

                $student_name = $student["name"];

                // ------------------------------------------------
                // Verify that selected subject exists
                // ------------------------------------------------
                $subject_stmt = mysqli_prepare(
                    $conn,
                    "SELECT subject_name
                     FROM subjects
                     WHERE subject_name = ?
                     LIMIT 1"
                );

                if (!$subject_stmt) {

                    $error = "Database error. Please try again.";

                } else {

                    mysqli_stmt_bind_param(
                        $subject_stmt,
                        "s",
                        $selected_subject
                    );

                    mysqli_stmt_execute($subject_stmt);

                    $subject_result = mysqli_stmt_get_result($subject_stmt);

                    if (
                        !$subject_result ||
                        mysqli_num_rows($subject_result) === 0
                    ) {

                        $error = "Selected subject was not found.";

                    } else {

                        // ------------------------------------------------
                        // Check for duplicate marks
                        // ------------------------------------------------
                        $duplicate_stmt = mysqli_prepare(
                            $conn,
                            "SELECT id
                             FROM marks
                             WHERE student_id = ?
                             AND subject_name = ?
                             LIMIT 1"
                        );

                        if (!$duplicate_stmt) {

                            $error = "Database error. Please try again.";

                        } else {

                            mysqli_stmt_bind_param(
                                $duplicate_stmt,
                                "is",
                                $selected_student_id,
                                $selected_subject
                            );

                            mysqli_stmt_execute($duplicate_stmt);

                            $duplicate_result =
                                mysqli_stmt_get_result($duplicate_stmt);

                            if (
                                $duplicate_result &&
                                mysqli_num_rows($duplicate_result) > 0
                            ) {

                                $error =
                                    "Marks for this student and subject already exist.";

                            } else {

                                // ------------------------------------------------
                                // Insert Marks
                                // ------------------------------------------------
                                $insert_stmt = mysqli_prepare(
                                    $conn,
                                    "INSERT INTO marks
                                    (student_id, student_name, subject_name, marks)
                                    VALUES (?, ?, ?, ?)"
                                );

                                if (!$insert_stmt) {

                                    $error =
                                        "Unable to prepare database request.";

                                } else {

                                    mysqli_stmt_bind_param(
                                        $insert_stmt,
                                        "issi",
                                        $selected_student_id,
                                        $student_name,
                                        $selected_subject,
                                        $marks
                                    );

                                    if (mysqli_stmt_execute($insert_stmt)) {

                                        header("Location: marks.php");
                                        exit();

                                    } else {

                                        $error =
                                            "Unable to save marks. Please try again.";

                                    }

                                    mysqli_stmt_close($insert_stmt);
                                }
                            }

                            mysqli_stmt_close($duplicate_stmt);
                        }
                    }

                    mysqli_stmt_close($subject_stmt);
                }
            }

            mysqli_stmt_close($student_stmt);
        }
    }
}

// ----------------------------------------------------
// Fetch students
// ----------------------------------------------------
$students = mysqli_query(
    $conn,
    "SELECT id, name
     FROM students
     ORDER BY name ASC"
);

// ----------------------------------------------------
// Fetch subjects
// ----------------------------------------------------
$subjects = mysqli_query(
    $conn,
    "SELECT subject_name
     FROM subjects
     ORDER BY subject_name ASC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Marks - Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f7fb;
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

        .form-control,
        .form-select {
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus,
        .form-select:focus {
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
            Add Marks
        </h2>

        <?php if ($error !== "") { ?>

            <div
                class="alert alert-danger"
                role="alert"
            >
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php } ?>

        <form method="POST" action="">

            <!-- Student -->
            <div class="mb-3">

                <label
                    for="student_id"
                    class="form-label"
                >
                    Student
                </label>

                <select
                    id="student_id"
                    name="student_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Student
                    </option>

                    <?php
                    if ($students && mysqli_num_rows($students) > 0) {

                        while ($row = mysqli_fetch_assoc($students)) {
                    ?>

                        <option
                            value="<?php echo (int)$row["id"]; ?>"
                            <?php
                            if (
                                $selected_student_id ==
                                $row["id"]
                            ) {
                                echo "selected";
                            }
                            ?>
                        >
                            <?php
                            echo htmlspecialchars(
                                $row["name"],
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>
                        </option>

                    <?php
                        }

                    } else {
                    ?>

                        <option value="" disabled>
                            No students available
                        </option>

                    <?php } ?>

                </select>

            </div>


            <!-- Subject -->
            <div class="mb-3">

                <label
                    for="subject_name"
                    class="form-label"
                >
                    Subject
                </label>

                <select
                    id="subject_name"
                    name="subject_name"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Subject
                    </option>

                    <?php
                    if ($subjects && mysqli_num_rows($subjects) > 0) {

                        while ($row = mysqli_fetch_assoc($subjects)) {
                    ?>

                        <option
                            value="<?php
                                echo htmlspecialchars(
                                    $row["subject_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            <?php
                            if (
                                $selected_subject ===
                                $row["subject_name"]
                            ) {
                                echo "selected";
                            }
                            ?>
                        >
                            <?php
                            echo htmlspecialchars(
                                $row["subject_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>
                        </option>

                    <?php
                        }

                    } else {
                    ?>

                        <option value="" disabled>
                            No subjects available
                        </option>

                    <?php } ?>

                </select>

            </div>


            <!-- Marks -->
            <div class="mb-4">

                <label
                    for="marks"
                    class="form-label"
                >
                    Marks
                </label>

                <input
                    type="number"
                    id="marks"
                    name="marks"
                    class="form-control"
                    min="0"
                    max="100"
                    step="1"
                    value="<?php
                        echo htmlspecialchars(
                            $entered_marks,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter marks"
                    required
                >

                <div class="form-text">
                    Enter marks between 0 and 100.
                </div>

            </div>


            <!-- Buttons -->
            <div class="button-group">

                <button
                    type="submit"
                    name="save"
                    class="btn btn-success px-4"
                >
                    Save Marks
                </button>

                <a
                    href="marks.php"
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
