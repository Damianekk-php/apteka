<?php
include "../session.php";
include "../config/db.php";

if ($_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

$id = $_GET['id'];

// soft-delete: mark product as inactive instead of deleting to preserve sales history
$stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: products.php");
