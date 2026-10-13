<?php

session_start();

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

    <title>Hospital Appointment System</title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<div class="container">

    <h1>CityCare Hospital</h1>

    <p>
        Welcome to our Hospital Appointment System.
    </p>

    <p>
        Book appointments with doctors easily and conveniently.
    </p>

    <?php if (isset($_SESSION["user_id"])): ?>

        <p>
            Welcome,
            <strong>
                <?= escape($_SESSION["user_name"]) ?>
            </strong>!
        </p>

        <p>
            Your role:
            <?= escape($_SESSION["user_role"]) ?>
        </p>

        <p>
            <a href="logout.php">Logout</a>
        </p>

    <?php else: ?>

        <p>
            <a href="register.php">Register as a Patient</a>
        </p>

        <p>
            <a href="login.php">Patient Login</a>
        </p>

    <?php endif; ?>

</div>

</body>

</html>