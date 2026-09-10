<?php
declare(strict_types=1);
require __DIR__ . '/config/app.php';

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: ?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');
        if (!$email || $password === '') {
            $error = 'Enter a valid email and password.';
        } else {
            try {
                $statement = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1');
                $statement->execute([$email]);
                $user = $statement->fetch();
                if ($user && $user['status'] === 'ACTIVE' && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['full_name'], 'role' => $user['role_name']];
                    header('Location: ?page=dashboard');
                    exit;
                }
            } catch (PDOException $exception) {
                $error = 'Database unavailable. Import database/schema.sql and check config/app.php.';
            }
            $error ??= 'Those login details were not recognised.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !$email || strlen($phone) < 9 || strlen($password) < 8) {
            $error = 'Complete every field. Passwords must be at least 8 characters.';
        } else {
            try {
                $role = db()->query("SELECT id FROM roles WHERE name = 'PASSENGER'")->fetchColumn();
                $statement = db()->prepare('INSERT INTO users (role_id, full_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$role, $name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
                header('Location: ?page=login&registered=1');
                exit;
            } catch (PDOException $exception) {
                $error = $exception->errorInfo[1] === 1062 ? 'That email or phone is already registered.' : 'Registration could not be completed.';
            }
        }
    }
}

$user = current_user();
if ($page === 'dashboard' && !$user) {
    $page = 'login';
}

if ($user && $page === 'dashboard') {
    $page = match (strtoupper($user['role'] ?? '')) {
        'ADMIN' => 'admin-dashboard',
        'DRIVER' => 'driver-dashboard',
        default => 'dashboard',
    };
}

$vehicleTypes = [];
$fareSettings = [];
if ($user && $page === 'dashboard') {
    try {
        $vehicleTypes = db()->query('SELECT id, name, description, capacity FROM vehicle_types WHERE active = 1 ORDER BY id')->fetchAll();
        $fareSettings = db()->query('SELECT vehicle_type_id, base_fare, per_km, per_minute, service_fee, minimum_fare FROM fare_settings')->fetchAll();
        $drivers = db()->query("SELECT d.id, u.full_name, u.phone, d.rating, v.model, v.registration_no FROM drivers d JOIN users u ON u.id = d.id LEFT JOIN vehicles v ON v.driver_id = d.id AND v.status = 'APPROVED' WHERE d.availability = 'AVAILABLE' AND u.status = 'ACTIVE' ORDER BY d.rating DESC, u.full_name")->fetchAll();
    } catch (PDOException $exception) {
        $dashboardError = 'Vehicle options are temporarily unavailable. Please try again shortly.';
    }
}

