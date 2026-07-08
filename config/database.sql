

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','Donor','Requester') NOT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, role)
VALUES
('admin', '123456', 'Admin'),
('aungaung', '123456', 'Donor'),
('mgmg', '123456', 'Requester');

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


CREATE TABLE requester (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE donation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    requester_id INT NOT NULL,
    request_id INT NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    units INT NOT NULL,
    donation_date DATE NOT NULL,
    status ENUM('Completed') DEFAULT 'Completed',

    FOREIGN KEY (donor_id) REFERENCES donor(id),
    FOREIGN KEY (requester_id) REFERENCES requester(id),
    FOREIGN KEY (request_id) REFERENCES blood_request(id)
);

INSERT INTO donation_history
(donor_id, requester_id, request_id, blood_group, units, donation_date, status)
VALUES
(1, 2, 5, 'A+', 1, '2026-07-06', 'Completed');

CREATE TABLE blood_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    units INT NOT NULL,
    hospital VARCHAR(100) NOT NULL,
    required_date DATE NOT NULL,
    status ENUM('Pending','Approved','Completed','Rejected') DEFAULT 'Pending',

    FOREIGN KEY (requester_id)
    REFERENCES requester(requester_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE donor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100),
    address TEXT NOT NULL,    
    weight DECIMAL(5,2) NOT NULL,
    last_donation_date DATE DEFAULT NULL,
    available_status ENUM('Available','Unavailable') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_donor_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE donor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100),
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