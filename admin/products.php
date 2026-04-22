<?php
include "../session.php";
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

// folder na zdjęcia produktów
define('UPLOAD_DIR', '../uploads/');

// obsługa dodawania produktu
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];

    // obsługa uploadu obrazka
    $image_name = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowed)) {
            $image_name = time() . '_' . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $image_name);
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO products (name, price, stock, category_id, description, image)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sdiiss", $name, $price, $stock, $category_id, $description, $image_name);
    $stmt->execute();
}

// pobieranie produktów
$products = $conn->query("
    SELECT p.*, c.name AS category
    FROM products p
    JOIN categories c ON p.category_id = c.id
");

// pobieranie kategorii
$categories = $conn->query("SELECT * FROM categories");
?>

<h2>Panel admina – Produkty</h2>
<a href="../index.php" class="admin-btn">Strona Główna</a>

<h3>Dodaj produkt</h3>
<form method="post" enctype="multipart/form-data">
    <label>Nazwa</label><br>
    <input type="text" name="name" required><br>

    <label>Cena</label><br>
    <input type="number" step="0.01" name="price" required><br>

    <label>Stan magazynowy</label><br>
    <input type="number" name="stock" required><br>

    <label>Kategoria</label><br>
    <select name="category_id" required>
        <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
        <?php endwhile; ?>
    </select><br>

    <label>Opis</label><br>
    <textarea name="description"></textarea><br>

    <label>Zdjęcie produktu</label><br>
    <input type="file" name="image" accept="image/*"><br><br>

    <button>Dodaj produkt</button>
</form>

<hr>

<h3>Lista produktów</h3>

<?php while ($p = $products->fetch_assoc()): ?>
    <div class="product">
        <?php if ($p['image']): ?>
            <img src="../uploads/<?= $p['image'] ?>" alt="<?= $p['name'] ?>" class="product-img">
        <?php else: ?>
            <img src="../uploads/default.png" alt="Brak zdjęcia" class="product-img">
        <?php endif; ?>
        <p>
            <b><?= $p['name'] ?></b>
            (<?= $p['category'] ?>) |
            <?= $p['price'] ?> zł |
            sztuk: <?= $p['stock'] ?>
        </p>
        <p>
            <a href="edit_product.php?id=<?= $p['id'] ?>">Edytuj</a>
            <a href="delete_product.php?id=<?= $p['id'] ?>"
               onclick="return confirm('Usunąć produkt?')">Usuń</a>
        </p>
    </div>
<?php endwhile; ?>
