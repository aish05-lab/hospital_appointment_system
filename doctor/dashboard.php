<?php
// Display PHP errors during development
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();

require_once __DIR__ . '/../config/database.php';

// Allow only logged-in doctors
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {
    header('Location: ../login.php');
    exit;
}

$doctorUserId = (int) $_SESSION['user_id'];
$doctorName = $_SESSION['name'] ?? 'Doctor';

$doctor = null;
$appointments = [];
$errorMessage = '';

function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

try {
    // Get the doctor profile connected to the logged-in account
    $stmt = $conn->prepare(
        "SELECT id, name, specialization, qualification
         FROM doctors
         WHERE user_id = ?
         LIMIT 1"
    );

    $stmt->bind_param('i', $doctorUserId);
    $stmt->execute();

    $doctorResult = $stmt->get_result();
    $doctor = $doctorResult->fetch_assoc();

    $stmt->close();

    if ($doctor) {
        $doctorId = (int) $doctor['id'];

        // Fetch appointments belonging to this doctor
        $stmt = $conn->prepare(
            "SELECT
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.status,
                u.name AS patient_name,
                u.email AS patient_email
             FROM appointments AS a
             INNER JOIN users AS u
                ON a.patient_id = u.id
             WHERE a.doctor_id = ?
             ORDER BY
                a.appointment_date DESC,
                a.appointment_time DESC"
        );

        $stmt->bind_param('i', $doctorId);
        $stmt->execute();

        $appointmentResult = $stmt->get_result();

        while ($row = $appointmentResult->fetch_assoc()) {
            $appointments[] = $row;
        }

        $stmt->close();
    }
} catch (Throwable $exception) {
    // Keep technical details out of the page shown to users
    error_log('Doctor dashboard error: ' . $exception->getMessage());

    $errorMessage =
        'Unable to load the dashboard. Please check your database tables and columns.';
}

// Calculate appointment totals
$totalAppointments = count($appointments);
$pendingCount = 0;
$confirmedCount = 0;
$cancelledCount = 0;

