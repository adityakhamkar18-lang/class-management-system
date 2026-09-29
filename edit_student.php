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
| Get Student ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Validate Student ID
|--------------------------------------------------------------------------
*/
if (!$id || $id <= 0) {
    header("Location: students.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Fetch Student
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, roll_no, name, email, phone, gender, class_name
     FROM students
     WHERE id = ?"
);

if (!$stmt) {
    header("Location: students.php");
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| Student Not Found
|--------------------------------------------------------------------------
*/
if (!$row) {
    header("Location: students.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/
$roll_no = $row['roll_no'];
$name = $row['name'];
$email = $row['email'];
$phone = $row['phone'];
$gender = $row['gender'];
$class_name = $row['class_name'];

$error = "";


/*
|--------------------------------------------------------------------------
| Update Student
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $roll_no = trim($_POST['roll_no'] ?? "");
    $name = trim($_POST['name'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $phone = trim($_POST['phone'] ?? "");
    $gender = trim($_POST['gender'] ?? "");
    $class_name = trim($_POST['class_name'] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validate Roll Number
    |--------------------------------------------------------------------------
    */
    if ($roll_no === "") {

        $error = "Please enter the roll number.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Name
    |--------------------------------------------------------------------------
    */
    elseif ($name === "") {

        $error = "Please enter the student's name.";

    }

    elseif (strlen($name) < 2) {

        $error = "Student name must contain at least 2 characters.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Email
    |--------------------------------------------------------------------------
    */
    elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Phone
    |--------------------------------------------------------------------------
    */
    elseif ($phone === "") {

        $error = "Please enter the phone number.";

    }

    elseif (!preg_match('/^[0-9]{10}$/', $phone)) {

        $error = "Phone number must contain exactly 10 digits.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Gender
    |--------------------------------------------------------------------------
    */
    elseif (!in_array($gender, ["Male", "Female"], true)) {

        $error = "Please select a valid gender.";

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Class
    |--------------------------------------------------------------------------
    */
    elseif ($class_name === "") {

        $error = "Please enter the class.";

    }


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Roll Number
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        $duplicate_roll_stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM students
             WHERE roll_no = ?
             AND id != ?
             LIMIT 1"
        );

        if ($duplicate_roll_stmt) {

            mysqli_stmt_bind_param(
                $duplicate_roll_stmt,
                "si",
                $roll_no,
                $id
            );

            mysqli_stmt_execute($duplicate_roll_stmt);

            $duplicate_result =
                mysqli_stmt_get_result($duplicate_roll_stmt);

            if (mysqli_num_rows($duplicate_result) > 0) {

                $error = "This roll number is already assigned to another student.";

            }

            mysqli_stmt_close($duplicate_roll_stmt);

        } else {

            $error = "Unable to validate roll number. Please try again.";

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */
    if ($error === "" && $email !== "") {

        $duplicate_email_stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM students
             WHERE email = ?
             AND id != ?
             LIMIT 1"
        );

        if ($duplicate_email_stmt) {

            mysqli_stmt_bind_param(
                $duplicate_email_stmt,
                "si",
                $email,
                $id
            );

            mysqli_stmt_execute($duplicate_email_stmt);

            $duplicate_email_result =
                mysqli_stmt_get_result($duplicate_email_stmt);

            if (mysqli_num_rows($duplicate_email_result) > 0) {

                $error = "This email address is already registered.";

            }

            mysqli_stmt_close($duplicate_email_stmt);

        } else {

            $error = "Unable to validate email. Please try again.";

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
            "UPDATE students
             SET roll_no = ?,
                 name = ?,
                 email = ?,
                 phone = ?,
                 gender = ?,
                 class_name = ?
             WHERE id = ?"
        );

        if ($update_stmt) {

            mysqli_stmt_bind_param(
                $update_stmt,
                "ssssssi",
                $roll_no,
                $name,
                $email,
                $phone,
                $gender,
                $class_name,
                $id
            );

            if (mysqli_stmt_execute($update_stmt)) {

                mysqli_stmt_close($update_stmt);

                header("Location: students.php");
                exit();

            } else {

                $error =
                    "Unable to update student. Please try again.";
            }

            mysqli_stmt_close($update_stmt);

        } else {

            $error =
                "Unable to update student. Please try again.";
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

    <title>Edit Student - Class Management System</title>


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
            max-width: 650px;

            margin: 40px auto;
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


        .form-label {
            font-weight: bold;
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
                    👨‍🎓 Edit Student
                </h3>

            </div>


            <div class="card-body">


                <!-- Error Message -->

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


                    <!-- Roll Number -->

                    <div class="mb-3">

                        <label
                            for="roll_no"
                            class="form-label"
                        >
                            Roll No
                        </label>

                        <input
                            type="text"
                            name="roll_no"
                            id="roll_no"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $roll_no,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Name -->

                    <div class="mb-3">

                        <label
                            for="name"
                            class="form-label"
                        >
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $name,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Email -->

                    <div class="mb-3">

                        <label
                            for="email"
                            class="form-label"
                        >
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $email,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                        >

                    </div>


                    <!-- Phone -->

                    <div class="mb-3">

                        <label
                            for="phone"
                            class="form-label"
                        >
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            id="phone"
                            class="form-control"
                            maxlength="10"
                            inputmode="numeric"
                            value="<?php
                                echo htmlspecialchars(
                                    $phone,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Gender -->

                    <div class="mb-3">

                        <label
                            for="gender"
                            class="form-label"
                        >
                            Gender
                        </label>

                        <select
                            name="gender"
                            id="gender"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Gender --
                            </option>

                            <option
                                value="Male"
                                <?php
                                if ($gender === "Male") {
                                    echo "selected";
                                }
                                ?>
                            >
                                Male
                            </option>

                            <option
                                value="Female"
                                <?php
                                if ($gender === "Female") {
                                    echo "selected";
                                }
                                ?>
                            >
                                Female
                            </option>

                        </select>

                    </div>


                    <!-- Class -->

                    <div class="mb-3">

                        <label
                            for="class_name"
                            class="form-label"
                        >
                            Class
                        </label>

                        <input
                            type="text"
                            name="class_name"
                            id="class_name"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars(
                                    $class_name,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required
                        >

                    </div>


                    <!-- Buttons -->

                    <div class="mt-4">

                        <button
                            type="submit"
                            name="update"
                            class="btn btn-primary"
                        >
                            Update Student
                        </button>


                        <a
                            href="students.php"
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