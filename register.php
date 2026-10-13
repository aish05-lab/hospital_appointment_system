<?php

session_start();

require_once __DIR__ . "/config/database.php";

$errors = [];
$name = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "") {
        $errors[] = "Name is required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if ($password === "") {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $errors[] = "This email is already registered.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $role = "patient";

            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, email, phone, password, role)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssss",
                $name,
                $email,
                $phone,
                $hashed_password,
                $role
            );

            if ($stmt->execute()) {

                $stmt->close();
                $check->close();

                header("Location: login.php?registered=1");
                exit;

            } else {

                $errors[] = "Registration failed. Please try again.";

            }

            $stmt->close();
        }

        $check->close();
    }
}

function escape($value) {
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Registration</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="container">

    <h1>Patient Registration</h1>

    <p>Create your account to book hospital appointments.</p>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= escape($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <label>Full Name</label>
        <input
            type="text"
            name="name"
            value="<?= escape($name) ?>"
            maxlength="100"
            autocomplete="name"
            required
        >

        <label>Email Address</label>
        <input
            type="email"
            name="email"
            value="<?= escape($email) ?>"
            maxlength="150"
            autocomplete="email"
            required
        >

        <label>Phone Number</label>
        <input
            type="tel"
            name="phone"
            value="<?= escape($phone) ?>"
            maxlength="20"
            autocomplete="tel"
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <label>Confirm Password</label>
        <input
            type="password"
            name="confirm_password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <button type="submit">Register</button>

    </form>

    <p>
        Already registered?
        <a href="login.php">Login here</a>
    </p>

</div>

</body>
</html>