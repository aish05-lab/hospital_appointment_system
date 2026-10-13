<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();

require_once __DIR__ . '/../config/database.php';

// Check database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Check config/database.php.");
}

// Check whether the patient is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Only patients can access this page
if (($_SESSION['role'] ?? '') !== 'patient') {
    die("Access denied. Please log in with a patient account.");
}

$patient_id = (int) $_SESSION['user_id'];
$appointments = [];
$error = '';

try {
    // Fetch appointments belonging to the logged-in patient
    $stmt = $conn->prepare(
        "SELECT
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.reason,
            a.status,
            d.name AS doctor_name,
            d.specialization
         FROM appointments AS a
         INNER JOIN doctors AS d ON a.doctor_id = d.id
         WHERE a.patient_id = ?
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    $stmt->close();

} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Appointments</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #eef5f7;
            color: #263238;
        }

        .container {
            max-width: 1000px;
            margin: 25px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #087f8c;
            text-align: center;
        }

        .appointment {
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #cfd8dc;
            border-left: 5px solid #087f8c;
            border-radius: 8px;
            background: #ffffff;
        }

        .appointment h2 {
            color: #075e67;
            margin-top: 0;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 5px;
            font-weight: bold;
            background: #fff3cd;
            color: #664d03;
        }

        .status.cancelled {
            background: #f8d7da;
            color: #842029;
        }

        .status.confirmed,
        .status.completed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .cancel-button {
            display: inline-block;
            margin-top: 12px;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            background: #dc3545;
            color: white;
            cursor: pointer;
            font-size: 15px;
        }

        .cancel-button:hover {
            background: #bb2d3b;
        }

        .empty {
            padding: 25px;
            text-align: center;
            background: #e8f7f8;
            border-radius: 8px;
        }

        .error {
            padding: 15px;
            background: #f8d7da;
            color: #842029;
            border-radius: 6px;
            overflow-wrap: anywhere;
        }

        .links {
            margin-top: 25px;
            text-align: center;
        }

        .links a {
            display: inline-block;
            margin: 5px;
            padding: 12px 18px;
            background: #087f8c;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .links a:hover {
            background: #075e67;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>My Appointments</h1>

    <?php if (isset($_GET['booked'])): ?>
        <p style="color: green; text-align: center;">
            Your appointment was booked successfully!
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="error">
            <strong>Database error:</strong>
            <?= htmlspecialchars($error) ?>
        </div>

    <?php elseif (count($appointments) === 0): ?>

        <div class="empty">
            <h2>No Appointments Found</h2>

            <p>You have not booked any appointments yet.</p>

            <p>Visit the doctors page to book an appointment.</p>
        </div>

    <?php else: ?>

        <p>
            Total appointments:
            <strong><?= count($appointments) ?></strong>
        </p>

        <?php foreach ($appointments as $appointment): ?>

            <?php
            $status = strtolower($appointment['status'] ?? 'pending');
            $statusClass = preg_replace('/[^a-z]/', '', $status);
            ?>

            <div class="appointment">

                <h2>
                    Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                </h2>

                <p>
                    <strong>Specialization:</strong>
                    <?= htmlspecialchars($appointment['specialization']) ?>
                </p>

                <p>
                    <strong>Appointment Date:</strong>
                    <?= htmlspecialchars($appointment['appointment_date']) ?>
                </p>

                <p>
                    <strong>Appointment Time:</strong>
                    <?= htmlspecialchars(substr($appointment['appointment_time'], 0, 5)) ?>
                </p>

                <p>
                    <strong>Reason:</strong>
                    <?= htmlspecialchars($appointment['reason'] ?? 'Not specified') ?>
                </p>

                <p>
                    <strong>Status:</strong>

                    <span class="status <?= htmlspecialchars($statusClass) ?>">
                        <?= htmlspecialchars(ucfirst($status)) ?>
                    </span>
                </p>

                <?php
                $appointmentTimestamp = strtotime(
                    $appointment['appointment_date'] . ' ' .
                    $appointment['appointment_time']
                );
                ?>

                <?php if (
                    in_array($status, ['pending', 'confirmed'], true) &&
                    $appointmentTimestamp !== false &&
                    $appointmentTimestamp > time()
                ): ?>

                    <form method="POST"
                          action="cancel_appointment.php"
                          onsubmit="return confirm('Are you sure you want to cancel this appointment?');">

                        <input
                            type="hidden"
                            name="appointment_id"
                            value="<?= (int) $appointment['id'] ?>"
                        >

                        <button type="submit" class="cancel-button">
                            Cancel Appointment
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <div class="links">
        <a href="doctors.php">Book Another Appointment</a>
        <a href="../index.php">Home</a>
    </div>

</div>

</body>
</html>