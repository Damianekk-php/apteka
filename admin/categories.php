<?php
include "../session.php";
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

// DODAWANIE
if (isset($_POST['add'])) {
    $name = $_POST['name'];

    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
}

// USUWANIE
define('DEFAULT_CATEGORY_ID', 1); // domyślna kategoria

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Nie pozwól usunąć domyślnej kategorii
    if ($id == DEFAULT_CATEGORY_ID) {
        die("Nie można usunąć kategorii domyślnej!");
    }

    // 1️⃣ Przypisz wszystkie produkty do kategorii domyślnej
    $default_category_id = DEFAULT_CATEGORY_ID; // <- zmienna
    $stmt = $conn->prepare("UPDATE products SET category_id=? WHERE category_id=?");
    $stmt->bind_param("ii", $default_category_id, $id);
    $stmt->execute();

    // 2️⃣ Usuń kategorię
    $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: categories.php");
    exit;
}




// EDYCJA
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];

    $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
}

$categories = $conn->query("SELECT * FROM categories");
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel admina – Kategorie</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<h2>Panel admina – Kategorie</h2>
<a href="../index.php">← Powrót</a>

<hr>

<h3>Dodaj kategorię</h3>
<form method="post">
    <input type="text" name="name" required>
    <button name="add">Dodaj</button>
</form>

<hr>

<h3>Lista kategorii</h3>

<?php while ($c = $categories->fetch_assoc()): ?>
    <form method="post" style="margin-bottom:10px;">
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
        <input type="text" name="name" value="<?= $c['name'] ?>" required>
        <button name="edit">Zapisz</button>
        <a href="?delete=<?= $c['id'] ?>" onclick="return confirm('Usunąć kategorię?')">Usuń</a>
    </form>
<?php endwhile; ?>

</body>
</html>
