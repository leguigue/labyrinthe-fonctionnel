<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: mousecatcher.php");
    exit();
}
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Kalnia+Glaze:wght@100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="lobby.css">
    <title>Accueil</title>
</head>
<body>
<div class="fog">
    <div class="fog-img"></div>
    <div class="fog-img fog-img-second"></div>
</div>
<header>
    <h1>Qui aime bien ChaRis bien</h1>
</header>
<main>
<form action="_db/register.php" id="inscription" method="POST" onsubmit="return validatePassword()">
    <h2>Inscription</h2>
    <label for="username">Pseudo :</label>
    <input type="text" id="username" name="username" required>
    <label for="email">Email :</label>
    <input type="email" id="email" name="email" required>
    <label for="password">Mot de passe :</label>
    <input type="password" id="password" name="password" required>
    <label for="confirm-password">Confirmer le mot de passe :</label>
    <input type="password" id="confirm-password" name="confirm-password" required>
    <button type="submit">S'inscrire</button>
</form>
<form action="_db/login.php" id="connexion" method="POST">
    <h2>Connexion</h2>
    <label for="login-username">Pseudo :</label>
    <input type="text" id="login-username" name="username" required>
    <label for="login-password">Mot de passe :</label>
    <input type="password" id="login-password" name="password" required>
    <button type="submit">Se connecter</button>
</form>
</main>
<script>
function validatePassword() {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm-password").value;
    if (password !== confirmPassword) {
        alert("Les mots de passe ne correspondent pas.");
        return false;
    }
    return true;
}
</script>
</body>
</html>
