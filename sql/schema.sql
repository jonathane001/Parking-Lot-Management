-- Parking Lot Management System
-- CIS 344 Fall 2026 - Jonathan Echavarria

DROP DATABASE IF EXISTS parking_lot;
CREATE DATABASE parking_lot;
USE parking_lot;

-- Staff who log in to the app
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin', 'attendant') NOT NULL DEFAULT 'attendant',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Each parking space in the lot
CREATE TABLE parking_spots (
    spot_id     INT AUTO_INCREMENT PRIMARY KEY,
    spot_number VARCHAR(10) NOT NULL UNIQUE,
    spot_type   ENUM('regular', 'compact', 'handicap', 'ev') NOT NULL DEFAULT 'regular',
    hourly_rate DECIMAL(6,2) NOT NULL,
    is_occupied BOOLEAN NOT NULL DEFAULT FALSE,
    CHECK (hourly_rate > 0)
);

-- Cars that have used the lot
CREATE TABLE vehicles (
    vehicle_id   INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(15) NOT NULL UNIQUE,
    make         VARCHAR(30),
    model        VARCHAR(30),
    color        VARCHAR(20),
    owner_name   VARCHAR(60),
    owner_phone  VARCHAR(20)
);

-- One ticket per visit: created on entry, closed on exit
CREATE TABLE tickets (
    ticket_id  INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    spot_id    INT NOT NULL,
    entry_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    exit_time  DATETIME NULL,
    fee        DECIMAL(8,2) NULL,
    status     ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    created_by INT NOT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (spot_id)    REFERENCES parking_spots(spot_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    CHECK (exit_time IS NULL OR exit_time >= entry_time)
);

-- One payment per closed ticket
CREATE TABLE payments (
    payment_id   INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id    INT NOT NULL UNIQUE,
    amount       DECIMAL(8,2) NOT NULL,
    method       ENUM('cash', 'card') NOT NULL,
    paid_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_by INT NOT NULL,
    FOREIGN KEY (ticket_id)    REFERENCES tickets(ticket_id),
    FOREIGN KEY (processed_by) REFERENCES users(user_id),
    CHECK (amount >= 0)
);

-- Speeds up the "which cars are parked right now" lookup
CREATE INDEX idx_tickets_status ON tickets(status);

-- Sample data

-- Logins: admin / admin123   and   maria / staff1234
INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$10$hMfEzxH0fKhR6T3WYvzXh.YgBoFuSZTGjPQySzML4MnCIxVR8z1Ae', 'admin'),
('maria', '$2y$10$3Na6g/6H5noMz99ecJfjE.jipJ7EKddTq5FmQ8vh9i5TR7Fhf2pau', 'attendant');

INSERT INTO parking_spots (spot_number, spot_type, hourly_rate) VALUES
('A1', 'regular', 5.00), ('A2', 'regular', 5.00), ('A3', 'regular', 5.00),
('B1', 'compact', 4.00), ('B2', 'compact', 4.00),
('H1', 'handicap', 3.00),
('E1', 'ev', 6.00), ('E2', 'ev', 6.00);

INSERT INTO vehicles (plate_number, make, model, color, owner_name, owner_phone) VALUES
('KJM4821', 'Honda',  'Civic',   'Black', 'Maria Lopez',  '718-555-0142'),
('LRT9034', 'Toyota', 'Camry',   'White', 'David Kim',    '347-555-0198'),
('HBC1177', 'Tesla',  'Model 3', 'Red',   'Aisha Bello',  '646-555-0173'),
('JNX5530', 'Ford',   'Escape',  'Blue',  'Carlos Reyes', '917-555-0110'),
('GPW2086', 'Nissan', 'Sentra',  'Gray',  'Emily Chen',   '718-555-0166');

-- Past visits that are already paid (dates are relative to today so reports always have data)
INSERT INTO tickets (vehicle_id, spot_id, entry_time, exit_time, fee, status, created_by) VALUES
(1, 1, NOW() - INTERVAL 6 DAY - INTERVAL 5 HOUR, NOW() - INTERVAL 6 DAY - INTERVAL 3 HOUR, 10.00, 'closed', 2),
(2, 4, NOW() - INTERVAL 5 DAY - INTERVAL 8 HOUR, NOW() - INTERVAL 5 DAY - INTERVAL 7 HOUR, 4.00,  'closed', 2),
(3, 7, NOW() - INTERVAL 4 DAY - INTERVAL 6 HOUR, NOW() - INTERVAL 4 DAY - INTERVAL 2 HOUR, 24.00, 'closed', 1),
(1, 2, NOW() - INTERVAL 3 DAY - INTERVAL 4 HOUR, NOW() - INTERVAL 3 DAY - INTERVAL 1 HOUR, 15.00, 'closed', 2),
(4, 6, NOW() - INTERVAL 2 DAY - INTERVAL 3 HOUR, NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR, 6.00,  'closed', 1),
(5, 5, NOW() - INTERVAL 1 DAY - INTERVAL 9 HOUR, NOW() - INTERVAL 1 DAY - INTERVAL 7 HOUR, 8.00,  'closed', 2),
(1, 1, NOW() - INTERVAL 1 DAY - INTERVAL 5 HOUR, NOW() - INTERVAL 1 DAY - INTERVAL 4 HOUR, 5.00,  'closed', 2);

INSERT INTO payments (ticket_id, amount, method, paid_at, processed_by) VALUES
(1, 10.00, 'card', NOW() - INTERVAL 6 DAY - INTERVAL 3 HOUR, 2),
(2, 4.00,  'cash', NOW() - INTERVAL 5 DAY - INTERVAL 7 HOUR, 2),
(3, 24.00, 'card', NOW() - INTERVAL 4 DAY - INTERVAL 2 HOUR, 1),
(4, 15.00, 'card', NOW() - INTERVAL 3 DAY - INTERVAL 1 HOUR, 2),
(5, 6.00,  'cash', NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR, 1),
(6, 8.00,  'card', NOW() - INTERVAL 1 DAY - INTERVAL 7 HOUR, 2),
(7, 5.00,  'cash', NOW() - INTERVAL 1 DAY - INTERVAL 4 HOUR, 2);

-- Two cars parked right now
INSERT INTO tickets (vehicle_id, spot_id, entry_time, created_by) VALUES
(2, 3, NOW() - INTERVAL 95 MINUTE, 2),
(3, 8, NOW() - INTERVAL 40 MINUTE, 1);

UPDATE parking_spots SET is_occupied = TRUE WHERE spot_id IN (3, 8);
