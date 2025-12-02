<?php
require_once __DIR__ . '/config.php';

$conn = get_db_connection();
$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    die('Invalid movie ID.');
}

$stmt = $conn->prepare('SELECT title, poster_path FROM movies WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) {
    die('Movie not found.');
}

$deleted = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteStmt = $conn->prepare('DELETE FROM movies WHERE id = ?');
    $deleteStmt->bind_param('i', $id);
    if ($deleteStmt->execute()) {
        delete_poster_file($movie['poster_path'] ?? null);
        $deleted = true;
    } else {
        $error = 'Unable to delete movie. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Movie</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Delete Movie</h1>
        <nav>
            <a href="index.php">← Back to list</a>
        </nav>
    </header>
    <main>
        <?php if ($deleted): ?>
            <p class="alert success">
                "<?php echo htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?>" was removed.
                <a href="index.php">Return to the list</a>
            </p>
        <?php elseif ($error): ?>
            <p class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
            <div class="card">
                <p>Are you sure you want to delete "<?php echo htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?>"?</p>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="danger">Yes, delete it</button>
                    <a class="button" href="index.php">Cancel</a>
                </form>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
