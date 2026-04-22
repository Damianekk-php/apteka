<?php
include "../session.php";
include "../config/db.php";

if ($_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM products WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: products.php");
