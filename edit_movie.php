<?php
require_once __DIR__ . '/config.php';

$conn = get_db_connection();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid movie ID.');
}

$stmt = $conn->prepare('SELECT * FROM movies WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) {
    die('Movie not found.');
}

$title = $movie['title'];
$releaseYear = $movie['release_year'];
$status = $movie['status'];
$rating = $movie['rating'];
$notes = $movie['notes'];
$posterPath = $movie['poster_path'];
$successMessage = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $releaseYear = trim($_POST['release_year'] ?? '');
    $status = $_POST['status'] ?? 'wishlist';
    $rating = trim($_POST['rating'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $posterPath = $movie['poster_path'];

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
            $posterPath = save_uploaded_poster($_FILES['poster'], $errors, $movie['poster_path']);
        }
    }

    if (!$errors) {
        $update = $conn->prepare(
            'UPDATE movies SET title = ?, poster_path = ?, release_year = ?, status = ?, rating = ?, notes = ? WHERE id = ?'
        );
        $releaseYearValue = $releaseYear !== '' ? (int) $releaseYear : null;
        $ratingValue = $rating !== '' ? (int) $rating : null;

        $update->bind_param(
            'ssisisi',
            $title,
            $posterPath,
            $releaseYearValue,
            $status,
            $ratingValue,
            $notes,
            $id
        );

        if ($update->execute()) {
            $successMessage = 'Movie updated successfully.';
        } else {
            $errors[] = 'Failed to update movie.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Movie</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Edit Movie</h1>
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
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <label>
                Movie Title *
                <input type="text" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>
            <label>
                Release Year
                <input type="number" name="release_year" min="1900" max="2100" value="<?php echo htmlspecialchars((string) $releaseYear, ENT_QUOTES, 'UTF-8'); ?>">
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
                <input type="number" name="rating" min="1" max="10" value="<?php echo htmlspecialchars((string) $rating, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
                Notes
                <textarea name="notes" rows="4"><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>
            <div class="poster-field">
                <span class="poster-label">Current Poster</span>
                <div class="poster-preview">
                    <img class="poster-thumb" src="<?php echo htmlspecialchars(poster_src($posterPath), ENT_QUOTES, 'UTF-8'); ?>" alt="Poster preview">
                </div>
            </div>
            <label>
                Update Poster
                <input type="file" name="poster" accept="image/jpeg,image/png,image/gif,image/webp">
            </label>
            <button type="submit">Update Movie</button>
        </form>
    </main>
</body>
</html>