foreach ($appointments as $appointment) {
    $status = strtolower($appointment['status'] ?? '');

    if ($status === 'pending') {
        $pendingCount++;
    } elseif ($status === 'confirmed') {
        $confirmedCount++;
    } elseif ($status === 'cancelled' || $status === 'rejected') {
        $cancelledCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Doctor Dashboard | CarePoint</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f7fc;
            color: #243247;
        }

        header {
            background: #087f8c;
            color: white;
            padding: 20px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
        }

        header nav {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        header a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        header a:hover {
            text-decoration: underline;
        }

        .container {
            width: 92%;
            max-width: 1250px;
            margin: 35px auto;
        }

        .welcome {
            background: white;
            padding: 28px;
            border-radius: 14px;
            margin-bottom: 25px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
        }

        .welcome h2 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #087f8c;
            font-size: 28px;
        }

        .welcome p {
            color: #657287;
            line-height: 1.6;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            padding: 23px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        }

        .stat-card p {
            margin: 0 0 12px;
            color: #657287;
            font-size: 14px;
        }

        .stat-card h3 {
            margin: 0;
            font-size: 30px;
            color: #087f8c;
        }

        .profile,
        .appointments-section {
            background: white;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 25px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        }

        .profile h2,
        .appointments-section h2 {
            margin-top: 0;
            color: #087f8c;
        }

        .profile p {
            line-height: 1.8;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 800px;
        }

        th,
        td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e8edf3;
            vertical-align: top;
        }

        th {
            background: #e8f6f7;
            color: #075e67;
            font-size: 14px;
        }

        td {
            font-size: 14px;
            line-height: 1.5;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff0c2;
            color: #805b00;
        }

        .status-confirmed {
            background: #d8f5df;
            color: #176b32;
        }

        .status-cancelled,
        .status-rejected {
            background: #ffe0e0;
            color: #a32121;
        }

        .status-completed {
            background: #dceaff;
            color: #184e9e;
        }

        .empty {
            background: #f5f9fc;
            padding: 35px 20px;
            text-align: center;
            border-radius: 10px;
            color: #657287;
        }

        .empty h3 {
            color: #087f8c;
        }

        .error-box {
            background: #ffe3e3;
            color: #a32121;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .notice {
            background: #fff5d6;
            color: #755400;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.6;
        }

        footer {
            background: #087f8c;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 40px;
        }

        @media (max-width: 900px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 550px) {
            .cards {
                grid-template-columns: 1fr;
            }

            .container {
                width: 94%;
                margin-top: 20px;
            }

            .welcome,
            .profile,
            .appointments-section {
                padding: 18px;
            }

            header h1 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>CarePoint | Doctor Dashboard</h1>

    <nav>
        <a href="../index.php">Home</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <section class="welcome">
        <h2>
            Welcome, Dr. <?= e($doctorName) ?>!
        </h2>

        <p>
            Manage your appointments and review the details
            of patients who have booked consultations with you.
        </p>
    </section>

    <?php if ($errorMessage !== ''): ?>

        <div class="error-box">
            <?= e($errorMessage) ?>

            <br><br>

            Check the PHP error log at:

            <code>C:\xampp_new\apache\logs\error.log</code>
        </div>

    <?php elseif (!$doctor): ?>

        <div class="error-box">
            <strong>Doctor profile not found.</strong>

            <p>
                Your account is logged in, but no doctor profile
                is connected to this account.
            </p>

            <p>
                The doctors table must contain a record whose
                user_id matches your account's users.id.
            </p>
        </div>

    <?php else: ?>

        <section class="profile">
            <h2>Doctor Profile</h2>

            <p>
                <strong>Name:</strong>
                <?= e($doctor['name']) ?>
            </p>

            <p>
                <strong>Specialization:</strong>
                <?= e($doctor['specialization']) ?>
            </p>

            <p>
                <strong>Qualification:</strong>
                <?= e($doctor['qualification'] ?? 'Not specified') ?>
            </p>
        </section>

        <section class="cards">

            <div class="stat-card">
                <p>Total Appointments</p>
                <h3><?= $totalAppointments ?></h3>
            </div>

            <div class="stat-card">
                <p>Pending</p>
                <h3><?= $pendingCount ?></h3>
            </div>

            <div class="stat-card">
                <p>Confirmed</p>
                <h3><?= $confirmedCount ?></h3>
            </div>

            <div class="stat-card">
                <p>Cancelled / Rejected</p>
                <h3><?= $cancelledCount ?></h3>
            </div>

        </section>

        <section class="appointments-section">

            <h2>Patient Appointments</h2>

            <?php if (empty($appointments)): ?>

                <div class="empty">
                    <h3>No Appointments Yet</h3>

                    <p>
                        Appointments booked with your doctor profile
                        will appear here.
                    </p>
                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Email</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($appointments as $appointment): ?>

                            <?php
                            $status = strtolower(
                                $appointment['status'] ?? 'pending'
                            );

                            $allowedStatuses = [
                                'pending',
                                'confirmed',
                                'cancelled',
                                'rejected',
                                'completed'
                            ];

                            if (!in_array($status, $allowedStatuses, true)) {
                                $status = 'pending';
                            }
                            ?>

                            <tr>
                                <td>
                                    <?= e($appointment['patient_name']) ?>
                                </td>

                                <td>
                                    <?= e($appointment['patient_email']) ?>
                                </td>

                                <td>
                                    <?= e($appointment['appointment_date']) ?>
                                </td>

                                <td>
                                    <?= e($appointment['appointment_time']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $appointment['reason'] ?: 'Not provided'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status status-<?= e($status) ?>">
                                        <?= e($status) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<footer>
    <p>&copy; <?= date('Y') ?> CarePoint Hospital Appointment System</p>
</footer>

</body>
</html>