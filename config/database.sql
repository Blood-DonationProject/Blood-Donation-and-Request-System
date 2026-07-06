-- =====================================
-- USERS
-- =====================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'DONOR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, name, email, password, phone, role)
VALUES ('admin', 'Administrator', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0000000000', 'ADMIN');

-- =====================================
-- HOSPITAL
-- =====================================
CREATE TABLE Hospital (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hospital_name VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    phone BIGINT
);

-- =====================================
-- BLOOD GROUPS
-- =====================================
CREATE TABLE Blood_Groups (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    blood_gp_name VARCHAR(10) NOT NULL UNIQUE
);

-- =====================================
-- DONORS
-- =====================================
CREATE TABLE Donors (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    blood_gp_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,   
    gender VARCHAR(20),
    last_donation VARCHAR(100),
    response_date DATE,
    status ENUM('AVAILABLE', 'UNAVAILABLE', 'PENDING'),

    CONSTRAINT fk_donor_bloodgroup
        FOREIGN KEY (blood_gp_id)
        REFERENCES Blood_Groups(id),

    CONSTRAINT fk_donor_user
        FOREIGN KEY (user_id)
        REFERENCES Users(id)
);

-- =====================================
-- REQUEST
-- =====================================
CREATE TABLE Request (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hospital_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    blood_gp_id BIGINT NOT NULL,
    donor_id BIGINT,
    patient_name VARCHAR(255) NOT NULL,
    units_needed VARCHAR(50),
    required_date VARCHAR(50),
    status ENUM('PENDING', 'APPROVED', 'FULFILLED', 'CANCELLED'),

    CONSTRAINT fk_request_hospital
        FOREIGN KEY (hospital_id)
        REFERENCES Hospital(id),

    CONSTRAINT fk_request_user
        FOREIGN KEY (user_id)
        REFERENCES Users(id),

    CONSTRAINT fk_request_bloodgroup
        FOREIGN KEY (blood_gp_id)
        REFERENCES Blood_Groups(id),

    CONSTRAINT fk_request_donor
        FOREIGN KEY (donor_id)
        REFERENCES Donors(id)
);

-- =====================================
-- REQUEST ACTIONS
-- =====================================
CREATE TABLE Request_actions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    request_id BIGINT NOT NULL,
    donor_id BIGINT NOT NULL,
    action_type ENUM('ACCEPTED', 'REJECTED', 'DONATED', 'CANCELLED'),
    action_date DATE,
    remarks VARCHAR(255),
    create_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_requestactions_request
        FOREIGN KEY (request_id)
        REFERENCES Request(id),

    CONSTRAINT fk_requestactions_donor
        FOREIGN KEY (donor_id)
        REFERENCES Donors(id)
);

-- =====================================
-- ELIGIBILITY CHECKS
-- =====================================
CREATE TABLE Eligibility_checks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    donor_id BIGINT NOT NULL,
    check_date DATE,
    result ENUM('ELIGIBLE', 'NOT_ELIGIBLE', 'PENDING'),
    next_eligible_date DATE,
    remarks VARCHAR(255),

    CONSTRAINT fk_eligibility_donor
        FOREIGN KEY (donor_id)
        REFERENCES Donors(id)
);

-- =====================================
-- CERTIFICATE
-- =====================================
CREATE TABLE Certificate (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    donor_id BIGINT NOT NULL,
    checks_id BIGINT NOT NULL,
    certificate_no VARCHAR(100) UNIQUE,
    issue_date DATE,
    status ENUM('ACTIVE', 'EXPIRED', 'REVOKED'),

    CONSTRAINT fk_certificate_donor
        FOREIGN KEY (donor_id)
        REFERENCES Donors(id),

    CONSTRAINT fk_certificate_check
        FOREIGN KEY (checks_id)
        REFERENCES Eligibility_checks(id)
);

-- =====================================
-- NOTIFICATIONS
-- =====================================
CREATE TABLE Notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    message VARCHAR(500),
    status VARCHAR(50),
    create_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id)
        REFERENCES Users(id)
);

CREATE DATABASE bloodbank;
USE bloodbank;

CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(100),
    blood_group VARCHAR(5),
    hospital VARCHAR(150),
    department VARCHAR(100),
    units_required INT,
    status ENUM('Critical','Pending','Fulfilled','In Progress'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO requests
(patient_name,blood_group,hospital,department,units_required,status)

VALUES
('Arthur Morgan','O+','St. Jude Medical Center','Emergency',4,'Critical'),

('Elena Martinez','A-','City General Hospital','Surgical',2,'Pending'),

('John Wickham','B+',"North Star Children's",'Pediatric',1,'Fulfilled'),

('Sarah Connor','O-','Westside Trauma Center','ICU',6,'In Progress');


<div class="flex justify-between p-6">

                        <h2 class="font-bold text-xl">

                            Active Blood Requests

                        </h2>

                        <a href="add.php"

                            class="bg-red-700 text-white px-5 py-2 rounded">

                            New Request

                        </a>

                    </div>