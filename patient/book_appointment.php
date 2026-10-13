<?php
// Show PHP errors during development
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();

require_once __DIR__ . '/../config/database.php';

// Check database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Check config/database.php.');
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Only patients can book appointments
if (($_SESSION['role'] ?? '') !== 'patient') {
    die('Access denied. Please log in with a patient account.');
}

$patient_id = (int) $_SESSION['user_id'];

$error = '';
$doctor = null;
$doctor_id = 0;

// Get doctor ID from the form or URL
$rawDoctorId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['doctor_id'] ?? '')
    : ($_GET['doctor_id'] ?? '');

if (filter_var($rawDoctorId, FILTER_VALIDATE_INT) !== false) {
    $doctor_id = (int) $rawDoctorId;
}

if ($doctor_id < 1) {
    die(
        'Invalid doctor ID. Please return to the doctors page and click Book Appointment again.'
    );
}

try {
    // Find the selected doctor
    $stmt = $conn->prepare(
        "SELECT id, name, specialization, consultation_fee
         FROM doctors
         WHERE id = ? AND status = 'active'"
    );

    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();

    $stmt->close();

    if (!$doctor) {
        die('Doctor not found or unavailable. Please select another doctor.');
    }

    // Process the booking form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $appointment_date = trim($_POST['appointment_date'] ?? '');
        $appointment_time = trim($_POST['appointment_time'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        // Validate date
        $validDate = false;

        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)
        ) {
            $dateParts = explode('-', $appointment_date);

            $validDate = checkdate(
                (int) $dateParts[1],
                (int) $dateParts[2],
                (int) $dateParts[0]
            );
        }

        // Validate time in HH:MM format
        $validTime = preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $appointment_time
        );

        if (!$validDate) {
            $error = 'Please select a valid appointment date.';
        } elseif (!$validTime) {
            $error = 'Please select a valid appointment time.';
        } elseif (
            strtotime($appointment_date . ' ' . $appointment_time)
            <= time()
        ) {
            $error = 'Please choose a future date and time.';
        } elseif (strlen($reason) > 1000) {
            $error = 'The reason must not exceed 1000 characters.';
        } else {
            // Check whether the selected time is already booked
            $check = $conn->prepare(
                "SELECT id
                 FROM appointments
                 WHERE doctor_id = ?
                   AND appointment_date = ?
                   AND appointment_time = ?
                   AND status IN ('pending', 'confirmed')
                 LIMIT 1"
            );

            $check->bind_param(
                'iss',
                $doctor_id,
                $appointment_date,
                $appointment_time
            );

            $check->execute();
            $checkResult = $check->get_result();

            $slotTaken = $checkResult->num_rows > 0;

            $check->close();

            if ($slotTaken) {
                $error = 'This time slot is already booked. Please choose another time.';
            } else {
                // Save the appointment
                $insert = $conn->prepare(
                    "INSERT INTO appointments
                    (
                        patient_id,
                        doctor_id,
                        appointment_date,
                        appointment_time,
                        reason,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, 'pending')"
                );

                $insert->bind_param(
                    'iisss',
                    $patient_id,
                    $doctor_id,
                    $appointment_date,
                    $appointment_time,
                    $reason
                );

                $insert->execute();
                $insert->close();

                // Show success page instead of redirecting
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport"
                          content="width=device-width, initial-scale=1.0">
                    <title>Appointment Booked</title>

                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background: #eef5f7;
                            text-align: center;
                            padding: 40px 15px;
                        }

                        .box {
                            max-width: 500px;
                            margin: 40px auto;
                            padding: 30px;
                            background: white;
                            border-radius: 12px;
                            box-shadow: 0 4px 15px rgba(0,0,0,.1);
                        }

                        h1 {
                            color: #087f8c;
                        }

                        a {
                            display: inline-block;
                            margin: 10px;
                            padding: 12px 18px;
                            background: #087f8c;
                            color: white;
                            text-decoration: none;
                            border-radius: 6px;
                        }

                        a:hover {
                            background: #075e67;
                        }
                    </style>
                </head>

                <body>
                    <div class="box">
                        <h1>Appointment Booked Successfully!</h1>

                        <p>Your appointment has been saved.</p>

                        <p>
                            <strong>Doctor:</strong>
                            <?= htmlspecialchars($doctor['name']) ?>
                        </p>

                        <p>
                            <strong>Date:</strong>
                            <?= htmlspecialchars($appointment_date) ?>
                        </p>

                        <p>
                            <strong>Time:</strong>
                            <?= htmlspecialchars($appointment_time) ?>
                        </p>

                        <p>
                            <strong>Status:</strong> Pending
                        </p>

                        <a href="my_appointments.php">
                            My Appointments
                        </a>

                        <a href="doctors.php">
                            Back to Doctors
                        </a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            }
        }
    }
} catch (Throwable $e) {
    // Show the real error during local development
    $error = 'Booking error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Book Appointment - Hospital Appointment System</title>

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
            max-width: 600px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
        }

        h1 {
            text-align: center;
            color: #087f8c;
        }

        .doctor {
            background: #e5f6f7;
            border-left: 5px solid #087f8c;
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #b0bec5;
            border-radius: 6px;
            font-size: 16px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 25px;
            border: none;
            border-radius: 6px;
            background: #087f8c;
            color: white;
            font-size: 17px;
            cursor: pointer;
        }

        button:hover {
            background: #075e67;
        }

        .error {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            overflow-wrap: anywhere;
        }

        .links {
            text-align: center;
            margin-top: 22px;
        }

        .links a {
            color: #087f8c;
            margin: 8px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Book an Appointment</h1>

    <?php if ($doctor): ?>

        <div class="doctor">
            <h2><?= htmlspecialchars($doctor['name']) ?></h2>

            <p>
                <strong>Specialization:</strong>
                <?= htmlspecialchars($doctor['specialization']) ?>
            </p>

            <p>
                <strong>Consultation Fee:</strong>
                ₹<?= htmlspecialchars((string) $doctor['consultation_fee']) ?>
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error">
                <strong>Please check:</strong>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="book_appointment.php?doctor_id=<?= (int) $doctor['id'] ?>">

            <input type="hidden"
                   name="doctor_id"
                   value="<?= (int) $doctor['id'] ?>">

            <label for="appointment_date">Appointment Date</label>

            <input
                type="date"
                id="appointment_date"
                name="appointment_date"
                min="<?= date('Y-m-d') ?>"
                value="<?= htmlspecialchars($_POST['appointment_date'] ?? '') ?>"
                required
            >

            <label for="appointment_time">Appointment Time</label>

            <input
                type="time"
                id="appointment_time"
                name="appointment_time"
                value="<?= htmlspecialchars($_POST['appointment_time'] ?? '') ?>"
                required
            >

            <label for="reason">Reason for Appointment</label>

            <textarea
                id="reason"
                name="reason"
                maxlength="1000"
                placeholder="Briefly describe the reason for your visit."
            ><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>

            <button type="submit">Confirm Appointment</button>

        </form>

    <?php endif; ?>

    <div class="links">
        <a href="doctors.php">Back to Doctors</a>
        |
        <a href="my_appointments.php">My Appointments</a>
    </div>

</div>

</body>
</html>