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

    header("Location: products.php?added=1");
    exit;
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
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel admina | Produkty</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-hero">
            <div>
                <p class="admin-kicker">Panel administracyjny</p>
                <h2>Produkty</h2>
                <p class="admin-description">Zarządzaj ofertą, dodawaj nowe pozycje i edytuj istniejące bez opuszczania panelu.</p>
            </div>
            <div class="admin-actions">
                <a href="../index.php" class="admin-btn admin-btn-ghost">Strona główna</a>
                <button type="button" class="admin-btn admin-btn-primary" id="openAddProduct">Dodaj produkt</button>
            </div>
        </section>

        <?php if (isset($_GET['added'])): ?>
            <p class="auth-success admin-notice">Produkt został dodany.</p>
        <?php endif; ?>

        <section class="admin-list">
            <?php while ($p = $products->fetch_assoc()): ?>
                <article class="admin-product-card">
                    <div class="admin-product-media">
                        <?php if (!empty($p['image']) && file_exists('../uploads/' . $p['image'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="admin-product-img">
                        <?php else: ?>
                            <img src="../uploads/default.png" alt="Brak zdjęcia" class="admin-product-img">
                        <?php endif; ?>
                    </div>

                    <div class="admin-product-body">
                        <div>
                            <p class="admin-product-category"><?= htmlspecialchars($p['category']) ?></p>
                            <h3><?= htmlspecialchars($p['name']) ?></h3>
                            <p class="admin-product-description"><?= htmlspecialchars($p['description'] ?? '') ?></p>
                        </div>

                        <div class="admin-product-meta">
                            <span><?= number_format((float)$p['price'], 2) ?> zł</span>
                            <span>Stan: <?= (int)$p['stock'] ?></span>
                        </div>

                        <div class="admin-product-actions">
                            <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="admin-btn admin-btn-small">Edytuj</a>
                            <a href="delete_product.php?id=<?= (int)$p['id'] ?>" class="admin-btn admin-btn-small admin-btn-danger" onclick="return confirm('Usunąć produkt?')">Usuń</a>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>
    </main>

    <div class="modal-overlay" id="addProductModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="addProductTitle">
            <button type="button" class="modal-close" id="closeAddProduct" aria-label="Zamknij">×</button>
            <h3 id="addProductTitle">Dodaj produkt</h3>

            <form method="post" enctype="multipart/form-data" class="admin-form">
                <label>Nazwa</label>
                <input type="text" name="name" required>

                <label>Cena</label>
                <input type="number" step="0.01" name="price" required>

                <label>Stan magazynowy</label>
                <input type="number" name="stock" required>

                <label>Kategoria</label>
                <select name="category_id" required>
                    <?php while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Opis</label>
                <textarea name="description" rows="4"></textarea>

                <label>Zdjęcie produktu</label>
                <input type="file" name="image" accept="image/*">

                <button type="submit">Dodaj produkt</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('addProductModal');
        const openBtn = document.getElementById('openAddProduct');
        const closeBtn = document.getElementById('closeAddProduct');

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
