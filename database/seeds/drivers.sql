USE niaride;

INSERT IGNORE INTO users (role_id, full_name, email, phone, password_hash, status)
SELECT (SELECT id FROM roles WHERE name = 'DRIVER'), driver.full_name, driver.email, driver.phone, driver.password_hash, 'ACTIVE'
FROM (
SELECT 'Amina Wanjiku' full_name,'amina.driver@niaride.local' email,'+254711000101' phone,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' password_hash UNION ALL
SELECT 'Brian Otieno','brian.driver@niaride.local','+254711000102','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Carol Njeri','carol.driver@niaride.local','+254711000103','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'David Kamau','david.driver@niaride.local','+254711000104','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Esther Achieng','esther.driver@niaride.local','+254711000105','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Felix Mwangi','felix.driver@niaride.local','+254711000106','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Grace Wambui','grace.driver@niaride.local','+254711000107','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Hassan Ali','hassan.driver@niaride.local','+254711000108','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Irene Moraa','irene.driver@niaride.local','+254711000109','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'John Kiptoo','john.driver@niaride.local','+254711000110','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Lilian Atieno','lilian.driver@niaride.local','+254711000111','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Mark Maina','mark.driver@niaride.local','+254711000112','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Nancy Jepkoech','nancy.driver@niaride.local','+254711000113','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Oscar Ouma','oscar.driver@niaride.local','+254711000114','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S' UNION ALL
SELECT 'Purity Naliaka','purity.driver@niaride.local','+254711000115','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC6E2I5G1p4M4y4QJf7S'
) driver;

INSERT IGNORE INTO drivers (id, availability, verified_at, rating, current_lat, current_lng)
SELECT u.id, 'AVAILABLE', NOW(), 4.75, locations.lat, locations.lng
FROM users u JOIN (
SELECT 'amina.driver@niaride.local' email,-1.2671 lat,36.8108 lng UNION ALL SELECT 'brian.driver@niaride.local',-1.2752,36.8151 UNION ALL SELECT 'carol.driver@niaride.local',-1.2920,36.8213 UNION ALL SELECT 'david.driver@niaride.local',-1.2585,36.8012 UNION ALL SELECT 'esther.driver@niaride.local',-1.3001,36.7875 UNION ALL SELECT 'felix.driver@niaride.local',-1.2467,36.8178 UNION ALL SELECT 'grace.driver@niaride.local',-1.2864,36.8380 UNION ALL SELECT 'hassan.driver@niaride.local',-1.3105,36.8254 UNION ALL SELECT 'irene.driver@niaride.local',-1.2791,36.7686 UNION ALL SELECT 'john.driver@niaride.local',-1.2305,36.8890 UNION ALL SELECT 'lilian.driver@niaride.local',-1.3168,36.7961 UNION ALL SELECT 'mark.driver@niaride.local',-1.2688,36.8520 UNION ALL SELECT 'nancy.driver@niaride.local',-1.3500,36.7600 UNION ALL SELECT 'oscar.driver@niaride.local',-1.2820,36.8050 UNION ALL SELECT 'purity.driver@niaride.local',-1.2170,36.8860
) locations ON locations.email = u.email;

INSERT IGNORE INTO vehicles (driver_id, vehicle_type_id, registration_no, model, color, status)
SELECT d.id, MOD(d.id, 4) + 1, CONCAT('KDX ', LPAD(d.id, 3, '0')), 'Verified NiaRide vehicle', 'Various', 'APPROVED'
FROM drivers d JOIN users u ON u.id=d.id WHERE u.email LIKE '%.driver@niaride.local';
