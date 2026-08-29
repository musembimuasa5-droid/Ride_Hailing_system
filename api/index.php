<?php
declare(strict_types=1);
require __DIR__ . '/../config/app.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['resource'] ?? '') === 'fare-settings') {
    try {
        json_response(true, 'Fare settings loaded.', ['settings' => db()->query('SELECT vehicle_type_id, base_fare, per_km, per_minute, service_fee, minimum_fare FROM fare_settings')->fetchAll()]);
    } catch (Throwable $exception) {
        json_response(false, 'Fare settings are unavailable.', [], 500);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['resource'] ?? '') === 'drivers') {
    $user = current_user();
    if (!$user) {
        json_response(false, 'Authentication required.', [], 401);
    }
    try {
        $lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $distanceSql = ($lat !== false && $lng !== false) ? ', (6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(d.current_lat)) * COS(RADIANS(d.current_lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(d.current_lat)))) AS distance_km' : ', NULL AS distance_km';
        $orderSql = ($lat !== false && $lng !== false) ? ' ORDER BY distance_km ASC, d.rating DESC' : ' ORDER BY d.rating DESC, u.full_name';
        $query = db()->prepare("SELECT d.id, u.full_name, u.phone, d.rating, d.current_lat, d.current_lng, v.model, v.registration_no $distanceSql FROM drivers d JOIN users u ON u.id = d.id LEFT JOIN vehicles v ON v.driver_id = d.id AND v.status = 'APPROVED' WHERE d.availability = 'AVAILABLE' AND u.status = 'ACTIVE' $orderSql LIMIT 15");
        $query->execute(($lat !== false && $lng !== false) ? [$lat, $lng, $lat] : []);
        $drivers = $query->fetchAll();
        json_response(true, 'Drivers loaded.', ['drivers' => $drivers]);
    } catch (Throwable $exception) {
        json_response(false, 'Drivers are unavailable.', [], 500);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['resource'] ?? '') === 'ride-status') {
    $user = current_user();
    $rideId = filter_var($_GET['ride_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$user || !$rideId) {
        json_response(false, 'Authentication and ride ID are required.', [], 401);
    }
    $query = db()->prepare('SELECT r.id, r.status, r.scheduled_departure, r.estimated_fare, u.full_name AS driver_name, u.phone AS driver_phone FROM rides r LEFT JOIN drivers d ON d.id = r.driver_id LEFT JOIN users u ON u.id = d.id WHERE r.id = ? AND r.passenger_id = ? LIMIT 1');
    $query->execute([$rideId, $user['id']]);
    $ride = $query->fetch();
    if (!$ride) {
        json_response(false, 'Ride not found.', [], 404);
    }
    json_response(true, 'Ride status loaded.', ['ride' => $ride]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}
$user = current_user();
if (!$user) {
    json_response(false, 'Authentication required.', [], 401);
}
if (!verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null)) {
    json_response(false, 'Invalid security token.', [], 419);
}

$resource = $_GET['resource'] ?? '';
if ($resource !== 'rides') {
    json_response(false, 'Resource not found.', [], 404);
}

$pickup = trim((string) ($_POST['pickup_label'] ?? ''));
$destination = trim((string) ($_POST['destination_label'] ?? ''));
$vehicleType = filter_var($_POST['vehicle_type_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$distance = filter_var($_POST['distance_km'] ?? null, FILTER_VALIDATE_FLOAT);
$duration = filter_var($_POST['duration_min'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]]);
$driverId = filter_var($_POST['driver_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$departure = trim((string) ($_POST['departure_time'] ?? ''));
if ($departure !== '' && !DateTime::createFromFormat('Y-m-d\TH:i', $departure)) {
    $departure = '';
}
if ($pickup === '' || $destination === '' || !$vehicleType || $distance === false || $distance <= 0 || $distance > 1000 || !$duration || $departure === '') {
    json_response(false, 'Provide valid pickup, destination, vehicle type, distance, and duration.', [], 422);
}

try {
    $fareQuery = db()->prepare('SELECT base_fare, per_km, per_minute, service_fee, minimum_fare FROM fare_settings WHERE vehicle_type_id = ?');
    $fareQuery->execute([$vehicleType]);
    $settings = $fareQuery->fetch();
    if (!$settings) {
        json_response(false, 'That vehicle type is unavailable.', [], 422);
    }
    $estimatedFare = max(
        (float) $settings['minimum_fare'],
        (float) $settings['base_fare'] + ($distance * (float) $settings['per_km']) + ($duration * (float) $settings['per_minute']) + (float) $settings['service_fee']
    );

    $connection = db();
    $connection->beginTransaction();
    if ($driverId) {
        $driverCheck = $connection->prepare("SELECT id FROM drivers WHERE id = ? AND availability = 'AVAILABLE'");
        $driverCheck->execute([$driverId]);
        if (!$driverCheck->fetchColumn()) {
            json_response(false, 'That driver is no longer available.', [], 409);
        }
    }
    $ride = $connection->prepare('INSERT INTO rides (passenger_id, driver_id, vehicle_type_id, pickup_label, destination_label, estimated_distance_km, estimated_duration_min, estimated_fare, scheduled_departure, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'SEARCHING_DRIVER\')');
    $ride->execute([$user['id'], $driverId, $vehicleType, $pickup, $destination, $distance, $duration, $estimatedFare, str_replace('T', ' ', $departure) . ':00']);
    $rideId = (int) $connection->lastInsertId();
    $history = $connection->prepare('INSERT INTO ride_status_history (ride_id, status, changed_by) VALUES (?, \'SEARCHING_DRIVER\', ?)');
    $history->execute([$rideId, $user['id']]);
    $connection->commit();
    json_response(true, 'Ride request created.', ['ride_id' => $rideId, 'estimated_fare' => $estimatedFare, 'currency' => APP_CURRENCY], 201);
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    json_response(false, 'Unable to create ride request.', [], 500);
}
