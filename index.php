<?php
include "config/db.php";
include "session.php";
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Apteka internetowa</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <div class="logo-box">
    <img src="logo.png" alt="Logo" class="logo">
    <h1>Apteka Internetowa</h1>
    </div>

    <?php if(isset($_SESSION['user'])): ?>
        <p>Zalogowany: <?= $_SESSION['user']['name'] ?> |
        <a href="auth/logout.php">Wyloguj</a></p>
    <?php else: ?>
        <a href="auth/login.php">Zaloguj</a>
    <?php endif; ?>
    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
    <a href="admin/products.php" class="admin-btn">Produkty</a>
    <a href="admin/categories.php" class="admin-btn">Kategorie</a>
<?php endif; ?>

</header>

<main>
    <h2>Produkty</h2>

    <?php
    $sql = "
    SELECT p.*, d.discount_percent
    FROM products p
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
       <div class="product">
    <h3><?= $row['name'] ?></h3>

    <?php if ($row['image'] && file_exists('uploads/' . $row['image'])): ?>
        <img src="uploads/<?= $row['image'] ?>" alt="<?= $row['name'] ?>" class="product-img">
    <?php else: ?>
        <img src="uploads/default.png" alt="Brak zdjęcia" class="product-img">
    <?php endif; ?>

    <p><?= $row['description'] ?></p>

    <?php if($row['discount_percent']): ?>
        <p class="old-price"><?= $row['price'] ?> zł</p>
        <p class="price"><?= number_format($finalPrice, 2) ?> zł (-<?= $row['discount_percent'] ?>%)</p>
    <?php else: ?>
        <p class="price"><?= $row['price'] ?> zł</p>
        <p class="stock"><?= $row['stock'] ?> Sztuk</p>
    <?php endif; ?>
</div>

    <?php endwhile; ?>
</main>

</body>
</html>
