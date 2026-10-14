<?php
session_start();

require_once __DIR__ . '/../config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Only logged-in doctors can update appointment statuses
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {
    header('Location: ../login.php');
    exit;
}

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$appointmentId = filter_input(
    INPUT_POST,
    'appointment_id',
    FILTER_VALIDATE_INT
);

$newStatus = $_POST['status'] ?? '';

if (
    !$appointmentId ||
    !in_array($newStatus, ['confirmed', 'rejected'], true)
) {
    header('Location: dashboard.php?error=invalid_request');
    exit;
}

$doctorUserId = (int) $_SESSION['user_id'];

try {
    // Find the doctor profile belonging to the logged-in user
    $stmt = $conn->prepare(
        "SELECT id
         FROM doctors
         WHERE user_id = ?
         LIMIT 1"
    );

    $stmt->bind_param('i', $doctorUserId);
    $stmt->execute();

    $doctorResult = $stmt->get_result();
    $doctor = $doctorResult->fetch_assoc();
    $stmt->close();

    if (!$doctor) {
        header('Location: dashboard.php?error=doctor_not_found');
        exit;
    }

    $doctorId = (int) $doctor['id'];

    // Update only an appointment assigned to this doctor
    // and currently waiting for a decision.
    $stmt = $conn->prepare(
        "UPDATE appointments
         SET status = ?
         WHERE id = ?
           AND doctor_id = ?
           AND status = 'pending'
           AND appointment_date >= CURDATE()"
    );

    $stmt->bind_param(
        'sii',
        $newStatus,
        $appointmentId,
        $doctorId
    );

    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();

    if ($updated === 1) {
        header('Location: dashboard.php?success=' . $newStatus);
        exit;
    }

    header('Location: dashboard.php?error=not_updated');
    exit;

} catch (Throwable $e) {
    error_log(
        'Appointment status update error: ' . $e->getMessage()
    );

    header('Location: dashboard.php?error=database_error');
    exit;
}