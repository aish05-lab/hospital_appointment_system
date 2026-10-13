<?php
session_start();

require_once __DIR__ . '/config/database.php';

// Redirect users who are already logged in.
if (
    isset($_SESSION['user_id'], $_SESSION['role']) &&
    $_SESSION['user_id'] > 0
) {
    if ($_SESSION['role'] === 'patient') {
        header("Location: patient/doctors.php");
        exit;
    }

    if ($_SESSION['role'] === 'doctor') {
        header("Location: doctor/dashboard.php");
        exit;
    }

    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
        exit;
    }

    // Clear an unrecognized role.
    $_SESSION = [];
    session_destroy();
    session_start();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (!is_string($password) || $password === '') {

        $error = "Please enter your password.";

    } else {

        // Find the user by email.
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $error = "Unable to process your login. Please try again.";

        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            $stmt->close();

            // Verify the password against the stored password hash.
            if (
                $user &&
                password_verify($password, $user['password'])
            ) {

                // Regenerate the session ID after successful login.
                session_regenerate_id(true);

                // Store the user's details in the session.
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Redirect according to the user's role.
                if ($user['role'] === 'patient') {

                    header("Location: patient/doctors.php");
                    exit;

                } elseif ($user['role'] === 'doctor') {

                    header("Location: doctor/dashboard.php");
                    exit;

                } elseif ($user['role'] === 'admin') {

                    header("Location: admin/dashboard.php");
                    exit;

                } else {

                    $_SESSION = [];
                    session_destroy();

                    session_start();

                    $error = "Your account has an invalid role.";
                }

            } else {

                // Use the same message for an unknown email
                // and an incorrect password.
                $error = "Invalid email or password.";

            }
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

    <title>Login | Hospital Appointment System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eaf4f8;
            color: #1e293b;
        }

        header {
            background: #075985;
            color: white;
            text-align: center;
            padding: 20px;
        }

        .login-container {
            max-width: 430px;
            margin: 45px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.10);
        }

        h2 {
            text-align: center;
            color: #075985;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #7dd3fc;
            border-color: #0284c7;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            background: #0284c7;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0369a1;
        }

        .error {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 6px;
            text-align: center;
        }

        .links {
            text-align: center;
            margin-top: 20px;
        }

        a {
            color: #0369a1;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<header>
    <h1>Hospital Appointment System</h1>
    <p>Login to manage your appointments</p>
</header>

<div class="login-container">

    <h2>Login</h2>

    <?php if ($error !== ''): ?>

        <p class="error">
            <?php
            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </p>

    <?php endif; ?>

    <form method="POST"
          action="login.php"
          autocomplete="on">

        <label for="email">Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            value="<?php
            echo htmlspecialchars(
                $email,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>"
            autocomplete="username"
            required
        >

        <label for="password">Password</label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Login
        </button>

    </form>

    <div class="links">
        <p>
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>

        <p>
            <a href="index.php">Back to Home</a>
        </p>
    </div>

</div>

</body>
</html>