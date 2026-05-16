<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!empty($_SESSION['active'])) {
    header('Location: src/index.php');
    exit;
}

$login_exitoso = false;
$nombre_usuario = "";
$mensaje_alerta = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once "conexion.php";

    $usuario = trim($_POST['usuario']);
    $clave = trim($_POST['clave']);

    if (empty($usuario) || empty($clave)) {
        $mensaje_alerta = "⚠️ Ingrese usuario y contraseña";
    } else {
        $stmt = $conexion->prepare("SELECT id, nombre_completo, correo_institucional, password_hash, rol FROM usuarios WHERE correo_institucional = ? AND activo = 1");
        if ($stmt) {
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {
                $user = $res->fetch_assoc();
                if (password_verify($clave, $user['password_hash'])) {
                    $_SESSION['active'] = true;
                    $_SESSION['idUser'] = $user['id'];
                    $_SESSION['nombre'] = $user['nombre_completo'];
                    $_SESSION['rol'] = $user['rol'];
                    $_SESSION['user'] = $user['correo_institucional'];

                    $nombre_usuario = $user['nombre_completo'];
                    $login_exitoso = true;

                    $ip = $_SERVER['REMOTE_ADDR'];
                    $fecha = date('Y-m-d H:i:s');
                    $sector = "login";
                    $accion = "Inicio de sesión";
                    $stmt2 = $conexion->prepare("INSERT INTO historial (usuario, ip, fyh, sector, acciones) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt2) {
                        $stmt2->bind_param("sssss", $nombre_usuario, $ip, $fecha, $sector, $accion);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                } else {
                    $mensaje_alerta = "❌ Usuario o contraseña incorrectos";
                }
            } else {
                $mensaje_alerta = "❌ Usuario o contraseña incorrectos";
            }
            $stmt->close();
        } else {
            $mensaje_alerta = "❌ Error en el sistema";
        }
    }
    $conexion->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberCenter | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            position: relative;
            overflow: hidden;
        }

        /* Animación de partículas (canvas) */
        #particles-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .login-card {
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 2.5rem;
            width: 380px;
            text-align: center;
            box-shadow: 0 25px 45px rgba(0,0,0,0.3);
            z-index: 2;
            transition: transform 0.3s;
            position: relative;
        }
        .login-card:hover {
            transform: translateY(-5px);
        }
        h2 {
            color: white;
            font-weight: 600;
        }
        .input-group {
            margin-bottom: 1.5rem;
            text-align: left;
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a5f3fc;
            z-index: 1;
        }
        input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 40px;
            color: white;
            font-size: 1rem;
            transition: 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #4f46e5;
            background: rgba(255,255,255,0.12);
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(79,70,229,0.4);
        }
        .alert {
            background: rgba(239,68,68,0.2);
            border-left: 3px solid #ef4444;
            padding: 10px;
            border-radius: 20px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        .loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(10px);
            z-index: 999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255,255,255,0.3);
            border-top: 5px solid #4f46e5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <canvas id="particles-canvas"></canvas>
    <div class="login-card">
        <i class="fas fa-microchip fa-3x text-primary mb-3"></i>
        <h2>CyberCenter</h2>
        <p class="text-white-50 mb-4">Gestión de tiempos y equipos</p>

        <?php if ($mensaje_alerta): ?>
            <div class="alert"><?php echo $mensaje_alerta; ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="usuario" placeholder="Correo institucional" required autofocus>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="clave" placeholder="Contraseña" required>
            </div>
            <button type="submit"><i class="fas fa-sign-in-alt"></i> Ingresar</button>
        </form>
    </div>

    <div id="loaderOverlay" class="loader">
        <div class="spinner"></div>
        <div class="loader-text">Redirigiendo al panel...</div>
    </div>

    <?php if ($login_exitoso): ?>
    <script>
        document.getElementById('loaderOverlay').style.display = 'flex';
        setTimeout(() => {
            window.location.href = 'src/index.php';
        }, 800);
    </script>
    <?php endif; ?>

    <script>
        // Animación de partículas (estilo cyber)
        const canvas = document.getElementById('particles-canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        let particles = [];
        const numParticles = 80;

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 3 + 1;
                this.speedX = (Math.random() - 0.5) * 1.5;
                this.speedY = (Math.random() - 0.5) * 1.5;
                this.color = `rgba(79, 70, 229, ${Math.random() * 0.5 + 0.2})`;
            }
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                if (this.x < 0) this.x = canvas.width;
                if (this.x > canvas.width) this.x = 0;
                if (this.y < 0) this.y = canvas.height;
                if (this.y > canvas.height) this.y = 0;
            }
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = this.color;
                ctx.fill();
            }
        }

        function init() {
            for (let i = 0; i < numParticles; i++) {
                particles.push(new Particle());
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (let p of particles) {
                p.update();
                p.draw();
            }
            requestAnimationFrame(animate);
        }

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            particles = [];
            init();
        });

        init();
        animate();
    </script>
</body>
</html>
