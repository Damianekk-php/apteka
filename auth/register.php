<?php
include "../session.php";
include "../config/db.php";

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$name = trim($_POST['name'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';
	$confirmPassword = $_POST['confirm_password'] ?? '';

	if ($name === '' || $email === '' || $password === '') {
		$error = "Wypelnij wszystkie pola";
	} elseif ($password !== $confirmPassword) {
		$error = "Hasla musza byc takie same";
	} else {
		$checkSql = "SELECT id FROM users WHERE email=?";
		$checkStmt = $conn->prepare($checkSql);
		$checkStmt->bind_param("s", $email);
		$checkStmt->execute();
		$existingUser = $checkStmt->get_result()->fetch_assoc();

		if ($existingUser) {
			$error = "Konto z takim adresem email juz istnieje";
		} else {
			$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
			$insertSql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')";
			$insertStmt = $conn->prepare($insertSql);
			$insertStmt->bind_param("sss", $name, $email, $hashedPassword);

			if ($insertStmt->execute()) {
				header("Location: login.php?registered=1");
				exit;
			}

			$error = "Nie udalo sie utworzyc konta";
		}
	}
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Rejestracja | Apteka Internetowa</title>
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
			<p class="auth-tagline">Stworz konto i zyskaj szybki dostep do zakupow oraz promocji.</p>
			<ul class="auth-benefits">
				<li>Blyskawiczna rejestracja</li>
				<li>Bezpieczne hashowanie hasel</li>
				<li>Nowoczesny panel klienta</li>
			</ul>
		</section>

		<section class="auth-panel auth-panel-form">
			<form method="post" class="auth-form">
				<h2>Rejestracja</h2>
				<p class="auth-subtitle">Utworz konto w kilka chwil.</p>

				<?php if (isset($_GET['registered'])): ?>
					<p class="auth-success">Konto zostalo utworzone. Mozesz sie zalogowac.</p>
				<?php endif; ?>

				<?php if (!empty($error)): ?>
					<p class="auth-error"><?= htmlspecialchars($error) ?></p>
				<?php endif; ?>

				<label for="name">Imie / nazwa</label>
				<input id="name" type="text" name="name" placeholder="Jan Kowalski" required>

				<label for="email">Email</label>
				<input id="email" type="email" name="email" placeholder="np. jan@apteka.pl" required>

				<label for="password">Haslo</label>
				<input id="password" type="password" name="password" placeholder="Wpisz haslo" required>

				<label for="confirm_password">Powtorz haslo</label>
				<input id="confirm_password" type="password" name="confirm_password" placeholder="Powtorz haslo" required>

				<button type="submit">Utworz konto</button>

				<p class="auth-alt">
					Masz juz konto?
					<a href="login.php">Zaloguj sie</a>
				</p>
			</form>
		</section>
	</main>
</body>
</html>
