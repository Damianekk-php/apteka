<?php
include "config/db.php";
include "session.php";

// initialize cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// auto-expire coupons after their end date
$conn->query("UPDATE discounts SET is_active = 0 WHERE is_active = 1 AND end_date < CURDATE()");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $qty = isset($_POST['qty']) ? max(1, (int)$_POST['qty']) : 1;
        if ($id > 0) {
            // check stock and cap
            $stmt = $conn->prepare('SELECT stock FROM products WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stock = $row ? (int)$row['stock'] : 0;

            $current = isset($_SESSION['cart'][$id]) ? (int)($_SESSION['cart'][$id]['qty']) : 0;
            $wanted = $current + $qty;
            $allowed = min($wanted, $stock);
            if ($allowed <= 0) {
                $_SESSION['cart_notice'] = 'Produkt niedostępny w wymaganej ilości.';
            } else {
                $_SESSION['cart'][$id] = ['qty' => $allowed];
                if ($allowed < $wanted) {
                    $_SESSION['cart_notice'] = 'Ilość została ograniczona do dostępnego stanu magazynowego.';
                }
            }
        }
        $redirect = $_POST['redirect'] ?? '';
        if ($redirect === 'checkout') {
            if (!isset($_SESSION['user'])) {
                header('Location: auth/login.php');
            } else {
                header('Location: checkout.php');
            }
        } else {
            header('Location: cart.php');
        }
        exit;
    }

    if ($action === 'remove') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0 && isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }
        header('Location: cart.php');
        exit;
    }

    if ($action === 'apply_coupon') {
        $couponCode = trim($_POST['coupon_code'] ?? '');
        if ($couponCode === '') {
            unset($_SESSION['cart_coupon']);
            $_SESSION['cart_notice'] = 'Wpisz poprawny kod rabatowy.';
            header('Location: cart.php');
            exit;
        }

        $stmt = $conn->prepare("SELECT id, name, discount_percent, start_date, end_date, is_active FROM discounts WHERE name = ? LIMIT 1");
        $stmt->bind_param('s', $couponCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $coupon = $result->fetch_assoc();

        $today = date('Y-m-d');
        if ($coupon && (int)$coupon['is_active'] === 1 && $today >= $coupon['start_date'] && $today <= $coupon['end_date']) {
            $_SESSION['cart_coupon'] = [
                'id' => (int)$coupon['id'],
                'code' => $coupon['name'],
                'percent' => (int)$coupon['discount_percent'],
            ];
            $_SESSION['cart_notice'] = 'Kod rabatowy został zastosowany.';
        } else {
            unset($_SESSION['cart_coupon']);
            $_SESSION['cart_notice'] = 'Nieprawidłowy lub nieaktywny kod rabatowy.';
        }

        header('Location: cart.php');
        exit;
    }

    if ($action === 'update') {
        // debug log POST and session cart
        error_log('CART POST: ' . print_r($_POST, true));
        error_log('CART SESSION BEFORE: ' . print_r($_SESSION['cart'] ?? [], true));

        $submitted = $_POST['quantities'] ?? [];

        // if session cart is empty (possible session loss), rebuild from submitted quantities
        if ((empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) && !empty($submitted)) {
            foreach ($submitted as $cid => $val) {
                $cid = (int)$cid;
                $q = max(0, (int)$val);
                if ($q > 0) $_SESSION['cart'][$cid] = ['qty' => $q];
            }
            error_log('CART SESSION REBUILT: ' . print_r($_SESSION['cart'], true));
        }

        // build quantities to validate: prefer submitted values, fallback to existing cart qty
        $quantities = [];
        foreach ($_SESSION['cart'] as $cid => $cinfo) {
            $cid = (int)$cid;
            if (isset($submitted[$cid]) && $submitted[$cid] !== '') {
                $val = max(0, (int)$submitted[$cid]);
                // ignore zero/negative from auto-submits; keep previous quantity
                if ($val > 0) {
                    $quantities[$cid] = $val;
                } else {
                    $quantities[$cid] = (int)$cinfo['qty'];
                }
            } else {
                // keep current qty if input missing/empty
                $quantities[$cid] = (int)$cinfo['qty'];
            }
        }

        // validate against stock for the items we're actually tracking
        $ids = array_map('intval', array_keys($quantities));
        $stocks = [];
        if (!empty($ids)) {
            $in = implode(',', $ids);
            $sql = "SELECT id, stock FROM products WHERE id IN ($in)";
            $res = $conn->query($sql);
            while ($r = $res->fetch_assoc()) $stocks[(int)$r['id']] = (int)$r['stock'];
        }

        $notice = '';
        foreach ($quantities as $id => $q) {
            // safety: if all quantities sum to 0, likely an empty/malformed submission — do not clear cart
            $totalSubmitted = 0;
            foreach ($quantities as $tmpQ) $totalSubmitted += (int)$tmpQ;
            if ($totalSubmitted === 0) {
                $_SESSION['cart_notice'] = 'Nieprawidłowe dane ilości — zachowano poprzednie ilości.';
                $redirect = $_POST['redirect'] ?? '';
                if ($redirect === 'checkout') {
                    if (!isset($_SESSION['user'])) header('Location: auth/login.php'); else header('Location: checkout.php');
                } else {
                    header('Location: cart.php');
                }
                exit;
            }

            $available = $stocks[$id] ?? 0;
            // do not remove items on update; require explicit 'remove' action
            if ($q <= 0) {
                // ignore and keep previous value
                continue;
            }
            if ($q > $available) {
                $q = $available;
                $notice = 'Niektóre ilości zostały ograniczone do dostępnego stanu.';
            }
            // set the adjusted quantity
            $_SESSION['cart'][$id]['qty'] = $q;
        }
        if ($notice) $_SESSION['cart_notice'] = $notice;

        $redirect = $_POST['redirect'] ?? '';
        if ($redirect === 'checkout') {
            if (!isset($_SESSION['user'])) {
                header('Location: auth/login.php');
            } else {
                header('Location: checkout.php');
            }
        } else {
            header('Location: cart.php');
        }
        exit;
    }
}

