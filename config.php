<?php
// Basic configuration and helper for connecting to MySQL.
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'movie_db';

function get_db_connection(): mysqli
{
    global $db_host, $db_user, $db_pass, $db_name;

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_errno === 1049) {
        ensure_database_exists();
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    }

    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    // Ensures utf-8 encoding for Arabic or special characters.
    $conn->set_charset('utf8mb4');
    ensure_movies_table($conn);

    return $conn;
}

function ensure_database_exists(): void
{
    global $db_host, $db_user, $db_pass, $db_name;

    $rootConnection = new mysqli($db_host, $db_user, $db_pass);
    if ($rootConnection->connect_error) {
        die('Unable to connect to MySQL server: ' . $rootConnection->connect_error);
    }

    $dbSql = sprintf(
        "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        $db_name
    );

    if (!$rootConnection->query($dbSql)) {
        die('Unable to create database: ' . $rootConnection->error);
    }

    $rootConnection->close();
}

function ensure_movies_table(mysqli $conn): void
{
    $result = $conn->query("SHOW TABLES LIKE 'movies'");
    if ($result && $result->num_rows > 0) {
        ensure_poster_column($conn);

        $test = $conn->query('SELECT 1 FROM movies LIMIT 1');
        if ($test === false && $conn->errno === 1932) {
            $conn->query('DROP TABLE IF EXISTS movies');
            create_movies_table($conn);
        }
        return;
    }

    create_movies_table($conn);
}

function create_movies_table(mysqli $conn): void
{
    $tableSql = <<<SQL
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    poster_path VARCHAR(255) DEFAULT NULL,
    release_year INT DEFAULT NULL,
    status ENUM('wishlist','watched') NOT NULL DEFAULT 'wishlist',
    rating TINYINT UNSIGNED DEFAULT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

    if (!$conn->query($tableSql)) {
        if ($conn->errno === 1813) {
            cleanup_orphaned_tablespace($conn);
            if (!$conn->query($tableSql)) {
                die('Unable to create movies table: ' . $conn->error);
            }
        } else {
            die('Unable to create movies table: ' . $conn->error);
        }
    }

    $existing = $conn->query('SELECT COUNT(*) AS total FROM movies');
    if ($existing && (int) $existing->fetch_assoc()['total'] === 0) {
        $conn->query("INSERT INTO movies (title, release_year, status, rating, notes) VALUES
            ('Inception', 2010, 'watched', 9, 'Re-watch soon.'),
            ('Dune Part Two', 2024, 'wishlist', NULL, 'IMAX preferred.')
        ");
    }
}

function cleanup_orphaned_tablespace(mysqli $conn): void
{
    $conn->query('DROP TABLE IF EXISTS movies');

    $result = $conn->query("SHOW VARIABLES LIKE 'datadir'");
    if (!$result) {
        return;
    }

    $row = $result->fetch_assoc();
    if (!$row || !isset($row['Value'])) {
        return;
    }

    $baseDir = rtrim($row['Value'], "/\\");
    $dbDir = $baseDir . DIRECTORY_SEPARATOR . $GLOBALS['db_name'];
    $files = [
        $dbDir . DIRECTORY_SEPARATOR . 'movies.ibd',
        $dbDir . DIRECTORY_SEPARATOR . 'movies.frm'
    ];

    foreach ($files as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

function ensure_poster_column(mysqli $conn): void
{
    $columnExists = $conn->query("SHOW COLUMNS FROM movies LIKE 'poster_path'");
    if ($columnExists && $columnExists->num_rows === 0) {
        $conn->query("ALTER TABLE movies ADD COLUMN poster_path VARCHAR(255) DEFAULT NULL AFTER title");
    }
}

function save_uploaded_poster(array $file, array &$errors, ?string $existingPath = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Poster upload failed. Please try again.';
        return $existingPath;
    }

    if ($file['size'] > 4 * 1024 * 1024) {
        $errors[] = 'Poster is too large. Please upload an image under 4MB.';
        return $existingPath;
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!isset($allowedMime[$mime])) {
        $errors[] = 'Poster must be an image (jpg, png, gif, or webp).';
        return $existingPath;
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $errors[] = 'Unable to create the uploads folder.';
        return $existingPath;
    }

    try {
        $filename = 'poster_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMime[$mime];
    } catch (Throwable $e) {
        $errors[] = 'Unable to generate a name for the poster file.';
        return $existingPath;
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    // Debug: تأكد أن مجلد الرفع قابل للكتابة
    if (!is_writable($uploadDir)) {
        $errors[] = 'Uploads directory is not writable: ' . $uploadDir;

        // نسجل معلومات أكثر في ملف لوج داخل نفس المشروع
        $debugMessage = sprintf(
            "[%s] Upload dir not writable. Path: %s, perms: %s\n",
            date('Y-m-d H:i:s'),
            $uploadDir,
            substr(sprintf('%o', fileperms($uploadDir)), -4)
        );
        @file_put_contents(__DIR__ . '/upload_debug.log', $debugMessage, FILE_APPEND);

        return $existingPath;
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save the uploaded poster.';

        // نكتب تفاصيل أكثر في ملف upload_debug.log للمساعدة في التشخيص
        $lastError = error_get_last();
        $debugMessage = sprintf(
            "[%s] move_uploaded_file failed. tmp_name: %s, dest: %s, is_uploaded_file: %s, last_error: %s\n",
            date('Y-m-d H:i:s'),
            $file['tmp_name'] ?? 'N/A',
            $destination,
            is_uploaded_file($file['tmp_name'] ?? '') ? 'true' : 'false',
            $lastError ? ($lastError['message'] ?? 'no message') : 'no last error'
        );
        @file_put_contents(__DIR__ . '/upload_debug.log', $debugMessage, FILE_APPEND);

        return $existingPath;
    }

    delete_poster_file($existingPath);

    return 'uploads/' . $filename;
}

function delete_poster_file(?string $posterPath): void
{
    $trimmedPath = is_string($posterPath) ? trim($posterPath) : '';
    if ($trimmedPath === '' || strpos($trimmedPath, 'uploads/') !== 0) {
        return;
    }

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $trimmedPath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function poster_src(?string $posterPath): string
{
    $trimmedPath = is_string($posterPath) ? trim($posterPath) : '';
    if ($trimmedPath !== '' && strpos($trimmedPath, 'uploads/') === 0) {
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $trimmedPath;
        if (is_file($fullPath)) {
            return $trimmedPath;
        }
    }

    $placeholder = '<svg width="220" height="330" viewBox="0 0 220 330" xmlns="http://www.w3.org/2000/svg">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop stop-color="%23e5edff" offset="0%"/><stop stop-color="%23d4e2ff" offset="100%"/></linearGradient></defs>'
        . '<rect fill="url(%23g)" x="0" y="0" width="220" height="330" rx="18"/>'
        . '<text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" fill="%23536a89" '
        . 'font-family="Segoe UI, Arial" font-size="18" font-weight="600">No Poster</text>'
        . '</svg>';

    return 'data:image/svg+xml,' . rawurlencode($placeholder);
}
