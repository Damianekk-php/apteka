<?php
include "config/db.php";
include "session.php";

// require login
if (!isset($_SESSION['user'])) {
    header('Location: auth/login.php');
    exit;
}

// require non-empty cart
if (empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

// auto-expire coupons after end date
$conn->query("UPDATE discounts SET is_active = 0 WHERE is_active = 1 AND end_date < CURDATE()");

// prepare products and total from cart
$products = [];
$ids = array_keys($_SESSION['cart']);
$total = 0.0;
$couponPercent = 0;
$couponCode = '';
$couponDiscount = 0.0;

if (!empty($_SESSION['cart_coupon']['code'])) {
    $stmt = $conn->prepare("SELECT id, name, discount_percent, start_date, end_date, is_active FROM discounts WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $_SESSION['cart_coupon']['code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $coupon = $result->fetch_assoc();
    $today = date('Y-m-d');
    if ($coupon && (int)$coupon['is_active'] === 1 && $today >= $coupon['start_date'] && $today <= $coupon['end_date']) {
        $couponPercent = (int)$coupon['discount_percent'];
        $couponCode = $coupon['name'];
        $_SESSION['cart_coupon'] = [
            'id' => (int)$coupon['id'],
            'code' => $couponCode,
            'percent' => $couponPercent,
        ];
    } else {
        unset($_SESSION['cart_coupon']);
    }
}

if (!empty($ids)) {
    $ids = array_map('intval', $ids);
    $in = implode(',', $ids);
    $sql = "SELECT p.*, d.discount_percent
            FROM products p
            LEFT JOIN product_discounts pd ON p.id = pd.product_id
            LEFT JOIN discounts d ON pd.discount_id = d.id AND d.is_active = 1
            WHERE p.id IN ($in)";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $products[$row['id']] = $row;
    }

    foreach ($_SESSION['cart'] as $id => $info) {
        if (!isset($products[$id])) continue;
        $p = $products[$id];
        $price = (float)$p['price'];
        if ($p['discount_percent']) {
            $price -= ($price * $p['discount_percent'] / 100);
        }
        $qty = (int)$info['qty'];
        $total += $price * $qty;
    }

    if ($couponPercent > 0) {
        $couponDiscount = round($total * ($couponPercent / 100), 2);
        $total = max(0, $total - $couponDiscount);
    }
}

$errors = [];
$success = '';

$name = $_POST['name'] ?? '';
$address = $_POST['address'] ?? '';
$city = $_POST['city'] ?? '';
$postal = $_POST['postal'] ?? '';
$phone = $_POST['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($name) === '') $errors[] = 'Podaj imię i nazwisko.';
    if (trim($address) === '') $errors[] = 'Podaj adres.';
    if (trim($city) === '') $errors[] = 'Podaj miasto.';
    if (trim($postal) === '') $errors[] = 'Podaj kod pocztowy.';
    if (trim($phone) === '') $errors[] = 'Podaj numer telefonu.';

    if (empty($errors)) {
        // validate stock availability before persisting
        $insufficient = [];
        foreach ($_SESSION['cart'] as $cid => $info) {
            $pid = (int)$cid;
            $qty = (int)$info['qty'];
            if (!isset($products[$pid])) {
                $insufficient[] = 'Produkt o ID ' . $pid . ' nie istnieje.';
                continue;
            }
            $available = isset($products[$pid]['stock']) ? (int)$products[$pid]['stock'] : 0;
            if ($qty > $available) {
                $insufficient[] = htmlspecialchars($products[$pid]['name']) . ' — dostępne: ' . $available . ', żądane: ' . $qty;
            }
        }

        if (!empty($insufficient)) {
            $errors = array_merge($errors, $insufficient);
        } else {
            // Persist order + items + payment in DB using transaction
            $conn->begin_transaction();
            try {
                $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
                $status = 'pending';

                $stmt = $conn->prepare("INSERT INTO orders (user_id, total, name, address, city, postal, phone, status, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
                $stmt->bind_param('idssssss', $userId, $total, $name, $address, $city, $postal, $phone, $status);
                $stmt->execute();
                $orderId = $conn->insert_id;

                // insert order items
                $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, price, qty) VALUES (?,?,?,?)");
                $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

                foreach ($_SESSION['cart'] as $cid => $info) {
                    $pid = (int)$cid;
                    if (!isset($products[$pid])) continue;
                    $p = $products[$pid];
                    $price = (float)$p['price'];
                    if ($p['discount_percent']) {
                        $price -= ($price * $p['discount_percent'] / 100);
                    }
                    $qty = (int)$info['qty'];

                    $itemStmt->bind_param('iidi', $orderId, $pid, $price, $qty);
                    $itemStmt->execute();

                    // decrement stock
                    $stockStmt->bind_param('iii', $qty, $pid, $qty);
                    $stockStmt->execute();
                }

                // simulate payment record
                $payStmt = $conn->prepare("INSERT INTO payments (order_id, amount, method, status, created_at) VALUES (?,?,?,?,NOW())");
                $method = 'card_simulated';
                $payStatus = 'paid';
                $payStmt->bind_param('idss', $orderId, $total, $method, $payStatus);
                $payStmt->execute();

                // mark order as paid
                $upd = $conn->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
                $upd->bind_param('i', $orderId);
                $upd->execute();

                $conn->commit();

                $_SESSION['last_order'] = [
                    'id' => $orderId,
                    'name' => $name,
                    'address' => $address,
                ];
                $_SESSION['cart'] = [];
                $success = 'Dziękujemy — zamówienie zostało przyjęte. Numer zamówienia: ' . $orderId;
            } catch (Exception $ex) {
                $conn->rollback();
                $errors[] = 'Błąd podczas zapisu zamówienia: ' . $ex->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Checkout — Apteka</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
    <div class="logo-box">
        <img src="logo.png" alt="Logo" class="logo">
        <h1>Apteka Internetowa</h1>
    </div>
    <div class="header-actions">
        <a href="cart.php" class="product-btn product-btn-secondary">Powrót do koszyka</a>
    </div>
</header>

<main>
    <section class="checkout-shell">
        <h2>Wprowadź dane do wysyłki</h2>

        <?php if (!empty($errors)): ?>
            <div class="notice error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="notice success"><?= htmlspecialchars($success) ?></div>
            <a href="index.php" class="product-btn">Powrót do sklepu</a>
        <?php else: ?>
            <form method="post" action="checkout.php" class="checkout-form">
                <label>Imię i nazwisko
                    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
                </label>

                <label>Adres
                    <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" required>
                </label>

                <label>Miasto
                    <input type="text" name="city" value="<?= htmlspecialchars($city) ?>" required>
                </label>

                <label>Kod pocztowy
                    <input type="text" name="postal" value="<?= htmlspecialchars($postal) ?>" required>
                </label>

                <label>Telefon
                    <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                </label>

                <div class="product-actions">
                    <button type="submit" class="product-btn product-btn-primary">Zamawiam i płacę</button>
                    <a href="cart.php" class="product-btn product-btn-secondary">Powrót</a>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
