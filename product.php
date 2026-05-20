<?php
include "config/db.php";
include "session.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) {
    header('Location: index.php');
    exit;
}

// fetch product with category and active discount
$sql = "
SELECT p.*, c.name AS category_name, d.discount_percent
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN product_discounts pd ON p.id = pd.product_id
LEFT JOIN discounts d ON pd.discount_id = d.id AND d.is_active = 1
WHERE p.id = ?
LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
if(!$product) {
    header('Location: index.php');
    exit;
}

$finalPrice = (float)$product['price'];
if($product['discount_percent']) {
    $finalPrice -= ($product['price'] * $product['discount_percent'] / 100);
}

$purchaseMessage = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!isset($_SESSION['user'])) {
        header('Location: auth/login.php');
        exit;
    }

    // Minimal simulated purchase flow (placeholder)
    $purchaseMessage = 'Dziękujemy — zakup został zrealizowany (symulacja).';
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> — Apteka</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
    <div class="logo-box">
        <img src="logo.png" alt="Logo" class="logo">
        <h1>Apteka Internetowa</h1>
    </div>

    <?php if(isset($_SESSION['user'])): ?>
        <p>Zalogowany: <?= htmlspecialchars($_SESSION['user']['name']) ?> |
        <a href="auth/logout.php">Wyloguj</a>
        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="admin/products.php" class="admin-btn">Panel admina</a>
        <?php endif; ?>
        </p>
    <?php else: ?>
        <a href="auth/login.php">Zaloguj</a>
    <?php endif; ?>

</header>

<main id="maincontent">
    <section class="product-detail-shell">
        <div class="product-detail-card">
            <div class="product-detail-media">
                <?php if ($product['image'] && file_exists('uploads/' . $product['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <?php else: ?>
                    <img src="uploads/default.png" alt="Brak zdjęcia">
                <?php endif; ?>
            </div>

            <div class="product-detail-info">
                <p class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Kategoria') ?></p>
                <h2><?= htmlspecialchars($product['name']) ?></h2>
                <p class="product-description"><?= nl2br(htmlspecialchars($product['description'] ?? 'Brak opisu')) ?></p>

                <div class="product-detail-meta">
                    <?php if($product['discount_percent']): ?>
                        <p class="old-price"><?= number_format((float)$product['price'], 2) ?> zł</p>
                        <p class="price"><?= number_format($finalPrice, 2) ?> zł</p>
                        <span class="product-badge">-<?= (int)$product['discount_percent'] ?>%</span>
                    <?php else: ?>
                        <p class="price"><?= number_format((float)$product['price'], 2) ?> zł</p>
                    <?php endif; ?>

                    <p class="stock">Dostępne: <strong><?= (int)$product['stock'] ?></strong> szt.</p>
                </div>

                <?php if($purchaseMessage): ?>
                    <div class="notice success"><?= htmlspecialchars($purchaseMessage) ?></div>
                <?php endif; ?>

                <div class="product-actions">
                    <form method="post" action="cart.php" class="inline-form" style="display:inline-block;margin-right:8px;">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <button type="submit" class="product-btn">Dodaj do koszyka</button>
                    </form>

                    <form method="post" action="cart.php" class="inline-form" style="display:inline-block;">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <input type="hidden" name="redirect" value="checkout">
                        <button type="submit" class="product-btn product-btn-primary">Kup teraz</button>
                    </form>

                    <a href="index.php" class="product-btn product-btn-secondary">Powrót do sklepu</a>
                </div>
            </div>
        </div>
    </section>
</main>

</body>
</html>
