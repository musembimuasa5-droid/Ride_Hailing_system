CREATE DATABASE IF NOT EXISTS niaride CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE niaride;

CREATE TABLE roles (
    id TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL UNIQUE
);
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    role_id TINYINT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('ACTIVE','SUSPENDED','PENDING') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id), INDEX idx_users_role (role_id, status)
);
CREATE TABLE vehicle_types (
    id SMALLINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    capacity TINYINT UNSIGNED NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE TABLE drivers (
    id BIGINT UNSIGNED PRIMARY KEY,
    availability ENUM('OFFLINE','AVAILABLE','ON_TRIP') NOT NULL DEFAULT 'OFFLINE',
    verified_at TIMESTAMP NULL,
    rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    current_lat DECIMAL(10,7) NULL,
    current_lng DECIMAL(10,7) NULL,
    FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE vehicles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    driver_id BIGINT UNSIGNED NOT NULL,
    vehicle_type_id SMALLINT UNSIGNED NOT NULL,
    registration_no VARCHAR(20) NOT NULL UNIQUE,
    model VARCHAR(80) NOT NULL,
    color VARCHAR(30) NOT NULL,
    status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    FOREIGN KEY (driver_id) REFERENCES drivers(id), FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id),
    INDEX idx_vehicles_type_status (vehicle_type_id, status)
);
CREATE TABLE fare_settings (
    id SMALLINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    vehicle_type_id SMALLINT UNSIGNED NOT NULL UNIQUE,
    base_fare DECIMAL(10,2) NOT NULL,
    per_km DECIMAL(10,2) NOT NULL,
    per_minute DECIMAL(10,2) NOT NULL,
    service_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    minimum_fare DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id)
);
CREATE TABLE rides (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    passenger_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NULL,
    vehicle_type_id SMALLINT UNSIGNED NOT NULL,
    pickup_label VARCHAR(255) NOT NULL,
    destination_label VARCHAR(255) NOT NULL,
    pickup_lat DECIMAL(10,7) NULL, pickup_lng DECIMAL(10,7) NULL,
    destination_lat DECIMAL(10,7) NULL, destination_lng DECIMAL(10,7) NULL,
    estimated_distance_km DECIMAL(8,2) NOT NULL,
    estimated_duration_min SMALLINT UNSIGNED NOT NULL,
    estimated_fare DECIMAL(10,2) NOT NULL,
    final_fare DECIMAL(10,2) NULL,
    status ENUM('REQUESTED','SEARCHING_DRIVER','ACCEPTED','DRIVER_ARRIVING','DRIVER_ARRIVED','IN_PROGRESS','COMPLETED','CANCELLED_BY_PASSENGER','CANCELLED_BY_DRIVER','NO_DRIVER_AVAILABLE') NOT NULL DEFAULT 'REQUESTED',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scheduled_departure DATETIME NULL,
    started_at TIMESTAMP NULL, completed_at TIMESTAMP NULL,
    FOREIGN KEY (passenger_id) REFERENCES users(id), FOREIGN KEY (driver_id) REFERENCES drivers(id), FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id),
    INDEX idx_rides_passenger_status (passenger_id, status), INDEX idx_rides_driver_status (driver_id, status)
);
CREATE TABLE ride_status_history (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, ride_id BIGINT UNSIGNED NOT NULL, status VARCHAR(40) NOT NULL, changed_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE, FOREIGN KEY (changed_by) REFERENCES users(id));
CREATE TABLE payments (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, ride_id BIGINT UNSIGNED NOT NULL UNIQUE, method ENUM('MPESA','CARD','CASH') NOT NULL, amount DECIMAL(10,2) NOT NULL, status ENUM('PENDING','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING', provider_reference VARCHAR(120) NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (ride_id) REFERENCES rides(id));
CREATE TABLE ratings (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, ride_id BIGINT UNSIGNED NOT NULL UNIQUE, passenger_id BIGINT UNSIGNED NOT NULL, driver_id BIGINT UNSIGNED NOT NULL, score TINYINT UNSIGNED NOT NULL CHECK (score BETWEEN 1 AND 5), feedback TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (ride_id) REFERENCES rides(id), FOREIGN KEY (passenger_id) REFERENCES users(id), FOREIGN KEY (driver_id) REFERENCES drivers(id));
CREATE TABLE notifications (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, title VARCHAR(140) NOT NULL, body VARCHAR(500) NOT NULL, read_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_notifications_user (user_id, read_at));

INSERT INTO roles (name) VALUES ('PASSENGER'), ('DRIVER'), ('ADMIN');
INSERT INTO vehicle_types (name, description, capacity) VALUES ('Motorcycle','Fast and nimble for solo trips',1), ('Economy','Reliable everyday rides',4), ('Comfort','Extra space and comfort',4), ('Van','Room for groups and luggage',8);
INSERT INTO fare_settings (vehicle_type_id, base_fare, per_km, per_minute, service_fee, minimum_fare) SELECT id, CASE name WHEN 'Motorcycle' THEN 70 WHEN 'Economy' THEN 100 WHEN 'Comfort' THEN 160 ELSE 240 END, CASE name WHEN 'Motorcycle' THEN 22 WHEN 'Economy' THEN 32 WHEN 'Comfort' THEN 48 ELSE 65 END, CASE name WHEN 'Motorcycle' THEN 3 WHEN 'Economy' THEN 5 WHEN 'Comfort' THEN 7 ELSE 9 END, 30, CASE name WHEN 'Motorcycle' THEN 100 WHEN 'Economy' THEN 180 WHEN 'Comfort' THEN 260 ELSE 400 END FROM vehicle_types;
