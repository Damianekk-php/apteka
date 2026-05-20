<?php
include "../session.php";
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

// folder na zdjęcia produktów
define('UPLOAD_DIR', '../uploads/');

// auto-expire coupons after end date
$conn->query("UPDATE discounts SET is_active = 0 WHERE is_active = 1 AND end_date < CURDATE()");

// obsługa formularzy admina
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form_type = $_POST['form_type'] ?? 'product';

    if ($form_type === 'discount') {
        $discount_action = $_POST['discount_action'] ?? 'create';
        $discount_id = (int)($_POST['discount_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $discount_percent = (int)($_POST['discount_percent'] ?? 0);
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($code !== '' && $discount_percent > 0 && $discount_percent <= 100) {
            if ($discount_action === 'update' && $discount_id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE discounts SET name = ?, discount_percent = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?"
                );
                $stmt->bind_param("sissii", $code, $discount_percent, $start_date, $end_date, $is_active, $discount_id);
                $stmt->execute();
                header("Location: products.php?discount_updated=1");
                exit;
            }

            $stmt = $conn->prepare(
                "INSERT INTO discounts (name, discount_percent, start_date, end_date, is_active)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sissi", $code, $discount_percent, $start_date, $end_date, $is_active);
            $stmt->execute();
            header("Location: products.php?discount_added=1");
            exit;
        }

        header("Location: products.php?discount_error=1");
        exit;
    }

    if ($form_type === 'product_toggle') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $set_active = isset($_POST['set_active']) ? 1 : 0;
        if ($product_id > 0) {
            $stmt = $conn->prepare("UPDATE products SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $set_active, $product_id);
            $stmt->execute();
            header("Location: products.php");
            exit;
        }
        header("Location: products.php?product_error=1");
        exit;
    }

    if ($form_type === 'discount_delete') {
        $discount_id = (int)($_POST['discount_id'] ?? 0);
        if ($discount_id > 0) {
            $stmt = $conn->prepare("DELETE FROM product_discounts WHERE discount_id = ?");
            $stmt->bind_param("i", $discount_id);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM discounts WHERE id = ?");
            $stmt->bind_param("i", $discount_id);
            $stmt->execute();

            header("Location: products.php?discount_deleted=1");
            exit;
        }

        header("Location: products.php?discount_error=1");
        exit;
    }

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

// pobieranie kodów rabatowych
$discounts = $conn->query("SELECT * FROM discounts ORDER BY id DESC");
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
    <main id="maincontent" class="admin-shell">
        <section class="admin-hero">
            <div>
                <p class="admin-kicker">Panel administracyjny</p>
                <h2>Produkty</h2>
                <p class="admin-description">Zarządzaj ofertą, dodawaj nowe pozycje i edytuj istniejące bez opuszczania panelu.</p>
            </div>
            <div class="admin-actions">
                <a href="../index.php" class="admin-btn admin-btn-ghost">Strona główna</a>
                <a href="stats.php" class="admin-btn admin-btn-ghost">Statystyki</a>
                <button type="button" class="admin-btn admin-btn-primary" id="openAddProduct">Dodaj produkt</button>
                <button type="button" class="admin-btn admin-btn-primary" id="openAddDiscount">Dodaj kod rabatowy</button>
            </div>
        </section>

        <?php if (isset($_GET['added'])): ?>
            <p class="auth-success admin-notice">Produkt został dodany.</p>
        <?php endif; ?>
        <?php if (isset($_GET['discount_added'])): ?>
            <p class="auth-success admin-notice">Kod rabatowy został dodany.</p>
        <?php endif; ?>
        <?php if (isset($_GET['discount_updated'])): ?>
            <p class="auth-success admin-notice">Kod rabatowy został zaktualizowany.</p>
        <?php endif; ?>
        <?php if (isset($_GET['discount_deleted'])): ?>
            <p class="auth-success admin-notice">Kod rabatowy został usunięty.</p>
        <?php endif; ?>
        <?php if (isset($_GET['discount_error'])): ?>
            <p class="auth-error admin-notice">Nie udało się dodać kodu rabatowego. Sprawdź pola formularza.</p>
        <?php endif; ?>

        <section class="admin-list">
            <?php while ($p = $products->fetch_assoc()): ?>
                <?php $isInactive = ((int)$p['is_active'] !== 1); ?>
                <article class="admin-product-card <?= $isInactive ? 'is-inactive' : '' ?>">
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
                            <?php if ($isInactive): ?>
                                <form method="post" action="products.php" style="display:inline-block; margin:0;">
                                    <input type="hidden" name="form_type" value="product_toggle">
                                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="set_active" value="1">
                                    <button type="submit" class="admin-btn admin-btn-small admin-btn-ghost">Aktywuj</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="products.php" style="display:inline-block; margin:0;">
                                    <input type="hidden" name="form_type" value="product_toggle">
                                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-small admin-btn-ghost" onclick="return confirm('Dezaktywować produkt?')">Dezaktywuj</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>

        <section class="admin-list admin-discount-list">
            <div class="admin-section-head">
                <h3>Kody rabatowe</h3>
                <p>Dodane kupony działają w koszyku po wpisaniu poprawnego kodu.</p>
            </div>
            <?php while ($d = $discounts->fetch_assoc()): ?>
                <?php $isInactive = ((int)$d['is_active'] !== 1) || ($d['end_date'] < date('Y-m-d')); ?>
                <article class="admin-discount-card <?= $isInactive ? 'is-inactive' : '' ?>">
                    <div class="admin-discount-body">
                        <div>
                            <p class="admin-product-category">Kod</p>
                            <h3><?= htmlspecialchars($d['name']) ?></h3>
                            <p class="admin-product-description">Aktywny od <?= htmlspecialchars($d['start_date']) ?> do <?= htmlspecialchars($d['end_date']) ?></p>
                        </div>

                        <div class="admin-product-meta">
                            <span><?= (int)$d['discount_percent'] ?>% zniżki</span>
                            <span><?= $isInactive ? 'Nieaktywny' : 'Aktywny' ?></span>
                        </div>

                        <div class="admin-product-actions">
                            <button
                                type="button"
                                class="admin-btn admin-btn-small js-edit-discount"
                                data-id="<?= (int)$d['id'] ?>"
                                data-code="<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>"
                                data-percent="<?= (int)$d['discount_percent'] ?>"
                                data-start="<?= htmlspecialchars($d['start_date'], ENT_QUOTES) ?>"
                                data-end="<?= htmlspecialchars($d['end_date'], ENT_QUOTES) ?>"
                                data-active="<?= (int)$d['is_active'] ?>"
                            >Edytuj</button>

                            <form method="post" action="products.php" style="display:inline-block; margin:0;">
                                <input type="hidden" name="form_type" value="discount_delete">
                                <input type="hidden" name="discount_id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="admin-btn admin-btn-small admin-btn-danger" onclick="return confirm('Usunąć kod rabatowy?')">Usuń</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>
    </main>

    <div class="modal-overlay" id="addProductModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="addProductTitle" tabindex="-1">
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

    <div class="modal-overlay" id="addDiscountModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="addDiscountTitle" tabindex="-1">
            <button type="button" class="modal-close" id="closeAddDiscount" aria-label="Zamknij">×</button>
            <h3 id="addDiscountTitle">Dodaj kod rabatowy</h3>

            <form method="post" class="admin-form" id="discountForm">
                <input type="hidden" name="form_type" value="discount">
                <input type="hidden" name="discount_action" id="discountAction" value="create">
                <input type="hidden" name="discount_id" id="discountId" value="">

                <label>Kod rabatowy</label>
                <input type="text" name="code" id="discountCode" required>

                <label>Procent zniżki</label>
                <input type="number" name="discount_percent" id="discountPercent" min="1" max="100" required>

                <label>Data startu</label>
                <input type="date" name="start_date" id="discountStartDate" value="<?= date('Y-m-d') ?>" required>

                <label>Data końca</label>
                <input type="date" name="end_date" id="discountEndDate" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>

                <label style="display:flex; align-items:center; gap:10px; margin-top:12px;">
                    <input type="checkbox" name="is_active" id="discountIsActive" checked>
                    Aktywny
                </label>

                <button type="submit" id="discountSubmitBtn">Dodaj kod</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('addProductModal');
        const openBtn = document.getElementById('openAddProduct');
        const closeBtn = document.getElementById('closeAddProduct');
        const discountModal = document.getElementById('addDiscountModal');
        const openDiscountBtn = document.getElementById('openAddDiscount');
        const closeDiscountBtn = document.getElementById('closeAddDiscount');
        const discountForm = document.getElementById('discountForm');
        const discountAction = document.getElementById('discountAction');
        const discountId = document.getElementById('discountId');
        const discountCode = document.getElementById('discountCode');
        const discountPercent = document.getElementById('discountPercent');
        const discountStartDate = document.getElementById('discountStartDate');
        const discountEndDate = document.getElementById('discountEndDate');
        const discountIsActive = document.getElementById('discountIsActive');
        const discountSubmitBtn = document.getElementById('discountSubmitBtn');
        const discountTitle = document.getElementById('addDiscountTitle');
        const editButtons = document.querySelectorAll('.js-edit-discount');

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
        openDiscountBtn.addEventListener('click', () => {
            discountAction.value = 'create';
            discountId.value = '';
            discountForm.reset();
            discountIsActive.checked = true;
            discountTitle.textContent = 'Dodaj kod rabatowy';
            discountSubmitBtn.textContent = 'Dodaj kod';
            discountModal.classList.add('is-open');
            discountModal.setAttribute('aria-hidden', 'false');
            // focus dialog for keyboard users
            const discountDialog = discountModal.querySelector('.modal-card');
            if (discountDialog) discountDialog.focus();
        });
        closeDiscountBtn.addEventListener('click', () => {
            discountModal.classList.remove('is-open');
            discountModal.setAttribute('aria-hidden', 'true');
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
        discountModal.addEventListener('click', (event) => {
            if (event.target === discountModal) {
                discountModal.classList.remove('is-open');
                discountModal.setAttribute('aria-hidden', 'true');
            }
        });

        editButtons.forEach((button) => {
            button.addEventListener('click', () => {
                discountAction.value = 'update';
                discountId.value = button.dataset.id || '';
                discountCode.value = button.dataset.code || '';
                discountPercent.value = button.dataset.percent || '';
                discountStartDate.value = button.dataset.start || '<?= date('Y-m-d') ?>';
                discountEndDate.value = button.dataset.end || '<?= date('Y-m-d', strtotime('+30 days')) ?>';
                discountIsActive.checked = (button.dataset.active || '1') === '1';
                discountTitle.textContent = 'Edytuj kod rabatowy';
                discountSubmitBtn.textContent = 'Zapisz zmiany';
                discountModal.classList.add('is-open');
                discountModal.setAttribute('aria-hidden', 'false');
                const discountDialog = discountModal.querySelector('.modal-card');
                if (discountDialog) discountDialog.focus();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
                discountModal.classList.remove('is-open');
                discountModal.setAttribute('aria-hidden', 'true');
            }
        });
    </script>
</body>
</html>