function page_header(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="NiaRide booking and travel management"><title>' . e($title) . ' | ' . APP_NAME . '</title><link rel="stylesheet" href="public/styles.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><style>
      .map-panel { padding: 28px; }
      .map-panel .panel-title { margin-bottom: 16px; }
      .map-panel .panel-help { margin: 7px 0 0; }
      .map-panel .panel-title .online-dot {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 10px;
        border-radius: 999px;
        background: #effaf1;
        border: 1px solid rgba(30, 155, 104, 0.18);
      }
      .map-panel .panel-title .online-dot i { font-size: 8px; }
      .map {
        position: relative;
        height: 280px;
        overflow: hidden;
        border-radius: 16px;
        border: 1px solid rgba(23, 34, 29, 0.08);
        background: linear-gradient(135deg, #edf7ee 0%, #dfece4 18%, #d4e8d9 52%, #eef7f1 100%);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.45);
      }
      .map::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image:
          linear-gradient(rgba(255, 255, 255, 0.54) 1px, transparent 1px),
          linear-gradient(90deg, rgba(255, 255, 255, 0.54) 1px, transparent 1px),
          radial-gradient(circle at 25% 18%, rgba(30, 155, 104, 0.18), transparent 28%),
          radial-gradient(circle at 75% 32%, rgba(246, 139, 81, 0.18), transparent 22%),
          linear-gradient(140deg, transparent 0 58%, rgba(25, 58, 41, 0.08) 58% 60%, transparent 60% 100%);
        background-size: 46px 46px, 46px 46px, 100% 100%, 100% 100%, 100% 100%;
        opacity: 0.9;
      }
      .map::after {
        content: "";
        position: absolute;
        inset: 0;
        background:
          radial-gradient(circle at 25% 60%, rgba(255, 255, 255, 0.22), transparent 18%),
          radial-gradient(circle at 68% 34%, rgba(255, 255, 255, 0.18), transparent 20%),
          linear-gradient(120deg, rgba(23, 34, 29, 0.04), transparent 32%, rgba(23, 34, 29, 0.06) 66%, transparent 100%);
      }
      .map .pin {
        position: absolute;
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 10px 16px rgba(20, 38, 31, 0.2);
        z-index: 1;
      }
      .map .pin::before {
        content: "";
        position: absolute;
        inset: -8px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.22);
        z-index: -1;
      }
      .map .pin i {
        font-size: 13px;
        color: #fff;
      }
      .map .pin.pickup {
        left: 22%;
        top: 58%;
        background: var(--green);
      }
      .map .pin.destination {
        right: 18%;
        top: 28%;
        background: var(--orange);
      }
      .map .pin.pickup::after,
      .map .pin.destination::after {
        content: "";
        position: absolute;
        bottom: -18px;
        left: 50%;
        transform: translateX(-50%);
        width: 2px;
        height: 18px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.8), rgba(23, 34, 29, 0.18));
      }
      .map .pin.pickup::after {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.85), rgba(30, 155, 104, 0.5));
      }
      .map .pin.destination::after {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.85), rgba(246, 139, 81, 0.5));
      }
      .panel-link.location-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }
      .panel-link.location-button:hover { color: #177c54; }
    </style>' . (GOOGLE_MAPS_API_KEY ? '<script src="https://maps.googleapis.com/maps/api/js?key=' . rawurlencode(GOOGLE_MAPS_API_KEY) . '&libraries=places&callback=initRideMap" async defer></script>' : '') . '</head><body>';
}
function page_footer(): void
{
    echo '<script src="public/app.js"></script></body></html>';
}

if ($page === 'login' || $page === 'register'):
    page_header(ucfirst($page)); ?>
    <main class="form-page"><section class="form-card">
      <a class="brand" style="color:var(--ink);margin:0 0 32px;display:block" href="?page=login">nia<span style="color:var(--green)">ride</span></a>
      <span class="eyebrow">Move freely</span><h1><?= $page === 'login' ? 'Welcome back.' : 'Start riding better.' ?></h1>
      <p style="color:var(--muted)"><?= $page === 'login' ? 'Your city, connected in a few taps.' : 'Create your passenger account in under a minute.' ?></p>
      <?php if (!empty($error)): ?><p style="color:#c54b38;font-size:13px"><?= e($error) ?></p><?php endif; ?>
      <form method="post" action="?action=<?= $page ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if ($page === 'register'): ?><label for="full_name">Full name</label><input id="full_name" name="full_name" required autocomplete="name"><label for="phone">Phone number</label><input id="phone" name="phone" required autocomplete="tel"><?php endif; ?>
        <label for="email">Email address</label><input id="email" type="email" name="email" required autocomplete="email">
        <label for="password">Password</label><input id="password" type="password" name="password" required minlength="8" autocomplete="current-password">
        <button class="primary" style="width:100%;margin-top:22px" type="submit"><?= $page === 'login' ? 'Sign in' : 'Create account' ?></button>
      </form>
      <p style="font-size:13px;color:var(--muted);margin-top:25px;text-align:center"><?= $page === 'login' ? 'New to NiaRide? <a style="color:var(--green);font-weight:600" href="?page=register">Create an account</a>' : 'Already have an account? <a style="color:var(--green);font-weight:600" href="?page=login">Sign in</a>' ?></p>
    </section></main>
    <?php page_footer(); exit; endif;

