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
    } else {
        $error = "Błędne dane logowania";
    }
}
?>

<form method="post">
    <h2>Logowanie</h2>
    <?php if(isset($error)) echo "<p>$error</p>"; ?>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Hasło" required>
    <button>Zaloguj</button>
</form>
