<?php
include "../session.php";
include "../config/db.php";

if ($_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

$id = $_GET['id'];
$product = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
$categories = $conn->query("SELECT * FROM categories");

define('UPLOAD_DIR', '../uploads/');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];

    // Obsługa nowego zdjęcia
    $image_name = $product['image']; // zachowaj stare jeśli brak nowego
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowed)) {
            $image_name = time() . '_' . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $image_name);

            // opcjonalnie usuń stare zdjęcie
            if ($product['image'] && file_exists(UPLOAD_DIR . $product['image'])) {
                unlink(UPLOAD_DIR . $product['image']);
            }
        }
    }

    $stmt = $conn->prepare(
        "UPDATE products 
         SET name=?, price=?, stock=?, category_id=?, description=?, image=?
         WHERE id=?"
    );
    $stmt->bind_param("sdiissi", $name, $price, $stock, $category_id, $description, $image_name, $id);
    $stmt->execute();

    header("Location: products.php");
    exit;
}
?>

<h2>Edytuj produkt</h2>

<form method="post" enctype="multipart/form-data">
    <label>Nazwa</label><br>
    <input type="text" name="name" value="<?= $product['name'] ?>" required><br>

    <label>Cena</label><br>
    <input type="number" step="0.01" name="price" value="<?= $product['price'] ?>" required><br>

    <label>Stan magazynowy</label><br>
    <input type="number" name="stock" value="<?= $product['stock'] ?>" required><br>

    <label>Kategoria</label><br>
    <select name="category_id">
        <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id']==$product['category_id']?'selected':'' ?>>
                <?= $c['name'] ?>
            </option>
        <?php endwhile; ?>
    </select><br>

    <label>Opis</label><br>
    <textarea name="description"><?= $product['description'] ?></textarea><br>

    <label>Aktualne zdjęcie:</label><br>
    <?php if ($product['image'] && file_exists(UPLOAD_DIR . $product['image'])): ?>
        <img src="../uploads/<?= $product['image'] ?>" alt="<?= $product['name'] ?>" class="product-img"><br>
    <?php else: ?>
        <img src="../uploads/default.png" alt="Brak zdjęcia" class="product-img"><br>
    <?php endif; ?>

    <label>Wgraj nowe zdjęcie (opcjonalnie)</label><br>
    <input type="file" name="image" accept="image/*"><br><br>

    <button>Zapisz zmiany</button>
</form>