// gather product ids
$ids = array_keys($_SESSION['cart']);
$products = [];
$total = 0.0;
$couponDiscount = 0.0;
$couponPercent = 0;
$couponCode = '';

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

    $sql = "SELECT p.*, c.name AS category_name, d.discount_percent
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_discounts pd ON p.id = pd.product_id
            LEFT JOIN discounts d ON pd.discount_id = d.id AND d.is_active = 1
            WHERE p.id IN ($in)";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $products[$row['id']] = $row;
    }

    foreach ($_SESSION['cart'] as $id => $info) {
        if (isset($products[$id])) {
            $p = $products[$id];
            $price = (float)$p['price'];
            if ($p['discount_percent']) {
                $price -= ($price * $p['discount_percent'] / 100);
            }
            $subtotal = $price * (int)$info['qty'];
            $total += $subtotal;
        }
    }

    if ($couponPercent > 0) {
        $couponDiscount = round($total * ($couponPercent / 100), 2);
        $total = max(0, $total - $couponDiscount);
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koszyk — Apteka</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
    <div class="logo-box">
        <img src="logo.png" alt="Logo" class="logo">
        <h1>Apteka Internetowa</h1>
    </div>
    <div class="header-actions">
        <a href="index.php" class="product-btn product-btn-secondary">Kontynuuj zakupy</a>
    </div>
</header>

<main>
    <section class="cart-shell">
        <h2>Twój koszyk</h2>

        <?php if (!empty($_SESSION['cart_notice'])): ?>
            <div class="notice warning"><?= htmlspecialchars($_SESSION['cart_notice']) ?></div>
            <?php unset($_SESSION['cart_notice']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['cart_coupon']['code'])): ?>
            <div class="notice success">Kod rabatowy <strong><?= htmlspecialchars($_SESSION['cart_coupon']['code']) ?></strong> aktywny: <?= (int)$_SESSION['cart_coupon']['percent'] ?>% zniżki.</div>
        <?php endif; ?>

        <?php if (empty($_SESSION['cart']) || empty($products)): ?>
            <p>Twój koszyk jest pusty.</p>
            <a href="index.php" class="product-btn product-btn-primary">Przejdź do sklepu</a>
        <?php else: ?>
            <form method="post" action="cart.php" class="coupon-form" id="coupon-form" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin: 0 0 18px 0;">
                <input type="hidden" name="action" value="apply_coupon">
                <input type="text" name="coupon_code" id="coupon-code" placeholder="Wpisz kod rabatowy" value="<?= htmlspecialchars($_SESSION['cart_coupon']['code'] ?? '') ?>" style="min-width:220px; flex:1;">
                <button type="submit" class="product-btn">Zastosuj kod</button>
            </form>

            <form method="post" action="cart.php" id="cart-form" style="display:none;">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="redirect" id="redirect-field" value="">
            </form>

            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Produkt</th>
                        <th>Cena</th>
                        <th>Ilość</th>
                        <th>Razem</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['cart'] as $id => $info):
                        if (!isset($products[$id])) continue;
                        $p = $products[$id];
                        $basePrice = (float)$p['price'];
                        $price = $basePrice;
                        if ($p['discount_percent']) {
                            $price -= ($price * $p['discount_percent'] / 100);
                        }
                        $finalUnitPrice = $price;
                        if ($couponPercent > 0) {
                            $finalUnitPrice = $finalUnitPrice - ($finalUnitPrice * $couponPercent / 100);
                        }
                        $qty = (int)$info['qty'];
                        $subtotal = $finalUnitPrice * $qty;
                    ?>
                    <tr>
                        <td>
                            <div><?= htmlspecialchars($p['name']) ?></div>
                            <?php if ($couponPercent > 0): ?>
                                <div class="cart-item-price-note">
                                    po rabacie: <strong><?= number_format($finalUnitPrice, 2) ?> zł</strong>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($couponPercent > 0): ?>
                                <span class="old-price"><?= number_format($price, 2) ?> zł</span>
                                <span class="price"><?= number_format($finalUnitPrice, 2) ?> zł</span>
                            <?php else: ?>
                                <?= number_format($price,2) ?> zł
                            <?php endif; ?>
                        </td>
                        <td>
                            <input form="cart-form" type="number" name="quantities[<?= (int)$id ?>]" value="<?= $qty ?>" min="0" max="<?= (int)($p['stock'] ?? 0) ?>" style="width:70px;"
                                   data-price="<?= htmlspecialchars(number_format($finalUnitPrice,2,'.','')) ?>" data-stock="<?= (int)($p['stock'] ?? 0) ?>">
                        </td>
                        <td><span class="row-subtotal"><?= number_format($subtotal,2) ?></span> zł</td>
                        <td>
                            <form method="post" action="cart.php" style="display:inline-block; margin:0;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int)$id ?>">
                                <button type="submit" class="product-btn product-btn-secondary">Usuń</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-actions">
                <?php if (!isset($_SESSION['user'])): ?>
                    <a href="auth/login.php" class="product-btn product-btn-primary">Kup (zaloguj się)</a>
                <?php else: ?>
                    <button type="button" id="checkout-btn" class="product-btn product-btn-primary">Kup</button>
                <?php endif; ?>
            </div>

            <div class="cart-total">
                <strong>Łącznie: <span id="cart-total"><?= number_format($total,2) ?></span> zł</strong>
                <?php if ($couponDiscount > 0): ?>
                    <div class="cart-discount-line">
                        Rabat: <?= number_format($total + $couponDiscount, 2) ?> zł - <?= number_format($couponDiscount, 2) ?> zł = <?= number_format($total, 2) ?> zł
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<script>
// Recalculate row subtotals and cart total when quantities change
(() => {
    const qtyInputs = Array.from(document.querySelectorAll('input[type="number"][name^="quantities"]'));
    function fmt(n){ return Number(n).toFixed(2); }
    function recalc(){
        let total = 0;
        qtyInputs.forEach(inp => {
            const price = parseFloat(inp.dataset.price) || 0;
            let qty = parseInt(inp.value) || 0;
            const stock = parseInt(inp.dataset.stock) || 0;
            if (qty < 0) qty = 0;
            if (stock >= 0 && qty > stock) {
                qty = stock;
                inp.value = stock;
            }
            const row = inp.closest('tr');
            const subEl = row.querySelector('.row-subtotal');
            const subtotal = price * qty;
            if (subEl) subEl.textContent = fmt(subtotal);
            total += subtotal;
        });
        const totalEl = document.getElementById('cart-total');
        if (totalEl) totalEl.textContent = fmt(total);
    }

    // debounce submit after changes
    const form = document.getElementById('cart-form');
    const redirectField = document.getElementById('redirect-field');
    let submitTimer = null;
    const DEBOUNCE_MS = 700;

    function scheduleSubmit() {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(() => {
            // ensure we're doing a normal update
            redirectField.value = '';
            if (form) form.submit();
        }, DEBOUNCE_MS);
    }

    qtyInputs.forEach(inp => {
        inp.addEventListener('input', () => { recalc(); scheduleSubmit(); });
        inp.addEventListener('change', () => { recalc(); scheduleSubmit(); });
    });

    // checkout button: set redirect and submit immediately
    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', () => {
            if (submitTimer) clearTimeout(submitTimer);
            redirectField.value = 'checkout';
            form.submit();
        });
    }

    const couponForm = document.getElementById('coupon-form');
    const couponCode = document.getElementById('coupon-code');
    if (couponForm && couponCode) {
        couponCode.addEventListener('change', () => couponForm.submit());
        couponCode.addEventListener('blur', () => {
            if (couponCode.value.trim()) {
                couponForm.submit();
            }
        });
    }

    // initial calc (in case values changed)
    recalc();
})();
</script>
</body>
</html>
