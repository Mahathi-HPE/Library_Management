# Library Management System (PHP MVC + OOP)

This project is built using:
- PHP (OOP + MVC)
- MySQL
- HTML + Bootstrap

## Folder Structure

```
Library_Management/
├── config/
├── controllers/
├── core/
├── database/
├── models/
├── public/
├── views/
└── index.php
```

## Setup

1. Place folder in `xampp/htdocs/Library_Management`.
2. Start Apache and MySQL in XAMPP.
3. Import `database/schema.sql` in phpMyAdmin.
4. Configure `config/dbconn.php` if your DB credentials differ.
5. Open: `http://localhost/Library_Management/`

## Login

Seeded users:
- Member login: username `member1@library.com`, password `password123`, role `Member`
- Admin login: username `admin`, password `password123`, role `Admin`

In DB tables, role values are `User` and `Admin`; UI label shows Member/Admin.

## Features Implemented

### Member
- Self registration from login page
- All Books (search + borrow)
- Currently Borrowed Books
- Return Books
- History
- Logout

### Admin
- Manage Users (MemName, Title, Price, AuthName)
- Add New Book (Title, AuthName, Price, PubDate)
- Monitor Fines (MemName, Title, Fine, FineStatus)
- Logout

## Notes

- Login supports both hashed passwords and plain text values for easy initial setup.
- For production, store hashed passwords using PHP `password_hash`.
- New member registrations use a `UserMember` mapping table so username and email can be stored separately.
