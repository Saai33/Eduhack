<?php
require __DIR__ . '/../includes/db.php';

$sql = "ALTER TABLE forum_posts ADD COLUMN likes INT NOT NULL DEFAULT 0";
if (mysqli_query($conn, $sql)) {
    echo "OK: 'likes' column added.\n";
} else {
    echo "ERROR: " . mysqli_error($conn) . "\n";
}