if ($page === 'admin-dashboard'):
    $user = require_auth();
    $stats = [
        'passengers' => db()->query('SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = "PASSENGER" AND u.status = "ACTIVE"')->fetchColumn(),
        'drivers' => db()->query('SELECT COUNT(*) FROM drivers d JOIN users u ON u.id = d.id WHERE u.status = "ACTIVE"')->fetchColumn(),
        'active_rides' => db()->query('SELECT COUNT(*) FROM rides WHERE status IN ("SEARCHING_DRIVER", "ACCEPTED", "DRIVER_ARRIVING", "DRIVER_ARRIVED", "IN_PROGRESS")')->fetchColumn(),
        'completed_payments' => db()->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "COMPLETED"')->fetchColumn(),
    ];
    $recentRides = db()->query('SELECT r.id, p.full_name AS passenger_name, COALESCE(d.full_name, "Unassigned") AS driver_name, r.status, r.requested_at, r.estimated_fare FROM rides r JOIN users p ON p.id = r.passenger_id LEFT JOIN drivers dr ON dr.id = r.driver_id LEFT JOIN users d ON d.id = dr.id ORDER BY r.requested_at DESC LIMIT 5')->fetchAll();
    $recentDrivers = db()->query('SELECT u.full_name, d.availability, d.rating FROM drivers d JOIN users u ON u.id = d.id ORDER BY d.rating DESC, u.full_name LIMIT 5')->fetchAll();
    page_header('Admin Dashboard'); ?>
<div class="app">
  <aside class="sidebar" data-sidebar>
    <div class="brand">nia<span>ride</span></div>
    <div class="nav-label">Main menu</div>
    <a class="nav-link active" href="?page=admin-dashboard"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
    <a class="nav-link" href="#rides"><i class="fa-solid fa-route"></i><span>Rides</span></a>
    <a class="nav-link" href="#drivers"><i class="fa-solid fa-user-tie"></i><span>Drivers</span></a>
    <a class="nav-link" href="#payments"><i class="fa-regular fa-credit-card"></i><span>Payments</span></a>
    <form method="post" action="?action=logout"><button class="nav-link" style="background:none;border:0;color:inherit;width:100%;text-align:left;font:inherit;cursor:pointer"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign out</span></button></form>
  </aside>
  <main class="main">
    <header class="topbar"><button class="icon-btn menu-btn" data-menu-toggle aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button><span class="topbar-title">Admin workspace</span><div class="topbar-actions"><button class="icon-btn" aria-label="Notifications"><i class="fa-regular fa-bell"></i></button><div class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div></div></header>
    <div class="content">
      <div class="hero">
        <div>
          <span class="eyebrow">Operations overview</span>
          <h1>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
          <p>Monitor the platform, drivers, fares, and live ride activity.</p>
        </div>
        <a class="primary" href="#rides"><i class="fa-solid fa-up-right-from-square"></i>&nbsp; View rides</a>
      </div>
      <section class="stats">
        <article class="stat"><div class="stat-head">Passengers <i class="stat-icon fa-solid fa-users"></i></div><div class="stat-value"><?= e((string) $stats['passengers']) ?></div><div class="stat-note">Active users</div></article>
        <article class="stat"><div class="stat-head">Drivers <i class="stat-icon fa-solid fa-user-tie"></i></div><div class="stat-value"><?= e((string) $stats['drivers']) ?></div><div class="stat-note">Registered drivers</div></article>
        <article class="stat"><div class="stat-head">Active rides <i class="stat-icon fa-solid fa-route"></i></div><div class="stat-value"><?= e((string) $stats['active_rides']) ?></div><div class="stat-note">In progress now</div></article>
        <article class="stat"><div class="stat-head">Revenue <i class="stat-icon fa-solid fa-wallet"></i></div><div class="stat-value">KSh <?= e(number_format((float) $stats['completed_payments'], 0)) ?></div><div class="stat-note">Completed payments</div></article>
      </section>
      <div class="dashboard-grid">
        <section class="panel booking-panel" id="rides">
          <div class="panel-title"><div><span class="step-count">01</span><h2>Recent rides</h2><p class="panel-help">Latest booking activity across the platform.</p></div></div>
          <div class="history-list">
            <?php foreach ($recentRides as $ride): ?>
              <article class="history-item">
                <div class="history-main">
                  <h3><?= e($ride['passenger_name']) ?></h3>
                  <p><?= e($ride['driver_name']) ?> · <?= e($ride['status']) ?></p>
                </div>
                <div class="history-meta">
                  <span class="rating"><i class="fa-solid fa-clock"></i> <?= e($ride['requested_at']) ?></span>
                  <span class="status online">KSh <?= e(number_format((float) $ride['estimated_fare'], 0)) ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <section class="panel map-panel" id="drivers">
          <div class="panel-title"><div><span class="step-count">02</span><h2>Driver availability</h2><p class="panel-help">Top drivers by rating and their status.</p></div><span class="online-dot"><i class="fa-solid fa-circle"></i> Live</span></div>
          <div class="history-list">
            <?php foreach ($recentDrivers as $driver): ?>
              <article class="history-item">
                <div class="history-main">
                  <h3><?= e($driver['full_name']) ?></h3>
                  <p><?= e($driver['availability']) ?></p>
                </div>
                <div class="history-meta">
                  <span class="rating"><i class="fa-solid fa-star"></i> <?= e((string) $driver['rating']) ?></span>
                  <span class="status online"><?= e($driver['availability'] === 'AVAILABLE' ? 'Available' : 'Busy') ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>
