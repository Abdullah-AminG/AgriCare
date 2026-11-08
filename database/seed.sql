-- =====================================================================
-- AgriCare - Crop Health and Advisory System
-- FILE: seed.sql  (SAMPLE DATA for development and testing only)
--
-- Run ONCE, on a database created with schema.sql:
--   sudo mysql < database/seed.sql
--
-- Safety design:
--   * Plain INSERT statements (not INSERT IGNORE / REPLACE). If sample rows
--     already exist, MySQL stops with a "Duplicate entry" error instead of
--     silently overwriting or duplicating data.
--   * Everything is inside ONE transaction. If any statement fails, the mysql
--     client exits, the connection closes and the whole seed is rolled back,
--     so you never get half of the sample data.
--   * This file contains no DROP, DELETE or TRUNCATE statements.
--
-- Sample logins (public TEST passwords, never use them on a real site):
--   admin@agricare.local / Admin@123
--   ali@example.com      / Farmer@123
--   bilal@example.com    / Farmer@123
-- Only bcrypt hashes are stored in the database.
-- =====================================================================

USE agricare;

START TRANSACTION;

INSERT INTO users (user_id, full_name, phone, email, password_hash, role) VALUES
(1, 'Ayesha Malik (Adviser)', '03001112233', 'admin@agricare.local',
    '$2b$12$eyeH3Y3D9nWeW8XjRSY5VO1JhfFDNBKf/Jlb9CMTdNgGdnXyUtqdi', 'admin'),
(2, 'Ali Raza',    '03214445566', 'ali@example.com',
    '$2b$12$svmnaNn1ntZi6NNb7C1yFu1x5dbMAPgUdmvnajH.ldhClkLjdegx6', 'farmer'),
(3, 'Bilal Ahmed', '03337778899', 'bilal@example.com',
    '$2b$12$svmnaNn1ntZi6NNb7C1yFu1x5dbMAPgUdmvnajH.ldhClkLjdegx6', 'farmer');

INSERT INTO crops (crop_id, crop_name, description) VALUES
(1, 'Wheat',     'Main winter (rabi) cereal crop of Punjab, sown in November and harvested in April.'),
(2, 'Rice',      'Major summer (kharif) cereal crop grown in flooded fields, transplanted in June and July.'),
(3, 'Cotton',    'Important cash fiber crop sown in spring and picked from September onwards.'),
(4, 'Sugarcane', 'Long-duration cash crop grown for sugar and jaggery production.'),
(5, 'Maize',     'Cereal crop grown in spring and autumn seasons for grain and fodder.'),
(6, 'Potato',    'Cool-season tuber crop grown mainly in the autumn and spring seasons.');

INSERT INTO diseases (disease_id, crop_id, disease_name, symptoms, prevention) VALUES
(1, 1, 'Yellow Rust (Stripe Rust)',
    'Yellow to orange powdery stripes on leaves, running parallel to the veins. Leaves dry out early in severe cases.',
    'Grow resistant varieties, sow on time, avoid excess nitrogen and inspect fields regularly in cool humid weather.'),
(2, 1, 'Leaf Rust',
    'Small round orange-brown pustules scattered on the upper leaf surface.',
    'Use resistant varieties, remove volunteer wheat plants and follow recommended sowing dates.'),
(3, 1, 'Powdery Mildew',
    'White powdery patches on leaves and stems that later turn grey or brown.',
    'Avoid dense sowing, balance fertilizer use and improve air movement in the field.'),
(4, 2, 'Rice Blast',
    'Diamond-shaped grey spots with brown edges on leaves; the neck of the panicle may turn black and break.',
    'Use certified seed and resistant varieties, avoid excess nitrogen and maintain proper field water management.'),
(5, 2, 'Bacterial Leaf Blight',
    'Water-soaked streaks on leaf edges that turn yellow and then dry, starting from the leaf tip.',
    'Use healthy seed, avoid injuring seedlings during transplanting and keep the field free of infected stubble.'),
(6, 3, 'Cotton Leaf Curl Virus',
    'Upward or downward curling of leaves, thickened dark veins and stunted plant growth. Spread by whitefly.',
    'Control whitefly, remove infected plants early, use tolerant varieties and avoid late sowing.'),
(7, 4, 'Red Rot',
    'Reddish internal stem tissue with white patches and a sour smell; leaves dry from the top.',
    'Plant disease-free setts, use resistant varieties, remove and destroy infected clumps and rotate crops.'),
(8, 5, 'Northern Leaf Blight',
    'Long grey-green cigar-shaped lesions on leaves that turn tan as they grow.',
    'Use resistant hybrids, rotate crops and bury or remove infected crop residue.'),
(9, 6, 'Late Blight',
    'Dark water-soaked patches on leaves with white growth underneath in humid weather; tubers rot.',
    'Plant certified seed tubers, avoid overhead irrigation at night and remove infected plants quickly.'),
(10, 6, 'Early Blight',
    'Brown spots with concentric rings on older leaves, often with a yellow border.',
    'Use crop rotation, keep plants well fed and watered, and remove infected leaves.');

INSERT INTO farms (farm_id, user_id, farm_name, location, area) VALUES
(1, 2, 'Green Valley Farm', 'Sheikhupura, Punjab', 12.50),
(2, 2, 'Riverside Fields',  'Kasur, Punjab',        6.00),
(3, 3, 'Bilal Family Farm', 'Faisalabad, Punjab',   8.25);

INSERT INTO farm_crops (farm_crop_id, farm_id, crop_id, planting_date) VALUES
(1, 1, 1, '2025-11-10'),
(2, 1, 5, '2026-03-15'),
(3, 2, 2, '2026-06-20'),
(4, 3, 3, '2026-05-01'),
(5, 3, 4, '2026-02-10');

INSERT INTO reports (report_id, user_id, farm_crop_id, image_path, symptoms, report_date, status) VALUES
(1, 2, 1, NULL,
    'Yellow stripes are visible on many wheat leaves in the north corner of the field. A yellow powder comes off on my fingers.',
    '2026-01-12 09:30:00', 'Resolved'),
(2, 2, 3, NULL,
    'Grey spots with brown borders have appeared on rice leaves after the recent rain. About one acre is affected.',
    '2026-10-05 14:10:00', 'Under Review'),
(3, 3, 4, NULL,
    'Cotton leaves are curling upward and the veins look thick and dark. Many whiteflies are present on the plants.',
    '2026-11-01 08:45:00', 'Pending');

INSERT INTO advice (advice_id, report_id, reviewer_id, disease_id, advice_text, response_date) VALUES
(1, 1, 1, 1,
    'The symptoms match Yellow Rust. Spray a recommended fungicide as soon as possible and repeat after 15 days if needed. Inspect the whole field and avoid extra nitrogen fertilizer.',
    '2026-01-13 11:00:00'),
(2, 2, 1, NULL,
    'Please upload a clear close-up photo of an affected leaf and tell us how long the field was flooded. I will review it again after that.',
    '2026-10-06 10:20:00');

INSERT INTO report_updates (update_id, report_id, update_note, update_date) VALUES
(1, 1, 'I sprayed the fungicide as advised. New leaves look healthier and the stripes are not spreading.', '2026-01-20 16:00:00'),
(2, 2, 'The field was flooded for about 12 days. I will upload a photo tomorrow.', '2026-10-07 09:15:00');

COMMIT;
