# Library Desk — PHP Backend + MySQL

This folder contains a PHP REST-style API and MySQL schema for the Library Desk frontend.

## Project structure

```text
Library_Desk/
├── index.html                 # Your existing frontend
├── STYLE.CSS                  # Your existing stylesheet
├── Dockerfile
├── .env.example
├── README.md
├── backend/
│   ├── api.php
│   └── config.php
└── database/
    └── library_management.sql
```

## 1. Create the database locally

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/phpmyadmin`.
3. Select **Import**.
4. Choose `database/library_management.sql`.
5. Click **Import**.

The SQL creates these tables: `book`, `readers`, `issued_book`, and `book_index`.
It uses `CREATE TABLE IF NOT EXISTS` and does not drop your existing tables.

> If your current database already has tables with different columns, back it up first and compare the schema before importing. Do not run DROP TABLE commands on your existing project database.

## 2. Run the PHP API locally

1. Copy the `backend` folder into `C:/xampp/htdocs/library-desk/`.
2. Start Apache and MySQL in XAMPP.
3. Update database settings in `backend/config.php` if your MySQL username/password differ.
4. Open this URL: `http://localhost/library-desk/api.php?action=stats`.

A successful response looks like:

```json
{
  "success": true,
  "data": {
    "total_books": 3,
    "issued_books": 0,
    "available_books": 3,
    "readers": 0
  }
}
```

## 3. API endpoints

All endpoints use `backend/api.php`.

| Method | URL / action | Purpose |
|---|---|---|
| GET | `?action=stats` | Dashboard counts |
| GET | `?action=books` | List all books |
| POST | `?action=login` | Admin login |
| GET | `?action=readers` | List readers |
| GET | `?action=records` | Issue/return history |
| POST | `?action=add-book` | Add a book |
| POST | `?action=add-reader` | Register a reader |
| POST | `?action=issue-book` | Issue a book |
| POST | `?action=return-book` | Return a book |
| DELETE | `?action=delete-book&book_id=1` | Delete an available book |

POST requests must send JSON. Examples:

Add a book:
```json
{
  "book_name": "Clean Code",
  "author": "Robert C. Martin",
  "genre": "Programming"
}
```

Register a reader:
```json
{
  "reader_name": "Ayesha",
  "email": "ayesha@example.com",
  "phone": "9876543210"
}
```

Issue a book:
```json
{
  "book_id": 1,
  "reader_name": "Ayesha"
}
```

Return a book:
```json
{
  "book_id": 1
}
```

## 4. Deploy on Render

Render does not provide a built-in MySQL database with a PHP web service. You need a reachable MySQL database hosted separately.

1. Push this project folder to GitHub.
2. In Render, choose **New + → Web Service** and connect the GitHub repository.
3. Select **Docker** as the runtime/environment. Render will use the included `Dockerfile`.
4. Add the environment variables from `.env.example` in Render → Environment.
5. Deploy the service.
6. Test: `https://YOUR-RENDER-SERVICE.onrender.com/api.php?action=stats`.

Keep your database password only in Render Environment Variables. Never commit real passwords to GitHub.

## Frontend (already connected)

`index.html` + `script.js` + `extra.css` now call the API: login, dashboard counts, add/issue/return/delete books, readers, issued list and records all use MySQL.

**Put your own `STYLE.CSS` in this folder** (exact name, capital letters - Linux/Render is case-sensitive).

Local run (XAMPP): copy everything inside `backend/` AND `index.html`, `STYLE.CSS`, `extra.css`, `script.js` into ONE folder, `C:/xampp/htdocs/library-desk/`, then open `http://localhost/library-desk/`.

Default login: `admin` / `admin123` (change with `ADMIN_USER` / `ADMIN_PASS`).

## Security note

This is a college-project starter API, not a production-ready public library system. Add authentication, authorization, CSRF protection, rate limiting, and stricter CORS rules before using real student data.