<?php page_footer(); exit;
endif;

if ($page === 'driver-dashboard'):
    $user = require_auth();
    $driverId = (int) $user['id'];
    $driverStats = [
        'assigned' => db()->query('SELECT COUNT(*) FROM rides WHERE driver_id = ' . $driverId)->fetchColumn(),
        'active' => db()->query('SELECT COUNT(*) FROM rides WHERE driver_id = ' . $driverId . ' AND status IN ("ACCEPTED", "DRIVER_ARRIVING", "DRIVER_ARRIVED", "IN_PROGRESS")')->fetchColumn(),
        'today_completed' => db()->query('SELECT COUNT(*) FROM rides WHERE driver_id = ' . $driverId . ' AND status = "COMPLETED" AND DATE(completed_at) = CURDATE()')->fetchColumn(),
        'today_earnings' => db()->query('SELECT COALESCE(SUM(p.amount), 0) FROM rides r JOIN payments p ON p.ride_id = r.id WHERE r.driver_id = ' . $driverId . ' AND p.status = "COMPLETED" AND DATE(p.created_at) = CURDATE()')->fetchColumn(),
    ];
    $driverRides = db()->query('SELECT r.id, p.full_name AS passenger_name, r.status, r.pickup_label, r.destination_label, r.estimated_fare FROM rides r JOIN users p ON p.id = r.passenger_id WHERE r.driver_id = ' . $driverId . ' ORDER BY r.requested_at DESC LIMIT 5')->fetchAll();
    page_header('Driver Dashboard'); ?>
<div class="app">
  <aside class="sidebar" data-sidebar>
    <div class="brand">nia<span>ride</span></div>
    <div class="nav-label">Main menu</div>
    <a class="nav-link active" href="?page=driver-dashboard"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
    <a class="nav-link" href="#rides"><i class="fa-solid fa-route"></i><span>Trips</span></a>
    <a class="nav-link" href="#earnings"><i class="fa-solid fa-wallet"></i><span>Earnings</span></a>
    <form method="post" action="?action=logout"><button class="nav-link" style="background:none;border:0;color:inherit;width:100%;text-align:left;font:inherit;cursor:pointer"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign out</span></button></form>
  </aside>
  <main class="main">
    <header class="topbar"><button class="icon-btn menu-btn" data-menu-toggle aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button><span class="topbar-title">Driver workspace</span><div class="topbar-actions"><button class="icon-btn" aria-label="Notifications"><i class="fa-regular fa-bell"></i></button><div class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div></div></header>
    <div class="content">
      <div class="hero">
        <div>
          <span class="eyebrow">Driver overview</span>
          <h1>Good day, <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
          <p>Your trips, status, and earnings are all in one place.</p>
        </div>
        <a class="primary" href="#rides"><i class="fa-solid fa-car-side"></i>&nbsp; Manage trips</a>
      </div>
      <section class="stats">
        <article class="stat"><div class="stat-head">Assigned rides <i class="stat-icon fa-solid fa-route"></i></div><div class="stat-value"><?= e((string) $driverStats['assigned']) ?></div><div class="stat-note">Total trips</div></article>
        <article class="stat"><div class="stat-head">Active trips <i class="stat-icon fa-solid fa-location-dot"></i></div><div class="stat-value"><?= e((string) $driverStats['active']) ?></div><div class="stat-note">In progress</div></article>
        <article class="stat"><div class="stat-head">Trips today <i class="stat-icon fa-solid fa-calendar-check"></i></div><div class="stat-value"><?= e((string) $driverStats['today_completed']) ?></div><div class="stat-note">Completed today</div></article>
        <article class="stat"><div class="stat-head">Earnings today <i class="stat-icon fa-solid fa-wallet"></i></div><div class="stat-value">KSh <?= e(number_format((float) $driverStats['today_earnings'], 0)) ?></div><div class="stat-note">Completed payments</div></article>
      </section>
      <div class="dashboard-grid">
        <section class="panel booking-panel" id="rides">
          <div class="panel-title"><div><span class="step-count">01</span><h2>Recent rides</h2><p class="panel-help">Trips assigned to you.</p></div></div>
          <div class="history-list">
            <?php foreach ($driverRides as $ride): ?>
              <article class="history-item">
                <div class="history-main">
                  <h3><?= e($ride['passenger_name']) ?></h3>
                  <p><?= e($ride['pickup_label']) ?> → <?= e($ride['destination_label']) ?></p>
                </div>
                <div class="history-meta">
                  <span class="rating"><i class="fa-solid fa-flag"></i> <?= e($ride['status']) ?></span>
                  <span class="status online">KSh <?= e(number_format((float) $ride['estimated_fare'], 0)) ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <section class="panel map-panel" id="earnings">
          <div class="panel-title"><div><span class="step-count">02</span><h2>Driver status</h2><p class="panel-help">Your current availability.</p></div><span class="online-dot"><i class="fa-solid fa-circle"></i> Online</span></div>
          <div class="history-list">
            <article class="history-item">
              <div class="history-main">
                <h3>Availability</h3>
                <p>Ready to accept nearby rides.</p>
              </div>
              <div class="history-meta">
                <span class="status online">Available</span>
              </div>
            </article>
            <article class="history-item">
              <div class="history-main">
                <h3>Current rating</h3>
                <p>Based on recent customer feedback.</p>
              </div>
              <div class="history-meta">
                <span class="rating"><i class="fa-solid fa-star"></i> 4.9</span>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>
