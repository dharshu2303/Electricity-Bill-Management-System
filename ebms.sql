CREATE DATABASE ebms;
USE ebms;
  ---Users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    meter_number VARCHAR(20) NOT NULL UNIQUE,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    user_type ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
 -- Tariff
CREATE TABLE tariffs (
    tariff_id INT AUTO_INCREMENT PRIMARY KEY,
    tariff_type VARCHAR(50) NOT NULL UNIQUE,
    rate DECIMAL(10,2) NOT NULL,
    effective_date DATE NOT NULL,
    description TEXT
);

-- Meter readings
CREATE TABLE meter_readings (
    reading_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reading_date DATE NOT NULL,
    reading_value INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, reading_date) 
);
-- Bills
CREATE TABLE bills (
    bill_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bill_period_start DATE NOT NULL,
    bill_period_end DATE NOT NULL,
    due_date DATE NOT NULL,
    units_consumed INT NOT NULL,
    tariff_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    paid_date DATE,
    transaction_id VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (tariff_id) REFERENCES tariffs(tariff_id),
    CHECK (bill_period_start < bill_period_end),
    CHECK (bill_period_end <= due_date)
);
-- Notification
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
-- Rewards
CREATE TABLE reward_points (
    point_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points_earned INT NOT NULL DEFAULT 0,
    points_redeemed INT NOT NULL DEFAULT 0,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CHECK (points_earned >= 0),
    CHECK (points_redeemed >= 0)
);
-- auto bill calci
DELIMITER //
CREATE TRIGGER calculate_bill_amount
BEFORE INSERT ON bills
FOR EACH ROW
BEGIN
    DECLARE tariff_rate DECIMAL(10,2);
    SELECT rate INTO tariff_rate 
    FROM tariffs 
    WHERE tariff_id = NEW.tariff_id;
    SET NEW.amount = NEW.units_consumed * tariff_rate;
    IF NEW.amount < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Bill amount cannot be negative';
    END IF;
END//
DELIMITER ;
DELIMITER //
CREATE TRIGGER update_bill_amount_on_tariff_change
AFTER UPDATE ON tariffs
FOR EACH ROW
BEGIN
    IF NEW.rate != OLD.rate THEN
        UPDATE bills
        SET amount = units_consumed * NEW.rate
        WHERE tariff_id = NEW.tariff_id
        AND status = 'pending'; 
    END IF;
END//
DELIMITER ;
DELIMITER //
CREATE TRIGGER calculate_bill_amount_with_late_fee
BEFORE INSERT ON bills
FOR EACH ROW
BEGIN DECLARE tariff_rate DECIMAL(10,2);
    DECLARE late_fee DECIMAL(10,2) DEFAULT 500.00; 
    SELECT rate INTO tariff_rate 
    FROM tariffs 
    WHERE tariff_id = NEW.tariff_id;
    SET NEW.amount = NEW.units_consumed * tariff_rate;
    IF NEW.due_date < CURRENT_DATE AND NEW.status = 'pending' THEN
        SET NEW.amount = NEW.amount + late_fee;
        INSERT INTO notifications (user_id, title, message, notification_type)
        VALUES (
            NEW.user_id, 
            'Late Payment Fee Applied', 
            CONCAT('A fixed late fee of ₹', late_fee, ' has been added to your bill for missing the payment deadline.'),
            'payment_reminder'
        );
    END IF;
    IF NEW.amount < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Bill amount cannot be negative';
    END IF;
END//
DELIMITER ;

-- late fee if overdue
DELIMITER //
CREATE TRIGGER add_late_fee_on_overdue
BEFORE UPDATE ON bills
FOR EACH ROW
BEGIN
    DECLARE late_fee DECIMAL(10,2) DEFAULT 500.00; 
    IF (NEW.status = 'pending' AND OLD.due_date < CURRENT_DATE) THEN
        IF NEW.amount = (SELECT rate FROM tariffs WHERE tariff_id = NEW.tariff_id) * NEW.units_consumed THEN
            SET NEW.amount = NEW.amount + late_fee;
            INSERT INTO notifications (user_id, title, message, notification_type)
            VALUES (
                NEW.user_id, 
                'Late Payment Fee Applied', 
                CONCAT('A fixed late fee of ₹', late_fee, ' has been added to your bill #', 
                       NEW.bill_id, ' for missing the payment deadline.'),
                'payment_reminder'
            );
        END IF;
    END IF;
END//
DELIMITER ;
DELIMITER //
CREATE TRIGGER update_bill_amount_on_tariff_change_with_fee
AFTER UPDATE ON tariffs
FOR EACH ROW
BEGIN
    IF NEW.rate != OLD.rate THEN
        UPDATE bills
        SET amount = (units_consumed * NEW.rate) + 
                     CASE 
                         WHEN due_date < CURRENT_DATE AND status = 'pending' THEN 100.00 
                         ELSE 0 
                     END
        WHERE tariff_id = NEW.tariff_id
        AND status = 'pending';
    END IF;
END//
DELIMITER ;
INSERT INTO users (username, password, full_name, email, address, meter_number, account_number, user_type) VALUES 
('admin', '123456', 'Admin User', 'admin@ebms.com', 'Bangalore', 'ADM001', 'ADM001', 'admin'),
('arjun', '123456', 'Arjun Reddy', 'arjun@example.com', 'Hyderabad', 'MET001', 'CUS001', 'customer');
INSERT INTO tariffs (tariff_type, rate, effective_date, description) VALUES 
('Residential', 5.08, '2023-01-01', 'Standard home tariff'),
('Commercial', 6.50, '2023-01-01', 'Business tariff');
INSERT INTO meter_readings (user_id, reading_date, reading_value) VALUES
(2, '2023-01-01', 100),
(2, '2023-02-01', 150),
(2, '2023-03-01', 210);
INSERT INTO bills (user_id, bill_period_start, bill_period_end, due_date, units_consumed, tariff_id, status) VALUES
(2, '2023-01-01', '2023-01-31', '2023-02-15', 100, 1, 'paid'),
(2, '2023-02-01', '2023-02-28', '2023-03-15', 150, 1, 'paid'),
(2, '2023-03-01', '2023-03-31', '2023-04-15', 210, 1, 'pending');
SELECT bill_id, units_consumed, rate AS tariff_rate, amount 
FROM bills 
JOIN tariffs ON bills.tariff_id = tariffs.tariff_id;
UPDATE tariffs SET rate = 5.50 WHERE tariff_type = 'Residential';
SELECT bill_id, units_consumed, rate AS new_tariff_rate, amount 
FROM bills 
JOIN tariffs ON bills.tariff_id = tariffs.tariff_id
WHERE status = 'pending';