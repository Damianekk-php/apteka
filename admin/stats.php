<?php
include "../session.php";
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Brak dostępu");
}

// prepare last 12 months labels
$labels = [];
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $dt = new DateTime("first day of -$i month");
    $key = $dt->format('Y-m');
    $labels[] = $dt->format('Y-m');
    $months[$key] = ['qty' => 0, 'revenue' => 0.0];
}

// fetch aggregated sales by month (using orders + order_items)
$sql = "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS ym, SUM(oi.qty) AS total_qty, SUM(oi.price * oi.qty) AS total_revenue
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        WHERE o.status = 'paid' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
        GROUP BY ym
        ORDER BY ym";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $ym = $row['ym'];
    if (isset($months[$ym])) {
        $months[$ym]['qty'] = (int)$row['total_qty'];
        $months[$ym]['revenue'] = (float)$row['total_revenue'];
    }
}

$qtyData = [];
$revData = [];
foreach ($labels as $l) {
    $qtyData[] = $months[$l]['qty'];
    $revData[] = round($months[$l]['revenue'], 2);
}

// fetch top selling products (total qty & revenue) across paid orders
$topProducts = [];
$topSql = "SELECT oi.product_id, p.name, SUM(oi.qty) AS total_qty, SUM(oi.price * oi.qty) AS total_revenue
           FROM order_items oi
           JOIN orders o ON oi.order_id = o.id
           LEFT JOIN products p ON p.id = oi.product_id
           WHERE o.status = 'paid'
           GROUP BY oi.product_id
           ORDER BY total_qty DESC
           LIMIT 20";
$topRes = $conn->query($topSql);
while ($r = $topRes->fetch_assoc()) {
    $topProducts[] = $r;
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Statystyki — Panel admina</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-hero">
            <div>
                <p class="admin-kicker">Statystyki</p>
                <h2>Sprzedaż i przychody (ostatnie 12 miesięcy)</h2>
                <p class="admin-description">Podsumowanie ilości sprzedanych produktów i łącznych przychodów.</p>
            </div>
            <div class="admin-actions">
                <a href="products.php" class="admin-btn admin-btn-ghost">Powrót do produktów</a>
            </div>
        </section>

        <section style="margin-top:22px;">
            <div class="admin-edit-preview">
                <h3>Top sprzedanych produktów</h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produkt</th>
                            <th>Ilość</th>
                            <th>Przychód (PLN)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $i => $p): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($p['name'] ?? 'Usunięty produkt (ID '.$p['product_id'].')') ?></td>
                                <td><?= (int)$p['total_qty'] ?></td>
                                <td><?= number_format((float)$p['total_revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="display:grid;gap:20px;grid-template-columns:1fr 1fr; margin-top:18px;">
            <div class="admin-edit-preview">
                <h3>Ilość sprzedanych sztuk</h3>
                <canvas id="soldChart" aria-label="Ilość sprzedanych sztuk" role="img"></canvas>
            </div>

            <div class="admin-edit-preview">
                <h3>Przychód (PLN)</h3>
                <canvas id="revenueChart" aria-label="Przychód" role="img"></canvas>
            </div>
        </section>

        <script>
            const labels = <?= json_encode($labels) ?>;
            const qtyData = <?= json_encode($qtyData) ?>;
            const revData = <?= json_encode($revData) ?>;

            const soldCtx = document.getElementById('soldChart').getContext('2d');
            new Chart(soldCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sprzedane sztuki',
                        data: qtyData,
                        backgroundColor: 'rgba(77,226,196,0.9)'
                    }]
                },
                options: {responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
            });

            const revCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Przychód (PLN)',
                        data: revData,
                        backgroundColor: 'rgba(36,184,255,0.16)',
                        borderColor: 'rgba(36,184,255,0.95)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
            });
        </script>

    </main>
</body>
</html>
