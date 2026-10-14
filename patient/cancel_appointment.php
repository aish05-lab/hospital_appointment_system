<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Redirect to login if the patient is not logged in
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'patient'
) {
    header("Location: ../login.php");
    exit;
}

// Accept cancellation requests only through POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_appointments.php?error=invalid_request");
    exit;
}

// Check whether an appointment ID was submitted
if (
    !isset($_POST['appointment_id']) ||
    !filter_var($_POST['appointment_id'], FILTER_VALIDATE_INT) ||
    (int) $_POST['appointment_id'] <= 0
) {
    header("Location: my_appointments.php?error=invalid_appointment");
    exit;
}

$appointment_id = (int) $_POST['appointment_id'];
$patient_id = (int) $_SESSION['user_id'];

try {
    // Find the appointment belonging to this patient
    $sql = "SELECT id, appointment_date, status
            FROM appointments
            WHERE id = ? AND patient_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $appointment_id, $patient_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    // Make sure the appointment exists and belongs to this patient
    if (!$appointment) {
        header("Location: my_appointments.php?error=appointment_not_found");
        exit;
    }

    // Only pending or confirmed appointments can be cancelled
    if (
        !in_array(
            strtolower($appointment['status']),
            ['pending', 'confirmed'],
            true
        )
    ) {
        header("Location: my_appointments.php?error=not_cancellable");
        exit;
    }

    // Prevent cancellation of appointments scheduled in the past
    $appointment_date = $appointment['appointment_date'];

    if ($appointment_date < date('Y-m-d')) {
        header("Location: my_appointments.php?error=past_appointment");
        exit;
    }

    // Update the appointment status
    $update_sql = "UPDATE appointments
                   SET status = 'cancelled'
                   WHERE id = ?
                     AND patient_id = ?
                     AND status IN ('pending', 'confirmed')";

    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param(
        "ii",
        $appointment_id,
        $patient_id
    );

    $update_stmt->execute();

    if ($update_stmt->affected_rows === 1) {
        $update_stmt->close();

        header("Location: my_appointments.php?success=cancelled");
        exit;
    }

    $update_stmt->close();

    header("Location: my_appointments.php?error=cancel_failed");
    exit;

} catch (mysqli_sql_exception $e) {
    // Record the error in the PHP error log without showing database
    // details to the patient.
    error_log("Appointment cancellation error: " . $e->getMessage());

    header("Location: my_appointments.php?error=database_error");
    exit;
}
?>