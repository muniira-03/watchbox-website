<?php
require_once __DIR__ . '/config.php';

$output = [];

try {
    $rootConnection = new mysqli($db_host, $db_user, $db_pass);
    if ($rootConnection->connect_error) {
        throw new RuntimeException('MySQL connection failed: ' . $rootConnection->connect_error);
    }

    $output[] = 'Connected to MySQL server.';

    $dbSql = sprintf(
        "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        $db_name
    );

    if (!$rootConnection->query($dbSql)) {
        throw new RuntimeException('Failed to create database: ' . $rootConnection->error);
    }

    $output[] = "Database `$db_name` is ready.";

    if (!$rootConnection->select_db($db_name)) {
        throw new RuntimeException('Unable to select database: ' . $rootConnection->error);
    }

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

    if (!$rootConnection->query($tableSql)) {
        throw new RuntimeException('Failed to create table: ' . $rootConnection->error);
    }

    $output[] = 'Table `movies` is ready.';
    ensure_poster_column($rootConnection);
    $output[] = 'Poster column is ready.';

    $countResult = $rootConnection->query('SELECT COUNT(*) AS total FROM movies');
    $row = $countResult->fetch_assoc();
    if ((int) $row['total'] === 0) {
        $sampleSql = "INSERT INTO movies (title, release_year, status, rating, notes) VALUES 
            ('Inception', 2010, 'watched', 9, 'Re-watch soon.'),
            ('Dune Part Two', 2024, 'wishlist', NULL, 'IMAX preferred.')";
        $rootConnection->query($sampleSql);
        $output[] = 'Inserted two sample movies.';
    } else {
        $output[] = 'Existing movie data detected – sample data skipped.';
    }
} catch (Throwable $e) {
    $output[] = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Initialize WatchBox DB</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>WatchBox DB Setup</h1>
        <ul>
            <?php foreach ($output as $line): ?>
                <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
        <p><a href="index.php">Go to movie list</a></p>
    </div>
</body>
</html>
