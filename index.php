<?php
require_once __DIR__ . '/config.php';

$conn = get_db_connection();

$statusFilter = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'wishlist', 'watched'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

$query = "SELECT * FROM movies";
if ($statusFilter !== 'all') {
    $query .= " WHERE status = ?";
}
$query .= " ORDER BY status DESC, created_at DESC";

$queryError = null;
$movies = [];

if ($statusFilter !== 'all') {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        $queryError = 'Failed to prepare query: ' . $conn->error;
    } else {
        $stmt->bind_param('s', $statusFilter);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
        } else {
            $queryError = 'Failed to execute query: ' . $stmt->error;
        }
    }
} else {
    $result = $conn->query($query);
    if ($result === false) {
        $queryError = 'Failed to load movies: ' . $conn->error;
    }
}

if (isset($result) && $result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WatchBox</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>WatchBox</h1>
        <p>Track the films you want to watch and rate them after viewing.</p>
        <nav>
            <a href="index.php" class="<?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="index.php?status=wishlist" class="<?php echo $statusFilter === 'wishlist' ? 'active' : ''; ?>">Wishlist</a>
            <a href="index.php?status=watched" class="<?php echo $statusFilter === 'watched' ? 'active' : ''; ?>">Watched</a>
            <a class="cta" href="add_movie.php">+ Add Movie</a>
        </nav>
    </header>

    <main>
        <?php if ($queryError): ?>
            <p class="alert error">
                <?php
                echo htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8');
                echo ' – Please reload or run init_db.php to ensure the database is ready.';
                ?>
            </p>
        <?php elseif (count($movies) === 0): ?>
            <p class="empty-state">No movies found. Start by adding your first title.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Poster</th>
                            <th>Title</th>
                            <th>Release Year</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movies as $movie): ?>
                            <tr>
                                <td class="poster-cell">
                                    <img
                                        class="poster-thumb"
                                        src="<?php echo htmlspecialchars(poster_src($movie['poster_path'] ?? null), ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Poster for <?php echo htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </td>
                                <td><?php echo htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php
                                    echo $movie['release_year'] !== null
                                        ? htmlspecialchars($movie['release_year'], ENT_QUOTES, 'UTF-8')
                                        : '—';
                                    ?>
                                </td>
                                <td>
                                    <span class="status <?php echo htmlspecialchars($movie['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo ucfirst($movie['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    echo $movie['rating'] !== null
                                        ? htmlspecialchars($movie['rating'], ENT_QUOTES, 'UTF-8') . '/10'
                                        : '—';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $notesText = trim((string) $movie['notes']);
                                    echo $notesText !== ''
                                        ? nl2br(htmlspecialchars($notesText, ENT_QUOTES, 'UTF-8'))
                                        : '—';
                                    ?>
                                </td>
                                <td class="actions">
                                    <a href="edit_movie.php?id=<?php echo $movie['id']; ?>">Edit</a>
                                    <a class="danger" href="delete_movie.php?id=<?php echo $movie['id']; ?>">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
