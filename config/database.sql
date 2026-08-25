

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','User') NOT NULL DEFAULT 'User',
    status ENUM('Active','Inactive') DEFAULT 'Active',
    myanmar_name VARCHAR(100) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, email, password, role)
VALUES
('admin', 'bloodcommunicationsystem12@gmail.com', '123456', 'Admin'),
('aungaung', 'aungaung@example.com', '123456', 'Donor'),
('mgmg', 'mgmg@example.com', '123456', 'Requester');

CREATE TABLE blood_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_group_name VARCHAR(5) NOT NULL UNIQUE
);

INSERT INTO blood_groups (blood_group_name)
VALUES
('A+'),
('A-'),
('B+'),
('B-'),
('AB+'),
('AB-'),
('O+'),
('O-');

CREATE TABLE blood_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    users_id INT NOT NULL,
    requester_name VARCHAR(100) DEFAULT NULL,
    blood_groups_id INT NOT NULL,
    units INT NOT NULL,
    hospital VARCHAR(100) NOT NULL,
    required_date DATE NOT NULL,
    status ENUM('Pending','Approved','Completed','Rejected') DEFAULT 'Pending',
    assigned_donor_id INT DEFAULT NULL,

    FOREIGN KEY (users_id)
    REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
    FOREIGN KEY (blood_groups_id)
    REFERENCES blood_groups(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
    FOREIGN KEY (assigned_donor_id)
    REFERENCES donor(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

CREATE TABLE donor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,   
    gender ENUM('Male','Female','Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT NOT NULL,
    blood_groups VARCHAR(5) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    last_donation_date DATE DEFAULT NULL,
    available_status ENUM('Available','Unavailable') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_donor_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
);

CREATE TABLE donation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,    
    request_id INT NOT NULL,
    blood_groups_id INT NOT NULL,
    units INT NOT NULL,
    donation_date DATE NOT NULL,
    status ENUM('Completed') DEFAULT 'Completed',

    FOREIGN KEY (donor_id) REFERENCES donor(id),
    FOREIGN KEY (users_id) REFERENCES users(id),
    FOREIGN KEY (request_id) REFERENCES blood_request(id),
    FOREIGN KEY (blood_groups_id) REFERENCES blood_groups(id)
);

CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NULL,
    user_id INT NULL,
    recipient_email VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(100) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    email_type VARCHAR(50) NOT NULL,
    status ENUM('Pending', 'Sent', 'Delivered', 'Failed', 'Bounced', 'Opened') DEFAULT 'Pending',
    error_message TEXT DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    opened_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
