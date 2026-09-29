<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("config.php");

/*
|--------------------------------------------------------------------------
| Validate Teacher ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: teachers.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch Teacher
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, name, email, phone, subject
     FROM teachers
     WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$row) {
    header("Location: teachers.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Update Teacher
|--------------------------------------------------------------------------
*/
if (isset($_POST['update'])) {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    if ($name === '' || $email === '' || $phone === '' || $subject === '') {

        $error = "All fields are required.";

    } elseif (strlen($name) < 2) {

        $error = "Teacher name must contain at least 2 characters.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {

        $error = "Phone number must contain exactly 10 digits.";

    } elseif (strlen($subject) < 2) {

        $error = "Subject must contain at least 2 characters.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Email
        |--------------------------------------------------------------------------
        */
        $check_email = mysqli_prepare(
            $conn,
            "SELECT id
             FROM teachers
             WHERE email = ? AND id != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check_email,
            "si",
            $email,
            $id
        );

        mysqli_stmt_execute($check_email);
        mysqli_stmt_store_result($check_email);

        if (mysqli_stmt_num_rows($check_email) > 0) {

            $error = "This email address is already used by another teacher.";
        }

        mysqli_stmt_close($check_email);

        /*
        |--------------------------------------------------------------------------
        | Update Teacher
        |--------------------------------------------------------------------------
        */
        if (!isset($error)) {

            $update_stmt = mysqli_prepare(
                $conn,
                "UPDATE teachers
                 SET name = ?,
                     email = ?,
                     phone = ?,
                     subject = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update_stmt,
                "ssssi",
                $name,
                $email,
                $phone,
                $subject,
                $id
            );

            if (mysqli_stmt_execute($update_stmt)) {

                mysqli_stmt_close($update_stmt);

                header("Location: teachers.php");
                exit();

            } else {

                $error = "Unable to update teacher. Please try again.";

                mysqli_stmt_close($update_stmt);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Keep Entered Values
    |--------------------------------------------------------------------------
    */
    $row['name'] = $name;
    $row['email'] = $email;
    $row['phone'] = $phone;
    $row['subject'] = $subject;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Teacher</title>

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

                    <h4 class="mb-0">Edit Teacher</h4>

                </div>

                <div class="card-body">

                    <?php if (isset($error)) { ?>

                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>

                    <?php } ?>

                    <form method="POST">

                        <!-- Name -->
                        <div class="mb-3">

                            <label class="form-label">
                                Teacher Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($row['name']); ?>"
                                maxlength="100"
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
                                value="<?php echo htmlspecialchars($row['email']); ?>"
                                maxlength="150"
                                required
                            >

                        </div>

                        <!-- Phone -->
                        <div class="mb-3">

                            <label class="form-label">
                                Phone
                            </label>

                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                value="<?php echo htmlspecialchars($row['phone']); ?>"
                                maxlength="10"
                                pattern="[0-9]{10}"
                                inputmode="numeric"
                                required
                            >

                        </div>

                        <!-- Subject -->
                        <div class="mb-3">

                            <label class="form-label">
                                Subject
                            </label>

                            <input
                                type="text"
                                name="subject"
                                class="form-control"
                                value="<?php echo htmlspecialchars($row['subject']); ?>"
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
                                Update Teacher
                            </button>

                            <a
                                href="teachers.php"
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