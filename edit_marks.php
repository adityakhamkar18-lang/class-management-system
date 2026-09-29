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
| Get Marks ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/
if (!$id || $id <= 0) {
    header("Location: marks.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch Marks Record
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, student_id, student_name, subject_name, marks
     FROM marks
     WHERE id = ?"
);

if (!$stmt) {
    header("Location: marks.php");
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
    header("Location: marks.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Fetch Subjects
|--------------------------------------------------------------------------
*/
$subjects = [];

$subjects_result = mysqli_query(
    $conn,
    "SELECT subject_name
     FROM subjects
     ORDER BY subject_name ASC"
);

if ($subjects_result) {

    while ($subject_row = mysqli_fetch_assoc($subjects_result)) {

        $subjects[] = $subject_row['subject_name'];

    }
}


/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/
$subject_name = $row['subject_name'];
$marks = $row['marks'];

$error = "";


/*
|--------------------------------------------------------------------------
| Update Marks
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_name = trim($_POST['subject_name'] ?? "");
    $marks_input = trim($_POST['marks'] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validate Subject
    |--------------------------------------------------------------------------
    */
    if ($subject_name === "") {

        $error = "Please select a subject.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Subject Exists
    |--------------------------------------------------------------------------
    */
    elseif (!in_array($subject_name, $subjects, true)) {

        $error = "Please select a valid subject.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Marks
    |--------------------------------------------------------------------------
    */
    elseif ($marks_input === "" || !is_numeric($marks_input)) {

        $error = "Please enter valid marks.";

    }


    elseif ((float)$marks_input < 0 || (float)$marks_input > 100) {

        $error = "Marks must be between 0 and 100.";

    }


    /*
    |--------------------------------------------------------------------------
    | Convert Marks
    |--------------------------------------------------------------------------
    */
    else {

        $marks = (float)$marks_input;


        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Subject for Same Student
        |--------------------------------------------------------------------------
        */
        $duplicate_stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM marks
             WHERE student_id = ?
             AND subject_name = ?
             AND id != ?
             LIMIT 1"
        );

        if ($duplicate_stmt) {

            mysqli_stmt_bind_param(
                $duplicate_stmt,
                "isi",
                $row['student_id'],
                $subject_name,
                $id
            );

            mysqli_stmt_execute($duplicate_stmt);

            $duplicate_result =
                mysqli_stmt_get_result($duplicate_stmt);

            $duplicate_exists =
                mysqli_num_rows($duplicate_result) > 0;

            mysqli_stmt_close($duplicate_stmt);


            if ($duplicate_exists) {

                $error =
                    "Marks for this student and subject already exist.";

            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        $update_stmt = mysqli_prepare(
            $conn,
            "UPDATE marks
             SET subject_name = ?, marks = ?
             WHERE id = ?"
        );

        if ($update_stmt) {

            mysqli_stmt_bind_param(
                $update_stmt,
                "sdi",
                $subject_name,
                $marks,
                $id
            );

            if (mysqli_stmt_execute($update_stmt)) {

                mysqli_stmt_close($update_stmt);

                header("Location: marks.php");
                exit();

            } else {

                $error =
                    "Unable to update marks. Please try again.";

            }

            mysqli_stmt_close($update_stmt);

        } else {

            $error =
                "Unable to update marks. Please try again.";
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

    <title>Edit Marks - Class Management System</title>


    <!-- Bootstrap CSS -->

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


            <!-- Header -->

            <div class="card-header bg-primary text-white">

                <h3 class="mb-0">
                    📊 Edit Marks
                </h3>

            </div>


            <div class="card-body">


                <!-- Error -->

                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">

                        <?php
                        echo htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>

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


                    <!-- Subject -->

                    <div class="mb-3">

                        <label
                            for="subject_name"
                            class="form-label fw-bold"
                        >
                            Subject
                        </label>

                        <select
                            name="subject_name"
                            id="subject_name"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Subject --
                            </option>


                            <?php foreach ($subjects as $subject): ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $subject,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                    ?>"
                                    <?php
                                    if ($subject === $subject_name) {
                                        echo "selected";
                                    }
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $subject,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Marks -->

                    <div class="mb-3">

                        <label
                            for="marks"
                            class="form-label fw-bold"
                        >
                            Marks
                        </label>

                        <input
                            type="number"
                            name="marks"
                            id="marks"
                            class="form-control"
                            min="0"
                            max="100"
                            step="0.01"
                            value="<?php
                                echo htmlspecialchars(
                                    (string)$marks,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required
                        >

                        <div class="form-text">
                            Enter marks between 0 and 100.
                        </div>

                    </div>


                    <!-- Buttons -->

                    <div class="mt-4">

                        <button
                            type="submit"
                            name="update"
                            class="btn btn-primary"
                        >
                            Update Marks
                        </button>


                        <a
                            href="marks.php"
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