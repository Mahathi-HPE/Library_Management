CREATE DATABASE IF NOT EXISTS Library;
USE Library;

CREATE TABLE IF NOT EXISTS Author (
    Aid INT AUTO_INCREMENT PRIMARY KEY,
    AuthLoc VARCHAR(255),
    AuthEmail VARCHAR(255) UNIQUE,
    AuthName VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS Books (
    Bid INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255),
    PubDate DATE,
    Price FLOAT
);

CREATE TABLE IF NOT EXISTS BookAuthor (
    Bid INT,
    Aid INT,
    PRIMARY KEY (Bid, Aid),
    FOREIGN KEY (Bid) REFERENCES Books(Bid) ON DELETE CASCADE,
    FOREIGN KEY (Aid) REFERENCES Author(Aid) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Members (
    Mid INT AUTO_INCREMENT PRIMARY KEY,
    MemName VARCHAR(255),
    MemEmail VARCHAR(255) UNIQUE,
    MemLoc VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS Copies (
    Cid INT AUTO_INCREMENT PRIMARY KEY,
    Bid INT,
    Status ENUM('Available', 'Rented') DEFAULT 'Available',
    FOREIGN KEY (Bid) REFERENCES Books(Bid) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Borrows (
    Cid INT,
    Mid INT,
    Bdate DATE,
    Fine FLOAT DEFAULT 0,
    FineStatus ENUM('Paid', 'Not Paid', 'NA') DEFAULT 'NA',
    PRIMARY KEY (Cid, Mid),
    FOREIGN KEY (Cid) REFERENCES Copies(Cid) ON DELETE CASCADE,
    FOREIGN KEY (Mid) REFERENCES Members(Mid) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Roles (
    Rid INT AUTO_INCREMENT PRIMARY KEY,
    RName ENUM('User', 'Admin') UNIQUE
);

CREATE TABLE IF NOT EXISTS Users (
    Uid INT AUTO_INCREMENT PRIMARY KEY,
    Username VARCHAR(255) UNIQUE,
    Password VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS UserRole (
    Rid INT,
    Uid INT,
    PRIMARY KEY (Rid, Uid),
    FOREIGN KEY (Rid) REFERENCES Roles(Rid) ON DELETE CASCADE,
    FOREIGN KEY (Uid) REFERENCES Users(Uid) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS UserMember (
    Uid INT,
    Mid INT,
    PRIMARY KEY (Uid, Mid),
    FOREIGN KEY (Uid) REFERENCES Users(Uid) ON DELETE CASCADE,
    FOREIGN KEY (Mid) REFERENCES Members(Mid) ON DELETE CASCADE
);

INSERT IGNORE INTO Roles (Rid, RName) VALUES
    (1, 'User'),
    (2, 'Admin');

INSERT IGNORE INTO Members (Mid, MemName, MemEmail, MemLoc) VALUES
    (1, 'Member One', 'member1@library.com', 'City A');

INSERT IGNORE INTO Users (Uid, Username, Password) VALUES
    (1, 'member1@library.com', 'password123'),
    (2, 'admin', 'password123');

INSERT IGNORE INTO UserRole (Rid, Uid) VALUES
    (1, 1),
    (2, 2);

INSERT IGNORE INTO UserMember (Uid, Mid) VALUES
    (1, 1);

INSERT IGNORE INTO Author (Aid, AuthLoc, AuthEmail, AuthName) VALUES
    (1, 'UK', 'orwell@example.com', 'George Orwell');

INSERT IGNORE INTO Books (Bid, Title, PubDate, Price) VALUES
    (1, '1984', '1949-06-08', 399.00);

INSERT IGNORE INTO BookAuthor (Bid, Aid) VALUES
    (1, 1);

INSERT IGNORE INTO Copies (Cid, Bid, Status) VALUES
    (1, 1, 'Available');

-- Default password for seeded users above is: password123
