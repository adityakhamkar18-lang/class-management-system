<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("config.php");

/*
|--------------------------------------------------------------------------
| Validate Subject ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: subjects.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch Subject
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, subject_name, subject_code, teacher_name
     FROM subjects
     WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$row) {
    header("Location: subjects.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Update Subject
|--------------------------------------------------------------------------
*/
if (isset($_POST['update'])) {

    $subject_name = trim($_POST['subject_name'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $teacher_name = trim($_POST['teacher_name'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    if ($subject_name === '' || $subject_code === '' || $teacher_name === '') {

        $error = "All fields are required.";

    } elseif (strlen($subject_name) < 2) {

        $error = "Subject name must contain at least 2 characters.";

    } elseif (strlen($subject_code) < 2) {

        $error = "Subject code must contain at least 2 characters.";

    } elseif (strlen($teacher_name) < 2) {

        $error = "Teacher name must contain at least 2 characters.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Subject Name
        |--------------------------------------------------------------------------
        */
        $check_name = mysqli_prepare(
            $conn,
            "SELECT id FROM subjects
             WHERE subject_name = ? AND id != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check_name,
            "si",
            $subject_name,
            $id
        );

        mysqli_stmt_execute($check_name);
        mysqli_stmt_store_result($check_name);

        if (mysqli_stmt_num_rows($check_name) > 0) {

            $error = "This subject name already exists.";

        }

        mysqli_stmt_close($check_name);

        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Subject Code
        |--------------------------------------------------------------------------
        */
        if (!isset($error)) {

            $check_code = mysqli_prepare(
                $conn,
                "SELECT id FROM subjects
                 WHERE subject_code = ? AND id != ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $check_code,
                "si",
                $subject_code,
                $id
            );

            mysqli_stmt_execute($check_code);
            mysqli_stmt_store_result($check_code);

            if (mysqli_stmt_num_rows($check_code) > 0) {

                $error = "This subject code already exists.";

            }

            mysqli_stmt_close($check_code);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Database
        |--------------------------------------------------------------------------
        */
        if (!isset($error)) {

            $update_stmt = mysqli_prepare(
                $conn,
                "UPDATE subjects
                 SET subject_name = ?,
                     subject_code = ?,
                     teacher_name = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update_stmt,
                "sssi",
                $subject_name,
                $subject_code,
                $teacher_name,
                $id
            );

            if (mysqli_stmt_execute($update_stmt)) {

                mysqli_stmt_close($update_stmt);

                header("Location: subjects.php");
                exit();

            } else {

                $error = "Unable to update subject. Please try again.";
                mysqli_stmt_close($update_stmt);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Keep Updated Values in Form
    |--------------------------------------------------------------------------
    */
    $row['subject_name'] = $subject_name;
    $row['subject_code'] = $subject_code;
    $row['teacher_name'] = $teacher_name;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Subject</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body>

<div class="container mt-5 mb-5">

    <div class="row justify-content-center">

        <div class="col-md-7 col-lg-6">

            <div class="card shadow">

                <div class="card-header bg-primary text-white">

                    <h4 class="mb-0">Edit Subject</h4>

                </div>

                <div class="card-body">

                    <?php if (isset($error)) { ?>

                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>

                    <?php } ?>

                    <form method="POST">

                        <!-- Subject Name -->
                        <div class="mb-3">

                            <label class="form-label">
                                Subject Name
                            </label>

                            <input
                                type="text"
                                name="subject_name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($row['subject_name']); ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                        <!-- Subject Code -->
                        <div class="mb-3">

                            <label class="form-label">
                                Subject Code
                            </label>

                            <input
                                type="text"
                                name="subject_code"
                                class="form-control"
                                value="<?php echo htmlspecialchars($row['subject_code']); ?>"
                                maxlength="50"
                                required
                            >

                        </div>

                        <!-- Teacher Name -->
                        <div class="mb-3">

                            <label class="form-label">
                                Teacher Name
                            </label>

                            <input
                                type="text"
                                name="teacher_name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($row['teacher_name']); ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                name="update"
                                class="btn btn-primary"
                            >
                                Update Subject
                            </button>

                            <a
                                href="subjects.php"
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

</div>

</body>
</html>