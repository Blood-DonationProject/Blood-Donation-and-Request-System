-- =====================================
-- USERS
-- =====================================

CREATE TABLE IF NOT EXISTS `users`(
    `id`           INT           AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)   NOT NULL UNIQUE,
    ` email`          VARCHAR(150) UNIQUE NOT NULL,
   ` password`      VARCHAR(50)  NOT NULL,
   `confirm password`      VARCHAR(50)  NOT NULL
);

INSERT INTO users (username, email, password, confirm_password)
VALUES ('admin', 'admin@gmail.com', 'password123', 'password123');


CREATE TABLE Users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address VARCHAR(255),
    role ENUM('ADMIN', 'DONOR', 'HOSPITAL', 'PATIENT') NOT NULL
);

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
    dthistory_id BIGINT,
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
    dthistory_id BIGINT,
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