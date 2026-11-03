CREATE DATABASE IF NOT EXISTS library_management
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE library_management;

-- Main book catalogue
CREATE TABLE IF NOT EXISTS book (
    book_id INT NOT NULL AUTO_INCREMENT,
    book_name VARCHAR(150) NOT NULL,
    author VARCHAR(100) NOT NULL DEFAULT 'Not specified',
    genre VARCHAR(60) NOT NULL,
    status ENUM('Available', 'Issued') NOT NULL DEFAULT 'Available',
    reader_name VARCHAR(100) NULL,
    PRIMARY KEY (book_id),
    INDEX idx_book_status (status),
    INDEX idx_book_name (book_name)
) ENGINE=InnoDB;

-- Reader/student records
CREATE TABLE IF NOT EXISTS readers (
    reader_id INT NOT NULL AUTO_INCREMENT,
    reader_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NULL,
    phone VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reader_id),
    INDEX idx_reader_name (reader_name)
) ENGINE=InnoDB;

-- Issue and return history
CREATE TABLE IF NOT EXISTS issued_book (
    issue_id INT NOT NULL AUTO_INCREMENT,
    book_id INT NOT NULL,
    reader_id INT NOT NULL,
    issue_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('Issued', 'Returned') NOT NULL DEFAULT 'Issued',
    PRIMARY KEY (issue_id),
    INDEX idx_issue_status (status),
    CONSTRAINT fk_issued_book_book
        FOREIGN KEY (book_id) REFERENCES book(book_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_issued_book_reader
        FOREIGN KEY (reader_id) REFERENCES readers(reader_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Optional shelf/catalogue index for each book
CREATE TABLE IF NOT EXISTS book_index (
    index_id INT NOT NULL AUTO_INCREMENT,
    book_id INT NOT NULL,
    shelf_location VARCHAR(50) NULL,
    catalogue_code VARCHAR(30) NULL,
    PRIMARY KEY (index_id),
    UNIQUE KEY uq_book_index_book (book_id),
    CONSTRAINT fk_book_index_book
        FOREIGN KEY (book_id) REFERENCES book(book_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Optional sample records (run only if the tables are empty)
INSERT INTO book (book_name, author, genre, status)
SELECT 'Atomic Habits', 'James Clear', 'Self Help', 'Available'
WHERE NOT EXISTS (SELECT 1 FROM book WHERE book_name = 'Atomic Habits');

INSERT INTO book (book_name, author, genre, status)
SELECT 'Java Basics', 'Herbert Schildt', 'Programming', 'Available'
WHERE NOT EXISTS (SELECT 1 FROM book WHERE book_name = 'Java Basics');

INSERT INTO book (book_name, author, genre, status)
SELECT 'The Alchemist', 'Paulo Coelho', 'Fiction', 'Available'
WHERE NOT EXISTS (SELECT 1 FROM book WHERE book_name = 'The Alchemist');
