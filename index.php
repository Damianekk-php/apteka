<?php
include "config/db.php";
include "session.php";
// cart item count
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cartItem) {
        $cartCount += (int)($cartItem['qty'] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apteka internetowa</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <div class="logo-box">
    <img src="logo.png" alt="Logo" class="logo">
    <h1>Apteka Internetowa</h1>
    </div>

    <div class="header-actions">
        <a href="cart.php" class="cart-link">Koszyk (<?= $cartCount ?>)</a>
    </div>

    <?php if(isset($_SESSION['user'])): ?>
        <p>Zalogowany: <?= htmlspecialchars($_SESSION['user']['name']) ?> |
        <a href="auth/logout.php">Wyloguj</a>
        <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
            <a href="admin/products.php" class="admin-btn">Panel admina</a>
        <?php endif; ?>
        </p>
    <?php else: ?>
        <a href="auth/login.php">Zaloguj</a>
    <?php endif; ?>

</header>

<main id="maincontent">
    <section class="shop-hero">
        <div class="shop-hero-copy">
            <p class="shop-kicker">Sklep online</p>
            <h2>Sprawdzona apteka online dla zadowolonych klientów</h2>
            <p class="shop-description">Wybieraj produkty w przejrzystym układzie, sprawdzaj dostępność i korzystaj z aktualnych cen oraz promocji.</p>
            <div class="shop-pills">
                <span>Szybka wysyłka</span>
                <span>Bezpieczne zakupy</span>
                <span>Aktualna oferta</span>
            </div>
        </div>
        <div class="shop-hero-panel">
            <div>
                <span class="shop-hero-stat">24/7</span>
                <p>zamówienia przyjmowane przez całą dobę</p>
            </div>
            <div>
                <span class="shop-hero-stat">Dostawa</span>
                <p>szybka realizacja zamówień i wygodny odbiór</p>
            </div>
            <div>
                <span class="shop-hero-stat">Promocje</span>
                <p>oferty i rabaty widoczne od razu przy produktach</p>
            </div>
        </div>
    </section>

    <div class="products-grid">

    <?php
    $sql = "
    SELECT p.*, c.name AS category_name, d.discount_percent
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_discounts pd ON p.id = pd.product_id
    LEFT JOIN discounts d ON pd.discount_id = d.id AND d.is_active = 1
    WHERE p.is_active = 1
    ";

    $result = $conn->query($sql);

    while($row = $result->fetch_assoc()):
        $finalPrice = $row['price'];
        if($row['discount_percent']) {
            $finalPrice -= ($row['price'] * $row['discount_percent'] / 100);
        }
    ?>
       <article class="product">
            <div class="product-topline">
                <span class="product-category"><?= htmlspecialchars($row['category_name'] ?? 'Kategoria') ?></span>
                <?php if($row['discount_percent']): ?>
                    <span class="product-badge">-<?= (int)$row['discount_percent'] ?>%</span>
                <?php endif; ?>
            </div>

            <?php if ($row['image'] && file_exists('uploads/' . $row['image'])): ?>
                <img src="uploads/<?= $row['image'] ?>" alt="<?= $row['name'] ?>" class="product-img">
            <?php else: ?>
                <img src="uploads/default.png" alt="Brak zdjęcia" class="product-img">
            <?php endif; ?>

            <div class="product-body">
                <h3><?= htmlspecialchars($row['name']) ?></h3>
                <p class="product-description"><?= htmlspecialchars($row['description'] ?? '') ?></p>

                <div class="product-footer">
                    <div>
                        <?php if($row['discount_percent']): ?>
                            <p class="old-price"><?= number_format((float)$row['price'], 2) ?> zł</p>
                            <p class="price"><?= number_format($finalPrice, 2) ?> zł</p>
                        <?php else: ?>
                            <p class="price"><?= number_format((float)$row['price'], 2) ?> zł</p>
                        <?php endif; ?>
                    </div>
                    <span class="stock stock-pill"><?= (int)$row['stock'] ?> szt.</span>
                </div>

                <div class="product-actions">
                    <a href="product.php?id=<?= (int)$row['id'] ?>" class="product-btn product-btn-secondary">Szczegóły</a>

                    <form method="post" action="cart.php" class="inline-form" style="display:inline-block;margin:0 8px;">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <button type="submit" class="product-btn">Dodaj do koszyka</button>
                    </form>

                    <form method="post" action="cart.php" class="inline-form" style="display:inline-block;">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <input type="hidden" name="redirect" value="checkout">
                        <button type="submit" class="product-btn product-btn-primary">Kup teraz</button>
                    </form>
                </div>
            </div>
        </article>

    <?php endwhile; ?>
    </div>
</main>

</body>
</html>
