<?php
require_once __DIR__ . '/config.php';

$title = '';
$releaseYear = '';
$status = 'wishlist';
$rating = '';
$notes = '';
$posterPath = null;
$successMessage = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $releaseYear = trim($_POST['release_year'] ?? '');
    $status = $_POST['status'] ?? 'wishlist';
    $rating = trim($_POST['rating'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $posterPath = null;

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if ($releaseYear !== '' && !ctype_digit($releaseYear)) {
        $errors[] = 'Release year must be a valid number.';
    }

    $allowedStatuses = ['wishlist', 'watched'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'wishlist';
    }

    if ($rating !== '') {
        if (!ctype_digit($rating) || (int) $rating < 1 || (int) $rating > 10) {
            $errors[] = 'Rating must be a number between 1 and 10.';
        }
    }

    if (!$errors) {
        if (isset($_FILES['poster'])) {
            $posterPath = save_uploaded_poster($_FILES['poster'], $errors);
        }
    }

    if (!$errors) {
        $conn = get_db_connection();
        $stmt = $conn->prepare(
            'INSERT INTO movies (title, poster_path, release_year, status, rating, notes) VALUES (?, ?, ?, ?, ?, ?)'
        );

        $releaseYearValue = $releaseYear !== '' ? (int) $releaseYear : null;
        $ratingValue = $rating !== '' ? (int) $rating : null;

        $stmt->bind_param(
            'ssisis',
            $title,
            $posterPath,
            $releaseYearValue,
            $status,
            $ratingValue,
            $notes
        );

        if ($stmt->execute()) {
            $successMessage = 'Movie added successfully.';
            $title = $releaseYear = $rating = $notes = '';
            $status = 'wishlist';
            $posterPath = null;
        } else {
            $errors[] = 'Failed to add movie. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Movie</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Add Movie</h1>
        <nav>
            <a href="index.php">← Back to list</a>
        </nav>
    </header>
    <main>
        <?php if ($successMessage): ?>
            <p class="alert success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="card" enctype="multipart/form-data">
            <label>
                Movie Title *
                <input type="text" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>
            <label>
                Release Year
                <input type="number" name="release_year" min="1900" max="2100" value="<?php echo htmlspecialchars($releaseYear, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="wishlist" <?php echo $status === 'wishlist' ? 'selected' : ''; ?>>Wishlist</option>
                    <option value="watched" <?php echo $status === 'watched' ? 'selected' : ''; ?>>Watched</option>
                </select>
            </label>
            <label>
                Rating (1-10)
                <input type="number" name="rating" min="1" max="10" value="<?php echo htmlspecialchars($rating, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                Notes
                <textarea name="notes" rows="4"><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>
            <label>
                Poster Image
                <input type="file" name="poster" accept="image/jpeg,image/png,image/gif,image/webp">
            </label>
            <button type="submit">Save Movie</button>
        </form>
    </main>
</body>
</html>
