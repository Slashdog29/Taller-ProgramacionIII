<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Encriptador de claves bcrypt</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f0f2f5; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        input, button { padding: 10px; font-size: 16px; margin-top: 10px; width: 100%; box-sizing: border-box; }
        button { background: #2c6e9e; color: white; border: none; border-radius: 6px; cursor: pointer; }
        button:hover { background: #1e4a6e; }
        .result { background: #e9ecef; padding: 12px; border-radius: 6px; margin-top: 20px; word-break: break-all; font-family: monospace; }
        h1 { color: #1e3c72; margin-top: 0; }
    </style>
</head>
<body>
<div class="card">
    <h1>🔐 Encriptador bcrypt</h1>
    <p>Introduce cualquier clave y obtén su versión encriptada (hash) con <code>bcrypt</code> (coste 10, formato <code>$2y$10$...</code>).</p>

    <form method="post">
        <input type="text" name="clave" placeholder="Ej: miClaveSecreta123" required autofocus>
        <button type="submit">Encriptar clave</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave']) && $_POST['clave'] !== '') {
        $clave = $_POST['clave'];
        $hash = password_hash($clave, PASSWORD_BCRYPT, ['cost' => 10]);
        echo '<div class="result">';
        echo '<strong>🔒 Clave encriptada:</strong><br>';
        echo htmlspecialchars($hash);
        echo '</div>';
    }
    ?>
</div>
</body>
</html>