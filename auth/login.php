<?php
include "../session.php";
include "../config/db.php";



if($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        header("Location: ../index.php");
        exit;
    } else {
        $error = "Błędne dane logowania";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie | Apteka Internetowa</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel auth-panel-info">
            <a href="../index.php" class="auth-back">Wroc do strony glownej</a>
            <div class="auth-brand">
                <img src="../logo.png" alt="Logo Apteki" class="logo">
                <h1>Apteka Internetowa</h1>
            </div>
            <p class="auth-tagline">Bezpieczne i szybkie logowanie do panelu klienta.</p>
            <ul class="auth-benefits">
                <li>Podglad ofert promocyjnych</li>
                <li>Szybki dostep do konta</li>
                <li>Nowoczesny panel zakupowy</li>
            </ul>
        </section>

        <section class="auth-panel auth-panel-form">
            <form method="post" class="auth-form">
                <h2>Logowanie</h2>
                <p class="auth-subtitle">Zaloguj sie, aby kontynuowac zakupy.</p>

                <?php if (isset($_GET['registered'])): ?>
                    <p class="auth-success">Konto zostalo utworzone. Mozesz sie teraz zalogowac.</p>
                <?php endif; ?>

                <?php if(isset($error)): ?>
                    <p class="auth-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <label for="email">Email</label>
                <input id="email" type="email" name="email" placeholder="np. jan@apteka.pl" required>

                <label for="password">Haslo</label>
                <input id="password" type="password" name="password" placeholder="Wpisz haslo" required>

                <button type="submit">Zaloguj sie</button>

                <p class="auth-alt">
                    Nie masz konta?
                    <a href="register.php">Zarejestruj sie</a>
                </p>
            </form>
        </section>
    </main>
</body>
</html>
