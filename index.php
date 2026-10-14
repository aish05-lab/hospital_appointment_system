<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? ($_SESSION['name'] ?? 'User') : '';
$userRole = $_SESSION['role'] ?? '';

$dashboardLink = 'login.php';

if ($userRole === 'patient') {
    $dashboardLink = 'patient/doctors.php';
} elseif ($userRole === 'doctor') {
    $dashboardLink = 'doctor/dashboard.php';
} elseif ($userRole === 'admin') {
    $dashboardLink = 'admin/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CarePoint | Hospital Appointment System</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #243047;
            background: #f7faff;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: auto;
        }

        /* Navigation bar */

        .navbar {
            background: #ffffff;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.07);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-content {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logo {
            font-size: 27px;
            font-weight: bold;
            color: #087f8c;
            white-space: nowrap;
        }

        .logo span {
            color: #243b5a;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #34445e;
            font-size: 14px;
            font-weight: bold;
        }

        .nav-links a:hover {
            color: #087f8c;
        }

        .nav-links .nav-button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 7px;
            background: #087f8c;
            color: white;
        }

        .nav-links .nav-button:hover {
            background: #066570;
            color: white;
        }

        .nav-links .register-button {
            background: #e8f6f7;
            color: #087f8c;
            border: 1px solid #087f8c;
        }

        .nav-links .register-button:hover {
            background: #d6eff0;
            color: #066570;
        }

        /* Hero section */

        .hero {
            background: linear-gradient(120deg, #e7f8fa, #f1f5ff);
            padding: 80px 0;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
            gap: 45px;
        }

        .tag {
            display: inline-block;
            background: #d4f2f1;
            color: #087f8c;
            padding: 8px 14px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .hero h1 {
            font-size: clamp(35px, 5vw, 56px);
            line-height: 1.2;
            color: #1d3150;
            margin-bottom: 22px;
        }

        .hero h1 span {
            color: #087f8c;
        }

        .hero p {
            font-size: 17px;
            color: #64718a;
            margin-bottom: 28px;
            max-width: 580px;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .button {
            display: inline-block;
            padding: 13px 21px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            transition: 0.2s;
        }

        .button-primary {
            background: #087f8c;
            color: white;
        }

        .button-primary:hover {
            background: #066570;
            transform: translateY(-2px);
        }

        .button-secondary {
            background: white;
            color: #087f8c;
            border: 1px solid #087f8c;
        }

        .button-secondary:hover {
            background: #e7f8fa;
        }

        .hero-card {
            background: white;
            border-radius: 22px;
            padding: 35px;
            box-shadow: 0 15px 45px rgba(34, 66, 100, 0.10);
            text-align: center;
        }

        .medical-icon {
            font-size: 85px;
            margin-bottom: 15px;
        }

        .hero-card h2 {
            color: #1d3150;
            font-size: 25px;
            margin-bottom: 12px;
        }

        .hero-card p {
            font-size: 15px;
            margin-bottom: 0;
        }

        .quick-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 25px;
        }

        .info-box {
            background: #f0fafb;
            padding: 14px 7px;
            border-radius: 10px;
        }

        .info-box strong {
            display: block;
            color: #087f8c;
            font-size: 14px;
        }

        .info-box span {
            font-size: 12px;
            color: #64718a;
        }

        /* General sections */

        section {
            padding: 75px 0;
        }

        .section-heading {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 45px;
        }

        .section-heading h2 {
            color: #1d3150;
            font-size: 34px;
            margin-bottom: 12px;
        }

        .section-heading p {
            color: #64718a;
        }

        /* Services */

        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .service-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #e8eef5;
            box-shadow: 0 8px 25px rgba(34, 66, 100, 0.04);
            transition: 0.2s;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(34, 66, 100, 0.09);
        }

        .service-icon {
            font-size: 38px;
            margin-bottom: 15px;
        }

        .service-card h3 {
            color: #1d3150;
            margin-bottom: 12px;
        }

        .service-card p {
            color: #64718a;
            font-size: 15px;
        }

        /* How it works */

        .how-section {
            background: #edf7fa;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .step-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
        }

        .step-number {
            background: #087f8c;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 20px;
            font-weight: bold;
        }

        .step-card h3 {
            color: #1d3150;
            margin-bottom: 10px;
        }

        .step-card p {
            color: #64718a;
            font-size: 15px;
        }

        /* Call to action */

        .cta {
            padding: 60px 0;
        }

        .cta-box {
            background: #087f8c;
            color: white;
            border-radius: 20px;
            padding: 50px 25px;
            text-align: center;
        }

        .cta-box h2 {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .cta-box p {
            margin-bottom: 25px;
            color: #e3fbfc;
        }

        .cta-box .button {
            background: white;
            color: #087f8c;
        }

        .cta-box .button:hover {
            background: #e8f6f7;
        }

        /* Footer */

        footer {
            background: #172941;
            color: #d8e1ee;
            padding: 30px 0;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        footer p {
            font-size: 14px;
        }

        /* Mobile design */

        @media (max-width: 950px) {
            .nav-content {
                padding: 15px 0;
                flex-direction: column;
            }

            .hero-content {
                grid-template-columns: 1fr;
            }

            .services-grid,
            .steps-grid {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 55px 0;
            }
        }

        @media (max-width: 480px) {
            .nav-links {
                gap: 12px;
            }

            .nav-links a {
                font-size: 13px;
            }

            .hero-card {
                padding: 25px 18px;
            }

            .quick-info {
                grid-template-columns: 1fr;
            }

            .section-heading h2 {
                font-size: 28px;
            }

            .cta-box h2 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>

<!-- NAVIGATION BAR -->

<header class="navbar">
    <div class="container nav-content">

        <a href="index.php" class="logo">
            Care<span>Point</span>+
        </a>

        <nav class="nav-links">

            <a href="index.php">Home</a>

            <a href="index.php#services">Services</a>

            <a href="index.php#how-it-works">How It Works</a>

            <?php if ($isLoggedIn && $userRole === 'patient'): ?>

                <a href="patient/doctors.php">Find Doctors</a>

                <a href="patient/my_appointments.php">My Appointments</a>

            <?php endif; ?>

            <?php if ($isLoggedIn): ?>

                <a href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>"
                   class="nav-button">
                    My Dashboard
                </a>

            <?php endif; ?>

            <!-- These buttons are always visible -->

            <a href="login.php" class="nav-button">Login</a>

            <a href="register.php" class="nav-button register-button">
                Register
            </a>

        </nav>

    </div>
</header>


<!-- HERO SECTION -->

<section class="hero">
    <div class="container hero-content">

        <div>

            <span class="tag">YOUR HEALTH, OUR PRIORITY</span>

            <h1>
                Healthcare Made
                <span>Simple and Accessible</span>
            </h1>

            <p>
                Welcome to CarePoint, your hospital appointment
                management system. Discover doctors, schedule
                appointments, and manage your visits conveniently
                from one place.
            </p>

            <div class="hero-buttons">

                <?php if ($isLoggedIn && $userRole === 'patient'): ?>

                    <a href="patient/doctors.php"
                       class="button button-primary">
                        Find a Doctor
                    </a>

                    <a href="patient/my_appointments.php"
                       class="button button-secondary">
                        My Appointments
                    </a>

                <?php elseif (!$isLoggedIn): ?>

                    <a href="register.php"
                       class="button button-primary">
                        Get Started
                    </a>

                    <a href="login.php"
                       class="button button-secondary">
                        Login to Your Account
                    </a>

                <?php else: ?>

                    <a href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>"
                       class="button button-primary">
                        Open Dashboard
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <div class="hero-card">

            <div class="medical-icon">🏥</div>

            <h2>Better Care Starts Here</h2>

            <p>
                Manage your hospital appointments with a simple,
                convenient, and user-friendly system.
            </p>

            <div class="quick-info">

                <div class="info-box">
                    <strong>Easy</strong>
                    <span>Booking</span>
                </div>

                <div class="info-box">
                    <strong>Simple</strong>
                    <span>Management</span>
                </div>

                <div class="info-box">
                    <strong>Convenient</strong>
                    <span>Access</span>
                </div>

            </div>

        </div>

    </div>
</section>


<!-- SERVICES SECTION -->

<section id="services">

    <div class="container">

        <div class="section-heading">

            <h2>Our Services</h2>

            <p>
                Everything you need to manage your hospital
                appointments conveniently.
            </p>

        </div>

        <div class="services-grid">

            <div class="service-card">

                <div class="service-icon">👨‍⚕️</div>

                <h3>Find Doctors</h3>

                <p>
                    Browse available doctors and view their
                    specializations and consultation information.
                </p>

            </div>

            <div class="service-card">

                <div class="service-icon">📅</div>

                <h3>Book Appointments</h3>

                <p>
                    Select a doctor, choose a suitable date and
                    time, and submit your appointment request.
                </p>

            </div>

            <div class="service-card">

                <div class="service-icon">📋</div>

                <h3>Manage Appointments</h3>

                <p>
                    View your appointment history and cancel
                    eligible appointments when necessary.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- HOW IT WORKS SECTION -->

<section id="how-it-works" class="how-section">

    <div class="container">

        <div class="section-heading">

            <h2>How It Works</h2>

            <p>
                Get started in three simple steps.
            </p>

        </div>

        <div class="steps-grid">

            <div class="step-card">

                <div class="step-number">1</div>

                <h3>Create an Account</h3>

                <p>
                    Register an account to access the
                    patient appointment features.
                </p>

            </div>

            <div class="step-card">

                <div class="step-number">2</div>

                <h3>Choose a Doctor</h3>

                <p>
                    Explore available doctors and choose
                    the doctor you need.
                </p>

            </div>

            <div class="step-card">

                <div class="step-number">3</div>

                <h3>Book Your Appointment</h3>

                <p>
                    Select a date and time, submit your request,
                    and check its status later.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- CALL TO ACTION SECTION -->

<section class="cta">

    <div class="container">

        <div class="cta-box">

            <h2>
                Take the First Step Towards Better Healthcare
            </h2>

            <p>
                Create your account and explore the available doctors.
            </p>

            <?php if ($isLoggedIn && $userRole === 'patient'): ?>

                <a href="patient/doctors.php" class="button">
                    Explore Doctors
                </a>

            <?php elseif (!$isLoggedIn): ?>

                <a href="register.php" class="button">
                    Register Now
                </a>

            <?php else: ?>

                <a href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>"
                   class="button">
                    Open Dashboard
                </a>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- FOOTER -->

<footer>

    <div class="container footer-content">

        <p>
            &copy; <?php echo date('Y'); ?> CarePoint Hospital
            Appointment System.
        </p>

        <p>
            Designed for convenient appointment management.
        </p>

    </div>

</footer>

</body>
</html>