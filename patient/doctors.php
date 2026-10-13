<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Only logged-in patients can view this page.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'patient'
) {
    header("Location: ../login.php");
    exit;
}

// Retrieve all active doctors.
$sql = "SELECT id, name, specialization, qualification,
               available_days, consultation_fee
        FROM doctors
        WHERE status = 'active'
        ORDER BY name ASC";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load doctors. Please check your database.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Available Doctors | Hospital Appointment System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #075985;
            color: white;
            padding: 20px;
            text-align: center;
        }

        nav {
            background: #0c4a6e;
            padding: 14px;
            text-align: center;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin: 0 12px;
            font-weight: bold;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .doctor-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }

        .doctor-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .doctor-card h3 {
            color: #075985;
            margin-top: 0;
        }

        .fee {
            color: #047857;
            font-size: 19px;
            font-weight: bold;
        }

        .book-btn {
            display: inline-block;
            background: #0284c7;
            color: white;
            padding: 11px 16px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 10px;
        }

        .book-btn:hover {
            background: #0369a1;
        }

        .empty-message {
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
    </style>
</head>

<body>

<header>
    <h1>Available Doctors</h1>
    <p>Choose a doctor for your appointment</p>
</header>

<nav>
    <a href="../index.php">Home</a>
    <a href="doctors.php">Doctors</a>
    <a href="my_appointments.php">My Appointments</a>
    <a href="../logout.php">Logout</a>
</nav>

<div class="container">

    <h2>Hello,
        <?php
        echo htmlspecialchars(
            $_SESSION['name'] ?? 'Patient',
            ENT_QUOTES,
            'UTF-8'
        );
        ?>!
    </h2>

    <p>Select a doctor to book your appointment.</p>

    <div class="doctor-grid">

        <?php if ($result->num_rows > 0): ?>

            <?php while ($doctor = $result->fetch_assoc()): ?>

                <div class="doctor-card">

                    <h3>
                        <?php
                        echo htmlspecialchars(
                            $doctor['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </h3>

                    <p>
                        <strong>Specialization:</strong>
                        <?php
                        echo htmlspecialchars(
                            $doctor['specialization'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Qualification:</strong>
                        <?php
                        echo htmlspecialchars(
                            $doctor['qualification'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Available Days:</strong>
                        <?php
                        echo htmlspecialchars(
                            $doctor['available_days'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </p>

                    <p class="fee">
                        Consultation Fee: ₹
                        <?php
                        echo number_format(
                            (float) $doctor['consultation_fee'],
                            2
                        );
                        ?>
                    </p>

                    <a
                        class="book-btn"
                        href="book_appointment.php?doctor_id=<?php
                        echo (int) $doctor['id'];
                        ?>"
                    >
                        Book Appointment
                    </a>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">
                No active doctors are available at the moment.
            </p>

        <?php endif; ?>

    </div>

</div>

</body>
</html>