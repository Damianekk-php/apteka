<?php
include "../session.php";
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

$id = (int)($_GET['id'] ?? 0);
$product = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
$error = null;

if (!$product) {
    die("Nie znaleziono produktu");
}

$categories = $conn->query("SELECT * FROM categories");

define('UPLOAD_DIR', '../uploads/');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $price <= 0 || $category_id <= 0) {
        $error = "Uzupełnij poprawnie wymagane pola";
    } else {
        // Obsługa nowego zdjęcia
        $image_name = $product['image']; // zachowaj stare jeśli brak nowego
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
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
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edycja produktu | Panel admina</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-hero">
            <div>
                <p class="admin-kicker">Panel administracyjny</p>
                <h2>Edytuj produkt</h2>
                <p class="admin-description">Zmien dane produktu i od razu wróc do listy produktów po zapisaniu zmian.</p>
            </div>
            <div class="admin-actions">
                <a href="products.php" class="admin-btn admin-btn-ghost">Wróć do listy</a>
                <a href="../index.php" class="admin-btn admin-btn-ghost">Strona główna</a>
            </div>
        </section>

        <?php if (!empty($error)): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <section class="admin-edit-grid">
            <div class="admin-edit-preview">
                <p class="admin-kicker">Aktualny podgląd</p>
                <div class="admin-product-media admin-product-media-large">
                    <?php if ($product['image'] && file_exists(UPLOAD_DIR . $product['image'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="admin-product-img">
                    <?php else: ?>
                        <img src="../uploads/default.png" alt="Brak zdjęcia" class="admin-product-img">
                    <?php endif; ?>
                </div>
                <h3><?= htmlspecialchars($product['name']) ?></h3>
                <p class="admin-product-description"><?= htmlspecialchars($product['description'] ?? '') ?></p>
            </div>

            <div class="admin-edit-form-wrap">
                <form method="post" enctype="multipart/form-data" class="admin-form">
                    <label>Nazwa</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>

                    <label>Cena</label>
                    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($product['price']) ?>" required>

                    <label>Stan magazynowy</label>
                    <input type="number" name="stock" value="<?= (int)$product['stock'] ?>" required>

                    <label>Kategoria</label>
                    <select name="category_id" required>
                        <?php while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === (int)$product['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <label>Opis</label>
                    <textarea name="description" rows="5"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>

                    <label>Wgraj nowe zdjęcie (opcjonalnie)</label>
                    <input type="file" name="image" accept="image/*">

                    <button type="submit">Zapisz zmiany</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
