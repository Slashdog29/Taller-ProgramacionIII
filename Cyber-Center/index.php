<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!empty($_SESSION['active'])) {
    header('location: src/');
    exit;
}

$login_exitoso = false;
$nombre_usuario = "";
$mensaje_alerta = "";

if (!empty($_POST)) {
    if (empty($_POST['usuario']) || empty($_POST['clave'])) {
        $mensaje_alerta = '⚠️ Ingrese su correo y contraseña';
    } else {
        require_once "conexion.php";

        if (!$conexion || $conexion->connect_error) {
            $mensaje_alerta = '❌ Error de conexión con la base de datos';
        } else {
            $correo = trim($_POST['usuario']);
            $password = $_POST['clave'];

            $stmt = $conexion->prepare("SELECT id, nombre_completo, correo_institucional, password_hash, rol, activo FROM usuarios WHERE correo_institucional = ?");
            if ($stmt) {
                $stmt->bind_param("s", $correo); 
                $stmt->execute();
                $resultado = $stmt->get_result();

                if ($resultado->num_rows === 1) {
                    $usuario = $resultado->fetch_assoc();
                    if ($usuario['activo'] != 1) {
                        $mensaje_alerta = '❌ Usuario desactivado.';
                    } elseif (password_verify($password, $usuario['password_hash'])) {
                        $_SESSION['active'] = true;
                        $_SESSION['idUser'] = $usuario['id'];
                        $_SESSION['nombre'] = $usuario['nombre_completo'];
                        $_SESSION['rol'] = $usuario['rol'];
                        $_SESSION['user'] = $usuario['correo_institucional'];
                        $nombre_usuario = $_SESSION['nombre'];

                        $upd = $conexion->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
                        $upd->bind_param("i", $usuario['id']);
                        $upd->execute();
                        $upd->close();

                        $ip = $_SERVER['REMOTE_ADDR'];
                        $fecha = date('Y-m-d H:i:s');
                        $hist = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                        $sector = 'login';
                        $accion = 'Inicio de sesión exitoso';
                        $hist->bind_param("sssss", $nombre_usuario, $ip, $fecha, $sector, $accion);
                        $hist->execute();
                        $hist->close();

                        $login_exitoso = true;
                    } else {
                        $mensaje_alerta = '❌ Contraseña incorrecta';
                        session_destroy();
                    }
                } else {
                    $mensaje_alerta = '❌ Correo no registrado';
                    session_destroy();
                }
                $stmt->close();
            } else {
                $mensaje_alerta = '❌ Error en la consulta';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberCenter UNERG | Gestión de Equipos y Tiempos</title>
    <link href="assets/css/styles.css" rel="stylesheet">
    <script src="assets/js/all.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0f;
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
            position: relative;
        }
        .circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: flotar 18s infinite alternate ease-in-out;
        }
        .c1 { width: 400px; height: 400px; background: #4facfe; top: -15%; left: -10%; }
        .c2 { width: 350px; height: 350px; background: #00f2fe; bottom: -10%; right: -10%; animation-delay: -6s; }
        @keyframes flotar {
            0% { transform: translate(0, 0); }
            100% { transform: translate(80px, 80px); }
        }
        .logo-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            border: 3px solid #4facfe;
        }
        .logo-wrapper img { width: 70px; }
        .login-card {
            background: rgba(20, 20, 35, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 40px 35px;
            border-radius: 40px;
            width: 420px;
            text-align: center;
            box-shadow: 0 25px 45px rgba(0,0,0,0.6);
            transition: 0.3s;
        }
        .input-group {
            position: relative;
            margin-bottom: 25px;
            text-align: left;
        }
        .input-group input {
            width: 100%;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            outline: none;
            padding: 14px 18px 14px 45px;
            border-radius: 30px;
            color: white;
            font-size: 1rem;
            transition: 0.2s;
        }
        .input-group input:focus {
            border-color: #4facfe;
            background: rgba(255,255,255,0.12);
            box-shadow: 0 0 8px rgba(79,172,254,0.5);
        }
        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #4facfe;
            font-size: 1.1rem;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            border-radius: 40px;
            border: none;
            background: linear-gradient(135deg, #4facfe, #00c3fe);
            color: white;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79,172,254,0.5);
        }
        .alert-error {
            color: #ff6b6b;
            background: rgba(255,75,75,0.15);
            padding: 10px;
            border-radius: 30px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border-left: 3px solid #ff6b6b;
        }
        #loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0a0a0f;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 200;
            backdrop-filter: blur(10px);
        }
        .progress-bar-container {
            width: 280px;
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 20px;
        }
        .progress-fill {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #4facfe, #00f2fe);
            transition: width 0.1s linear;
        }
        .welcome-message {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 20px;
        }
        .cyber-note {
            margin-top: 20px;
            font-size: 0.75rem;
            color: #aaa;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="circle c1"></div>
    <div class="circle c2"></div>

    <div id="loader-overlay">
        <div class="logo-wrapper">
            <img src="assets/img/logo.png" alt="Logo CyberCenter">
        </div>
        <div class="welcome-message">Bienvenido, <span id="nombreUsuario"><?php echo htmlspecialchars($nombre_usuario); ?></span></div>
        <div class="progress-bar-container">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="cyber-note" style="border: none; margin-top: 15px;">Cargando panel de control de equipos...</div>
    </div>

    <div class="login-card" id="loginFormContainer">
        <div class="logo-wrapper">
            <img src="assets/img/logo.png" alt="CyberCenter UNERG">
        </div>
        <h2 style="color: white; margin-bottom: 5px;">CyberCenter</h2>
        <p style="color: #b0b0b0; margin-bottom: 25px;">Sistema de Gestión de Tiempos y Equipos</p>

        <form method="POST">
            <?php if (!empty($mensaje_alerta)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($mensaje_alerta); ?></div>
            <?php endif; ?>

            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="usuario" placeholder="Correo institucional" required autofocus>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="clave" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Ingresar al sistema
            </button>
        </form>

        <div class="cyber-note">
            <i class="fas fa-desktop"></i> Control de PCs · Temporizador · Ventas · Reportes
        </div>
    </div>

    <script>
        <?php if ($login_exitoso): ?>
            document.getElementById('loginFormContainer').style.display = 'none';
            document.getElementById('loader-overlay').style.display = 'flex';
            let progreso = 0;
            const barra = document.getElementById('progressFill');
            const intervalo = setInterval(() => {
                if (progreso >= 100) {
                    clearInterval(intervalo);
                    window.location.href = 'src/';
                } else {
                    progreso += 3;
                    barra.style.width = progreso + '%';
                }
            }, 50);
        <?php endif; ?>
    </script>
</body>
</html>
