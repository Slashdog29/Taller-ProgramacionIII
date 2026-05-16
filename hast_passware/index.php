<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Encriptar y Verificar claves bcrypt</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #f0f2f5; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 25px; }
        input, button { padding: 10px; font-size: 16px; margin-top: 8px; width: 100%; box-sizing: border-box; }
        button { background: #2c6e9e; color: white; border: none; border-radius: 6px; cursor: pointer; }
        button:hover { background: #1e4a6e; }
        .result { background: #e9ecef; padding: 12px; border-radius: 6px; margin-top: 20px; word-break: break-all; font-family: monospace; }
        h2 { color: #1e3c72; border-left: 5px solid #2c6e9e; padding-left: 12px; margin-top: 0; }
        hr { margin: 20px 0; }
        .success { color: #155724; background: #d4edda; border-left: 4px solid #28a745; }
        .error { color: #721c24; background: #f8d7da; border-left: 4px solid #dc3545; }
    </style>
</head>
<body>

<!-- SECCIÓN 1: ENCRIPTAR -->
<div class="card">
    <h2>🔐 1. Encriptar (generar hash bcrypt)</h2>
    <p>Introduce una clave y obtén su hash en formato <code>$2y$10$...</code></p>
    <form method="post">
        <input type="text" name="clave_encriptar" placeholder="Ej: miClave123" required autofocus>
        <button type="submit" name="accion" value="encriptar">Encriptar clave</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'encriptar') {
        $clave = $_POST['clave_encriptar'] ?? '';
        if ($clave !== '') {
            $hash = password_hash($clave, PASSWORD_BCRYPT, ['cost' => 10]);
            echo '<div class="result">';
            echo '<strong>✅ Hash generado:</strong><br>';
            echo htmlspecialchars($hash);
            echo '</div>';
        }
    }
    ?>
</div>

<!-- SECCIÓN 2: VERIFICAR (similar a "desencriptar") -->
<div class="card">
    <h2>🔍 2. Verificar clave contra un hash (no se puede desencriptar, pero sí comprobar)</h2>
    <p>Pega un hash bcrypt y escribe una clave para saber si coinciden.</p>
    <form method="post">
        <input type="text" name="hash_verificar" placeholder="Hash bcrypt (ej: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)" required>
        <input type="text" name="clave_verificar" placeholder="Clave a probar" required>
        <button type="submit" name="accion" value="verificar">Verificar coincidencia</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'verificar') {
        $hash = $_POST['hash_verificar'] ?? '';
        $clave = $_POST['clave_verificar'] ?? '';
        if ($hash !== '' && $clave !== '') {
            if (password_verify($clave, $hash)) {
                echo '<div class="result success">✅ ¡Coinciden! La clave <strong>' . htmlspecialchars($clave) . '</strong> es correcta para ese hash.</div>';
            } else {
                echo '<div class="result error">❌ No coinciden. La clave <strong>' . htmlspecialchars($clave) . '</strong> NO es la que generó ese hash.</div>';
            }
        }
    }
    ?>
</div>

<div class="card">
    <p><strong>Nota:</strong> bcrypt es un hash unidireccional, no existe "desencriptar". La verificación comprueba si la clave proporcionada produce el mismo hash.</p>
    <p><strong>Ejemplo:</strong> El hash <code>$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi</code> corresponde a la clave <code>password</code>.</p>
</div>

</body>
</html>
