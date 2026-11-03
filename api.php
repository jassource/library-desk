<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$allowedOrigin = getenv('APP_ORIGIN') ?: '*';
header("Access-Control-Allow-Origin: {$allowedOrigin}");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    // Dashboard totals
    if ($method === 'GET' && $action === 'stats') {
        $stats = $pdo->query("
            SELECT
                COUNT(*) AS total_books,
                SUM(status = 'Issued') AS issued_books,
                SUM(status = 'Available') AS available_books
            FROM book
        ")->fetch();

        $stats['readers'] = (int) $pdo->query("SELECT COUNT(*) FROM readers")->fetchColumn();

        foreach (['total_books', 'issued_books', 'available_books', 'readers'] as $key) {
            $stats[$key] = (int) ($stats[$key] ?? 0);
        }

        jsonResponse(['success' => true, 'data' => $stats]);
    }

    // Complete book list with current issue details
    if ($method === 'GET' && $action === 'books') {
        $stmt = $pdo->query("
            SELECT b.book_id, b.book_name, b.author, b.genre, b.status,
                   b.reader_name, ib.issue_date, ib.return_date
            FROM book b
            LEFT JOIN issued_book ib
              ON ib.book_id = b.book_id AND ib.status = 'Issued'
            ORDER BY b.book_id DESC
        ");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Add a new book
    if ($method === 'POST' && $action === 'add-book') {
        $data = requestData();
        $bookName = trim((string) ($data['book_name'] ?? ''));
        $author = trim((string) ($data['author'] ?? 'Not specified'));
        $genre = trim((string) ($data['genre'] ?? ''));

        if ($bookName === '' || $genre === '') {
            jsonResponse(['success' => false, 'message' => 'Book name and genre are required.'], 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO book (book_name, author, genre, status, reader_name)
            VALUES (:book_name, :author, :genre, 'Available', NULL)
        ");
        $stmt->execute([
            ':book_name' => $bookName,
            ':author' => $author,
            ':genre' => $genre,
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Book added successfully.',
            'book_id' => (int) $pdo->lastInsertId(),
        ], 201);
    }

    // Register a reader
    if ($method === 'POST' && $action === 'add-reader') {
        $data = requestData();
        $readerName = trim((string) ($data['reader_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($readerName === '') {
            jsonResponse(['success' => false, 'message' => 'Reader name is required.'], 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO readers (reader_name, email, phone)
            VALUES (:reader_name, :email, :phone)
        ");
        $stmt->execute([
            ':reader_name' => $readerName,
            ':email' => $email !== '' ? $email : null,
            ':phone' => $phone !== '' ? $phone : null,
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Reader registered successfully.',
            'reader_id' => (int) $pdo->lastInsertId(),
        ], 201);
    }

    // Issue a book to a reader. Reader is created if not already registered.
    if ($method === 'POST' && $action === 'issue-book') {
        $data = requestData();
        $bookId = filter_var($data['book_id'] ?? null, FILTER_VALIDATE_INT);
        $readerName = trim((string) ($data['reader_name'] ?? ''));

        if (!$bookId || $readerName === '') {
            jsonResponse(['success' => false, 'message' => 'Valid book_id and reader_name are required.'], 422);
        }

        $pdo->beginTransaction();

        $bookStmt = $pdo->prepare("SELECT book_id, status FROM book WHERE book_id = ? FOR UPDATE");
        $bookStmt->execute([$bookId]);
        $book = $bookStmt->fetch();

        if (!$book) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Book not found.'], 404);
        }
        if ($book['status'] !== 'Available') {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'This book is already issued.'], 409);
        }

        $readerStmt = $pdo->prepare("SELECT reader_id FROM readers WHERE reader_name = ? LIMIT 1");
        $readerStmt->execute([$readerName]);
        $readerId = $readerStmt->fetchColumn();

        if (!$readerId) {
            $insertReader = $pdo->prepare("INSERT INTO readers (reader_name) VALUES (?)");
            $insertReader->execute([$readerName]);
            $readerId = (int) $pdo->lastInsertId();
        }

        $issueStmt = $pdo->prepare("
            INSERT INTO issued_book (book_id, reader_id, issue_date, status)
            VALUES (?, ?, CURDATE(), 'Issued')
        ");
        $issueStmt->execute([$bookId, $readerId]);

        $updateBook = $pdo->prepare("UPDATE book SET status = 'Issued', reader_name = ? WHERE book_id = ?");
        $updateBook->execute([$readerName, $bookId]);

        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Book issued successfully.']);
    }

    // Return a currently issued book
    if ($method === 'POST' && $action === 'return-book') {
        $data = requestData();
        $bookId = filter_var($data['book_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$bookId) {
            jsonResponse(['success' => false, 'message' => 'Valid book_id is required.'], 422);
        }

        $pdo->beginTransaction();

        $issueStmt = $pdo->prepare("
            SELECT issue_id FROM issued_book
            WHERE book_id = ? AND status = 'Issued'
            ORDER BY issue_id DESC LIMIT 1 FOR UPDATE
        ");
        $issueStmt->execute([$bookId]);
        $issueId = $issueStmt->fetchColumn();

        if (!$issueId) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'No active issue record found for this book.'], 404);
        }

        $pdo->prepare("UPDATE issued_book SET status = 'Returned', return_date = CURDATE() WHERE issue_id = ?")
            ->execute([$issueId]);
        $pdo->prepare("UPDATE book SET status = 'Available', reader_name = NULL WHERE book_id = ?")
            ->execute([$bookId]);

        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Book returned successfully.']);
    }

    // Delete a book only if it is not currently issued
    if ($method === 'DELETE' && $action === 'delete-book') {
        $bookId = filter_var($_GET['book_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$bookId) {
            jsonResponse(['success' => false, 'message' => 'Valid book_id is required.'], 422);
        }

        $stmt = $pdo->prepare("SELECT status FROM book WHERE book_id = ?");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            jsonResponse(['success' => false, 'message' => 'Book not found.'], 404);
        }
        if ($book['status'] === 'Issued') {
            jsonResponse(['success' => false, 'message' => 'Return this book before deleting it.'], 409);
        }

        $pdo->prepare("DELETE FROM book WHERE book_id = ?")->execute([$bookId]);
        jsonResponse(['success' => true, 'message' => 'Book deleted successfully.']);
    }

    // Admin login (credentials come from environment variables)
    if ($method === 'POST' && $action === 'login') {
        $data = requestData();
        $u = (string) ($data['username'] ?? '');
        $p = (string) ($data['password'] ?? '');
        $okUser = hash_equals(getenv('ADMIN_USER') ?: 'admin', $u);
        $okPass = hash_equals(getenv('ADMIN_PASS') ?: 'admin123', $p);
        if ($okUser && $okPass) {
            jsonResponse(['success' => true, 'message' => 'Login successful.']);
        }
        jsonResponse(['success' => false, 'message' => 'Wrong username or password.'], 401);
    }

    // All readers with the number of books they currently hold
    if ($method === 'GET' && $action === 'readers') {
        $stmt = $pdo->query("
            SELECT r.reader_id, r.reader_name, r.email, r.phone, r.created_at,
                   COUNT(ib.issue_id) AS books_issued
            FROM readers r
            LEFT JOIN issued_book ib ON ib.reader_id = r.reader_id AND ib.status = 'Issued'
            GROUP BY r.reader_id
            ORDER BY r.reader_id DESC
        ");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Full issue/return history
    if ($method === 'GET' && $action === 'records') {
        $stmt = $pdo->query("
            SELECT ib.issue_id, b.book_id, b.book_name, r.reader_name,
                   ib.issue_date, ib.return_date, ib.status
            FROM issued_book ib
            JOIN book b ON b.book_id = ib.book_id
            JOIN readers r ON r.reader_id = ib.reader_id
            ORDER BY ib.issue_id DESC
        ");
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    jsonResponse([
        'success' => false,
        'message' => 'Unknown action. Try login, readers, records, stats, books, add-book, add-reader, issue-book, return-book or delete-book.'
    ], 404);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log((string) $e);
    jsonResponse(['success' => false, 'message' => 'Server/database error. Check Render logs and database settings.'], 500);
}
