<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed. Please check config/database.php.');
}

$error = '';
$success = '';

$name = '';
$email = '';
$role = 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'patient';

    // Validate name
    if ($name === '' || strlen($name) < 2) {
        $error = 'Please enter your full name (at least 2 characters).';
    } elseif (strlen($name) > 100) {
        $error = 'Your name must not exceed 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($email) > 255) {
        $error = 'Your email address is too long.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must contain at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, ['patient', 'doctor'], true)) {
        $error = 'Please select a valid account type.';
    } else {
        try {
            // Check whether the email already exists
            $stmt = $conn->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            $stmt->bind_param('s', $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $emailExists = $result->num_rows > 0;

            $stmt->close();

            if ($emailExists) {
                $error = 'This email is already registered. Please log in instead.';
            } else {
                // Hash the password before storing it
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Create the user account
                $stmt = $conn->prepare(
                    "INSERT INTO users (name, email, password, role)
                     VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    'ssss',
                    $name,
                    $email,
                    $hashed_password,
                    $role
                );

                $stmt->execute();
                $stmt->close();

                // Redirect to login after successful registration
                header('Location: login.php?registered=1');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Registration failed. Please check your database structure.';

            // Show detailed error during local development only
            $error .= ' Details: ' . $e->getMessage();
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

    <title>Create Account | Hospital Appointment System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #e0f7fa, #eef2ff);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
            color: #263238;
        }

        .register-container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(20, 60, 80, 0.15);
        }

        .logo {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #e0f7fa;
            color: #087f8c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        h1 {
            text-align: center;
            color: #075e67;
            margin: 0 0 10px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #607d8b;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        label {
            display: block;
            margin: 17px 0 7px;
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            padding: 13px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            background: #fff;
            font-size: 15px;
            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #087f8c;
            box-shadow: 0 0 0 3px rgba(8, 127, 140, 0.12);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #087f8c;
            cursor: pointer;
            font-weight: bold;
        }

        .register-button {
            width: 100%;
            margin-top: 25px;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #087f8c;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .register-button:hover {
            background: #075e67;
        }

        .message {
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 18px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .error {
            color: #842029;
            background: #f8d7da;
        }

        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 14px;
            line-height: 1.8;
        }

        .footer a {
            color: #087f8c;
            font-weight: bold;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .home-link {
            display: block;
            margin-top: 15px;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 25px 20px;
            }

            h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<div class="register-container">

    <div class="logo">✚</div>

    <h1>Create Account</h1>

    <p class="subtitle">
        Join our Hospital Appointment System
    </p>

    <?php if ($error !== ''): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <label for="name">Full Name</label>

        <input
            type="text"
            id="name"
            name="name"
            placeholder="Enter your full name"
            maxlength="100"
            value="<?= htmlspecialchars($name) ?>"
            autocomplete="name"
            required
        >

        <label for="email">Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email"
            maxlength="255"
            value="<?= htmlspecialchars($email) ?>"
            autocomplete="email"
            required
        >

        <label for="role">Register As</label>

        <select id="role" name="role" required>
            <option value="patient"
                <?= $role === 'patient' ? 'selected' : '' ?>>
                Patient
            </option>

            <option value="doctor"
                <?= $role === 'doctor' ? 'selected' : '' ?>>
                Doctor
            </option>
        </select>

        <label for="password">Password</label>

        <div class="password-wrapper">
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Create a password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <button
                type="button"
                class="toggle-password"
                onclick="togglePassword('password', this)">
                Show
            </button>
        </div>

        <label for="confirm_password">Confirm Password</label>

        <div class="password-wrapper">
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Enter your password again"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <button
                type="button"
                class="toggle-password"
                onclick="togglePassword('confirm_password', this)">
                Show
            </button>
        </div>

        <button type="submit" class="register-button">
            Create Account
        </button>

    </form>

    <div class="footer">
        Already have an account?
        <a href="login.php">Login here</a>

        <a href="index.php" class="home-link">
            ← Back to Home
        </a>
    </div>

</div>

<script>
    function togglePassword(fieldId, button) {
        const field = document.getElementById(fieldId);

        if (field.type === 'password') {
            field.type = 'text';
            button.textContent = 'Hide';
        } else {
            field.type = 'password';
            button.textContent = 'Show';
        }
    }
</script>

</body>
</html>