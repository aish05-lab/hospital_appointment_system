<?php

session_start();

require_once __DIR__ . "/config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Enter a valid email address.";

    } elseif ($password === "") {

        $error = "Enter your password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (
            $user &&
            password_verify($password, $user["password"])
        ) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_role"] = $user["role"];

            $stmt->close();

            header("Location: index.php");
            exit;

        } else {

            $error = "Invalid email address or password.";

        }

        $stmt->close();
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

    <title>Patient Login</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="container">

    <h1>Patient Login</h1>

    <p>Login to access your hospital appointments.</p>

    <?php if (isset($_GET["registered"])): ?>
        <p class="success">
            Registration successful! Please log in.
        </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= escape($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <label>Email Address</label>
        <input
            type="email"
            name="email"
            maxlength="150"
            autocomplete="email"
            required
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>

</body>
</html>