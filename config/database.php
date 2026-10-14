<?php
// Database connection for Hospital Appointment System

mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$username = "root";
$password = "";
$database = "hospital_appointment";

// Create database connection
$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);

    die(
        "Unable to connect to the database. " .
        "Please check your XAMPP MySQL service and database settings."
    );
}

// Set character encoding
$conn->set_charset("utf8mb4");
?>