<?php page_footer(); exit;
endif;

page_header('Passenger Dashboard'); ?>
<div class="app">
<aside class="sidebar" data-sidebar><div class="brand">nia<span>ride</span></div><div class="nav-label">Main menu</div><a class="nav-link active" href="?page=dashboard"><i class="fa-solid fa-house"></i><span>Home</span></a><a class="nav-link" href="#request"><i class="fa-solid fa-location-arrow"></i><span>Book a ride</span></a><a class="nav-link" href="#history"><i class="fa-solid fa-clock-rotate-left"></i><span>My rides</span></a><a class="nav-link" href="#payments"><i class="fa-regular fa-credit-card"></i><span>Payments</span></a><div class="nav-label">You</div><a class="nav-link" href="#profile"><i class="fa-regular fa-user"></i><span>Profile</span></a><a class="nav-link" href="#help"><i class="fa-regular fa-circle-question"></i><span>Help centre</span></a><form method="post" action="?action=logout"><button class="nav-link" style="background:none;border:0;color:inherit;width:100%;text-align:left;font:inherit;cursor:pointer"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign out</span></button></form></aside>
<main class="main"><header class="topbar"><button class="icon-btn menu-btn" data-menu-toggle aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button><span class="topbar-title">Passenger workspace</span><div class="topbar-actions"><button class="icon-btn" aria-label="Notifications"><i class="fa-regular fa-bell"></i></button><div class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div></div></header>
<div class="content"><div class="hero"><div><span class="eyebrow">Thursday, 20 August 2026</span><h1>Good morning, <?= e(explode(' ', $user['name'])[0]) ?>.</h1><p>Where would you like to go today?</p></div><a class="primary" href="#request"><i class="fa-solid fa-plus"></i>&nbsp; Request a ride</a></div>
<section class="stats"><article class="stat"><div class="stat-head">Rides this month <i class="stat-icon fa-solid fa-route"></i></div><div class="stat-value">12</div><div class="stat-note">+3 from last month</div></article><article class="stat"><div class="stat-head">Total distance <i class="stat-icon fa-solid fa-road"></i></div><div class="stat-value">86.4 km</div><div class="stat-note">Across 12 rides</div></article><article class="stat"><div class="stat-head">Saved this month <i class="stat-icon fa-solid fa-wallet"></i></div><div class="stat-value">KSh 1,840</div><div class="stat-note">With NiaRide rewards</div></article><article class="stat"><div class="stat-head">Ride rating <i class="stat-icon fa-solid fa-star"></i></div><div class="stat-value">4.9 <small style="font-size:14px;color:var(--orange)">★</small></div><div class="stat-note">Your passenger score</div></article></section>
<div class="dashboard-grid"><section class="panel booking-panel" id="request"><div class="panel-title"><div><span class="step-count">01</span><h2>Book a ride</h2><p class="panel-help">Tell us where you are going.</p></div><button class="panel-link location-button" type="button"><i class="fa-solid fa-crosshairs"></i> Use my location</button></div><?php if (!empty($dashboardError)): ?><p class="form-message error-message"><?= e($dashboardError) ?></p><?php endif; ?><form id="ride-request-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="booking-fields"><label class="location-field"><span class="field-icon pickup-icon"><i class="fa-solid fa-location-dot"></i></span><span><small>Pick-up location</small><input name="pickup_label" required placeholder="Where should we pick you up?" aria-label="Pick-up location"></span></label><div class="route-line"></div><label class="location-field"><span class="field-icon destination-icon"><i class="fa-solid fa-flag-checkered"></i></span><span><small>Destination</small><input name="destination_label" required placeholder="Where are you going?" aria-label="Destination"></span></label></div><div class="vehicle-heading"><span class="step-count">02</span><h3>Choose your ride</h3></div><label class="select-field" for="vehicle_type_id"><i class="fa-solid fa-car-side"></i><span><small>Vehicle type</small><select id="vehicle_type_id" name="vehicle_type_id" required><option value="">Select a vehicle type</option><?php foreach ($vehicleTypes as $vehicle): ?><option value="<?= e((string) $vehicle['id']) ?>"><?= e($vehicle['name']) ?> · <?= e($vehicle['description']) ?> (up to <?= e((string) $vehicle['capacity']) ?>)</option><?php endforeach; ?></select></span><i class="fa-solid fa-chevron-down"></i></label><div class="estimate-box"><div><span>Estimated fare</span><strong id="fare-total">KSh 0</strong></div><small id="fare-breakdown">Enter your trip details to see an estimate.</small></div><div class="trip-inputs"><label>Distance (km)<input type="number" name="distance_km" id="distance_km" min="0.1" step="0.1" placeholder="e.g. 8" required></label><label>Time (minutes)<input type="number" name="duration_min" id="duration_min" min="1" step="1" placeholder="e.g. 25" required></label></div><button class="primary booking-submit" type="submit"><i class="fa-solid fa-magnifying-glass"></i> See available rides</button><p class="form-message" id="ride-message" role="status"></p></form></section><section class="panel map-panel"><div class="panel-title"><div><span class="step-count">03</span><h2>Nearby drivers</h2><p class="panel-help">Drivers around your area.</p></div><span class="online-dot"><i class="fa-solid fa-circle"></i> Live</span></div><div class="map"><div class="pin pickup"><i class="fa-solid fa-location-dot"></i></div><div class="pin destination"><i class="fa-solid fa-flag-checkered"></i></div><div class="driver-pin driver-one"><i class="fa-solid fa-car"></i></div><div class="driver-pin driver-two"><i class="fa-solid fa-car"></i></div></div><div class="map-note"><i class="fa-solid fa-shield-heart"></i><span><strong>Your trip is protected</strong><small>Every NiaRide driver is verified.</small></span></div></section><section class="panel recent-panel" id="history"><div class="panel-title"><h2><i class="fa-solid fa-clock-rotate-left section-icon"></i> Recent rides</h2><a class="panel-link" href="#">View all</a></div><div class="ride-row"><div class="ride-car"><i class="fa-solid fa-car"></i></div><div class="ride-info"><strong>Westlands → Kilimani</strong><span>Today, 09:42 · Economy</span></div><span class="status">Completed</span><strong class="ride-fare">KSh 420</strong></div><div class="ride-row"><div class="ride-car"><i class="fa-solid fa-motorcycle"></i></div><div class="ride-info"><strong>CBD → Parklands</strong><span>18 Aug, 16:18 · Motorcycle</span></div><span class="status">Completed</span><strong class="ride-fare">KSh 210</strong></div><div class="ride-row"><div class="ride-car"><i class="fa-solid fa-car"></i></div><div class="ride-info"><strong>Karen → Lavington</strong><span>15 Aug, 11:05 · Comfort</span></div><span class="status">Completed</span><strong class="ride-fare">KSh 680</strong></div></section></div></div></main></div><?php page_footer(); ?>
