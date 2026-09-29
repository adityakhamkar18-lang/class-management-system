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

$roll_no = "";
$name = "";
$email = "";
$phone = "";
$gender = "";
$class_name = "";

// ----------------------------------------------------
// Process form submission
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save"])) {

    // Get and clean form data
    $roll_no = trim($_POST["roll_no"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $class_name = trim($_POST["class_name"] ?? "");
    $password = $_POST["password"] ?? "";

    // ------------------------------------------------
    // Validate Roll Number
    // ------------------------------------------------
    if ($roll_no === "") {

        $error = "Please enter the roll number.";

    }

    // ------------------------------------------------
    // Validate Name
    // ------------------------------------------------
    elseif ($name === "") {

        $error = "Please enter the student's name.";

    }

    elseif (strlen($name) < 2) {

        $error = "Student name must contain at least 2 characters.";

    }

    // ------------------------------------------------
    // Validate Email
    // ------------------------------------------------
    elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }

    // ------------------------------------------------
    // Validate Phone
    // ------------------------------------------------
    elseif ($phone !== "" && !preg_match('/^[0-9]{10}$/', $phone)) {

        $error = "Phone number must contain exactly 10 digits.";

    }

    // ------------------------------------------------
    // Validate Gender
    // ------------------------------------------------
    elseif (
        $gender !== "" &&
        !in_array($gender, ["Male", "Female"], true)
    ) {

        $error = "Please select a valid gender.";

    }

    // ------------------------------------------------
    // Validate Class
    // ------------------------------------------------
    elseif ($class_name === "") {

        $error = "Please enter the class.";

    }

    // ------------------------------------------------
    // Validate Password
    // ------------------------------------------------
    elseif ($password === "") {

        $error = "Please enter a password.";

    }

    elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    }

    else {

        // ------------------------------------------------
        // Check duplicate Roll Number
        // ------------------------------------------------
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM students WHERE roll_no = ? LIMIT 1"
        );

        if (!$check_stmt) {

            $error = "Database error. Please try again.";

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "s",
                $roll_no
            );

            mysqli_stmt_execute($check_stmt);

            $check_result = mysqli_stmt_get_result($check_stmt);

            if (
                $check_result &&
                mysqli_num_rows($check_result) > 0
            ) {

                $error = "This roll number already exists.";

            }

            mysqli_stmt_close($check_stmt);
        }

        // ------------------------------------------------
        // Insert Student
        // ------------------------------------------------
        if ($error === "") {

            // Hash password before storing it
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO students
                (roll_no, name, email, phone, gender, class_name, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$insert_stmt) {

                $error = "Unable to prepare database request.";

            } else {

                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "sssssss",
                    $roll_no,
                    $name,
                    $email,
                    $phone,
                    $gender,
                    $class_name,
                    $hashed_password
                );

                if (mysqli_stmt_execute($insert_stmt)) {

                    mysqli_stmt_close($insert_stmt);

                    header("Location: students.php");
                    exit();

                } else {

                    $error = "Unable to save student. Please try again.";

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

    <title>Add Student - Class Management System</title>

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
            max-width: 650px;
            margin: 40px auto;
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
            padding: 11px 12px;
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
            Add Student
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
                    id="roll_no"
                    name="roll_no"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $roll_no,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter roll number"
                    maxlength="50"
                    required
                >

            </div>


            <!-- Student Name -->
            <div class="mb-3">

                <label
                    for="name"
                    class="form-label"
                >
                    Student Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter student name"
                    maxlength="100"
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
                    id="email"
                    name="email"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter email address"
                    maxlength="150"
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
                    type="tel"
                    id="phone"
                    name="phone"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $phone,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter 10-digit phone number"
                    maxlength="10"
                    pattern="[0-9]{10}"
                >

                <div class="form-text">
                    Enter exactly 10 digits.
                </div>

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
                    id="gender"
                    name="gender"
                    class="form-select"
                >

                    <option value="">
                        Select Gender
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
                    id="class_name"
                    name="class_name"
                    class="form-control"
                    value="<?php
                        echo htmlspecialchars(
                            $class_name,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                    placeholder="Enter class"
                    maxlength="100"
                    required
                >

            </div>


            <!-- Password -->
            <div class="mb-4">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter password"
                    minlength="6"
                    required
                >

                <div class="form-text">
                    Password must contain at least 6 characters.
                </div>

            </div>


            <!-- Buttons -->
            <div class="button-group">

                <button
                    type="submit"
                    name="save"
                    class="btn btn-success px-4"
                >
                    Save Student
                </button>

                <a
                    href="students.php"
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
