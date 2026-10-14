<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Doctor Appointments Dashboard
|--------------------------------------------------------------------------
| Expected database tables:
| appointments, users, doctors
|
| This page supports doctor/admin sessions and checks the available
| columns before running the appointment query.
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = strtolower(
    $_SESSION['user_role']
    ?? $_SESSION['role']
    ?? ''
);

if (!in_array($userRole, ['doctor', 'admin'], true)) {
    http_response_code(403);
    exit('Access denied. Please log in with an authorized doctor or admin account.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Database connection failed. Check config/includes/database.php.');
}

$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableColumns(mysqli $conn, string $table): array
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $result->free();
    }

    return $columns;
}

function firstColumn(array $available, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $available, true)) {
            return $candidate;
        }
    }
    return null;
}

$message = '';
$error = '';

if (isset($_SESSION['appointment_message'])) {
    $message = (string) $_SESSION['appointment_message'];
    unset($_SESSION['appointment_message']);
}

try {
    $appointmentColumns = tableColumns($conn, 'appointments');
    $userColumns = tableColumns($conn, 'users');
    $doctorColumns = tableColumns($conn, 'doctors');

    $appointmentIdCol = firstColumn(
        $appointmentColumns,
        ['id', 'appointment_id']
    );
    $patientIdCol = firstColumn(
        $appointmentColumns,
        ['user_id', 'patient_id']
    );
    $doctorIdCol = firstColumn(
        $appointmentColumns,
        ['doctor_id']
    );
    $dateCol = firstColumn(
        $appointmentColumns,
        ['appointment_date', 'date', 'booking_date']
    );
    $timeCol = firstColumn(
        $appointmentColumns,
        ['appointment_time', 'time', 'booking_time']
    );
    $statusCol = firstColumn(
        $appointmentColumns,
        ['status', 'appointment_status']
    );
    $reasonCol = firstColumn(
        $appointmentColumns,
        ['reason', 'symptoms', 'description']
    );

    $userIdCol = firstColumn($userColumns, ['id', 'user_id']);
    $userNameCol = firstColumn(
        $userColumns,
        ['full_name', 'name', 'username', 'user_name']
    );
    $userEmailCol = firstColumn(
        $userColumns,
        ['email', 'email_address']
    );
    $userPhoneCol = firstColumn(
        $userColumns,
        ['phone', 'phone_number', 'mobile']
    );

    $doctorTableIdCol = firstColumn($doctorColumns, ['id', 'doctor_id']);
    $doctorNameCol = firstColumn(
        $doctorColumns,
        ['name', 'full_name', 'doctor_name']
    );
    $doctorSpecialtyCol = firstColumn(
        $doctorColumns,
        ['specialization', 'speciality', 'specialty']
    );

    if (
        !$appointmentIdCol ||
        !$patientIdCol ||
        !$doctorIdCol ||
        !$dateCol ||
        !$timeCol ||
        !$statusCol ||
        !$userIdCol ||
        !$userNameCol ||
        !$doctorTableIdCol ||
        !$doctorNameCol
    ) {
        throw new RuntimeException(
            'Required database columns were not found. Check the appointments, users, and doctors tables in phpMyAdmin.'
        );
    }

    /*
     * Handle approval/rejection.
     * The action is restricted to pending appointments.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedToken = $_POST['csrf_token'] ?? '';

        if (
            empty($_SESSION['csrf_token']) ||
            !is_string($postedToken) ||
            !hash_equals($_SESSION['csrf_token'], $postedToken)
        ) {
            throw new RuntimeException(
                'Security token expired. Refresh the page and try again.'
            );
        }

        $appointmentId = filter_input(
            INPUT_POST,
            'appointment_id',
            FILTER_VALIDATE_INT
        );

        $action = $_POST['action'] ?? '';

        $allowedActions = [
            'approve' => 'approved',
            'reject' => 'rejected'
        ];

        if (!$appointmentId || !isset($allowedActions[$action])) {
            throw new RuntimeException('Invalid appointment action.');
        }

        $newStatus = $allowedActions[$action];

        // For doctor accounts, only their own appointments may be changed.
        $where = "`$appointmentIdCol` = ? AND LOWER(`$statusCol`) = 'pending'";
        $types = 'i';
        $params = [$appointmentId];

        if ($userRole === 'doctor') {
            $doctorUserIdCol = firstColumn(
                $doctorColumns,
                ['user_id', 'users_id']
            );

            if ($doctorUserIdCol) {
                $where .= " AND `$doctorIdCol` IN (
                    SELECT `$doctorTableIdCol`
                    FROM `doctors`
                    WHERE `$doctorUserIdCol` = ?
                )";
                $types .= 'i';
                $params[] = $userId;
            } else {
                // Some projects use the logged-in ID directly as doctor_id.
                $where .= " AND `$doctorIdCol` = ?";
                $types .= 'i';
                $params[] = $userId;
            }
        }

        $sql = "UPDATE `appointments`
                SET `$statusCol` = ?
                WHERE $where";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException('Could not prepare appointment update.');
        }

        $types = 's' . $types;
        $params = array_merge([$newStatus], $params);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $_SESSION['appointment_message'] =
                'Appointment ' . $newStatus . ' successfully.';
        } else {
            $_SESSION['appointment_message'] =
                'No appointment was updated. It may no longer be pending, or it may not belong to your account.';
        }

        $stmt->close();

        header('Location: appointments.php');
        exit;
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /*
     * Fetch appointments with patient and doctor details.
     */
    $select = [
        "a.`$appointmentIdCol` AS appointment_id",
        "a.`$patientIdCol` AS patient_id",
        "a.`$doctorIdCol` AS doctor_id",
        "a.`$dateCol` AS appointment_date",
        "a.`$timeCol` AS appointment_time",
        "a.`$statusCol` AS appointment_status",
        "u.`$userNameCol` AS patient_name",
        "d.`$doctorNameCol` AS doctor_name"
    ];

    $select[] = $userEmailCol
        ? "u.`$userEmailCol` AS patient_email"
        : "'' AS patient_email";

    $select[] = $userPhoneCol
        ? "u.`$userPhoneCol` AS patient_phone"
        : "'' AS patient_phone";

    $select[] = $reasonCol
        ? "a.`$reasonCol` AS appointment_reason"
        : "'' AS appointment_reason";

    $select[] = $doctorSpecialtyCol
        ? "d.`$doctorSpecialtyCol` AS specialization"
        : "'' AS specialization";

    $sql = "SELECT " . implode(', ', $select) . "
            FROM `appointments` a
            LEFT JOIN `users` u
                ON u.`$userIdCol` = a.`$patientIdCol`
            LEFT JOIN `doctors` d
                ON d.`$doctorTableIdCol` = a.`$doctorIdCol`";

    $params = [];
    $types = '';

    if ($userRole === 'doctor') {
        $doctorUserIdCol = firstColumn(
            $doctorColumns,
            ['user_id', 'users_id']
        );

        if ($doctorUserIdCol) {
            $sql .= " WHERE a.`$doctorIdCol` IN (
                SELECT `$doctorTableIdCol`
                FROM `doctors`
                WHERE `$doctorUserIdCol` = ?
            )";
            $types = 'i';
            $params[] = $userId;
        } else {
            $sql .= " WHERE a.`$doctorIdCol` = ?";
            $types = 'i';
            $params[] = $userId;
        }
    }

    $sql .= " ORDER BY a.`$dateCol` DESC, a.`$timeCol` DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Could not prepare appointment list.');
    }

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Throwable $exception) {
    $appointments = [];
    $error = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments | Hospital Appointment System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #eef5f7;
            color: #243746;
        }

        .navbar {
            background: #087f8c;
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .navbar h2 {
            margin: 0;
            font-size: 22px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 16px;
        }

        .container {
            width: 94%;
            max-width: 1250px;
            margin: 32px auto;
        }

        .page-heading {
            margin-bottom: 24px;
        }

        .page-heading h1 {
            color: #087f8c;
            margin-bottom: 8px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #183b4b12;
        }

        .summary-card p {
            margin: 0 0 10px;
            color: #657786;
        }

        .summary-card strong {
            font-size: 28px;
            color: #087f8c;
        }

        .table-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #183b4b12;
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 950px;
        }

        th, td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #e6ecef;
            vertical-align: top;
        }

        th {
            background: #e7f4f5;
            color: #075d67;
        }

        tr:hover td {
            background: #fafdfd;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .pending {
            color: #805600;
            background: #fff0bf;
        }

        .approved, .confirmed, .completed {
            color: #17633a;
            background: #d8f4e3;
        }

        .rejected, .cancelled, .canceled {
            color: #9b2634;
            background: #ffe0e3;
        }

        .action-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 6px;
            padding: 9px 12px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .approve {
            background: #16834a;
        }

        .reject {
            background: #c43d4b;
        }

        .btn:hover {
            filter: brightness(.92);
        }

        .message, .error, .empty {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .message {
            background: #d8f4e3;
            color: #17633a;
        }

        .error {
            background: #ffe0e3;
            color: #9b2634;
            overflow-wrap: anywhere;
        }

        .empty {
            text-align: center;
            background: #f7fafb;
            color: #5c6b73;
        }

        @media (max-width: 600px) {
            .navbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .navbar a {
                margin: 0 12px 0 0;
            }

            .container {
                width: 96%;
                margin: 20px auto;
            }

            .table-card {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <h2>Hospital Appointment System</h2>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="appointments.php">Appointments</a>
        <a href="../../logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <div class="page-heading">
        <h1>Manage Appointments</h1>
        <p>View patient bookings and manage pending appointment requests.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error">
            <strong>Unable to load appointments:</strong>
            <?= e($error) ?>
            <p>Check your database connection and table/column names.</p>
        </div>
    <?php endif; ?>

    <?php
    $total = count($appointments ?? []);
    $pending = 0;
    $approved = 0;
    $cancelled = 0;

    foreach (($appointments ?? []) as $appointment) {
        $status = strtolower(trim($appointment['appointment_status'] ?? ''));

        if ($status === 'pending') {
            $pending++;
        } elseif (in_array($status, ['approved', 'confirmed'], true)) {
            $approved++;
        } elseif (in_array($status, ['cancelled', 'canceled'], true)) {
            $cancelled++;
        }
    }
    ?>

    <section class="summary">
        <div class="summary-card">
            <p>Total Appointments</p>
            <strong><?= $total ?></strong>
        </div>
        <div class="summary-card">
            <p>Pending</p>
            <strong><?= $pending ?></strong>
        </div>
        <div class="summary-card">
            <p>Approved</p>
            <strong><?= $approved ?></strong>
        </div>
        <div class="summary-card">
            <p>Cancelled</p>
            <strong><?= $cancelled ?></strong>
        </div>
    </section>

    <section class="table-card">
        <h2>Appointment Records</h2>

        <?php if (empty($appointments)): ?>
            <div class="empty">
                No appointments found.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <?php
                    $status = strtolower(
                        trim($appointment['appointment_status'] ?? 'pending')
                    );

                    $statusClass = preg_replace('/[^a-z0-9_-]/', '', $status);
                    ?>
                    <tr>
                        <td><?= e($appointment['appointment_id']) ?></td>

                        <td>
                            <strong><?= e($appointment['patient_name']) ?></strong>
                        </td>

                        <td>
                            <?= e($appointment['patient_email']) ?>
                            <?php if (!empty($appointment['patient_phone'])): ?>
                                <br><?= e($appointment['patient_phone']) ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($appointment['doctor_name']) ?>
                            <?php if (!empty($appointment['specialization'])): ?>
                                <br><small><?= e($appointment['specialization']) ?></small>
                            <?php endif; ?>
                        </td>

                        <td><?= e($appointment['appointment_date']) ?></td>
                        <td><?= e($appointment['appointment_time']) ?></td>
                        <td><?= e($appointment['appointment_reason'] ?: 'Not provided') ?></td>

                        <td>
                            <span class="status <?= e($statusClass) ?>">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($status === 'pending'): ?>
                                <form method="POST"
                                      action="appointments.php"
                                      class="action-form"
                                      onsubmit="return confirm('Are you sure you want to change this appointment status?');">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

                                    <input type="hidden"
                                           name="appointment_id"
                                           value="<?= e($appointment['appointment_id']) ?>">

                                    <button type="submit"
                                            name="action"
                                            value="approve"
                                            class="btn approve">
                                        Approve
                                    </button>

                                    <button type="submit"
                                            name="action"
                                            value="reject"
                                            class="btn reject">
                                        Reject
                                    </button>
                                </form>
                            <?php else: ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</main>

</body>
</html>
```