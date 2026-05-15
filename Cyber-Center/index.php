<?php
session_start();
if (!empty($_SESSION['active'])) { header('location: src/'); exit; }

$login_success = false; 
$nombre_usuario = "";

if (!empty($_POST)) {
    $alert = '';
    if (empty($_POST['usuario']) || empty($_POST['clave'])) {
        $alert = 'Ingrese su usuario y su clave';
    } else {
        require_once "conexion.php";
        $user = mysqli_real_escape_string($conexion, $_POST['usuario']);
        $clave = md5(mysqli_real_escape_string($conexion, $_POST['clave']));

        $query = mysqli_query($conexion, "SELECT * FROM usuario WHERE usuario = '$user' AND clave = '$clave' AND estado = 1");
        
        if (mysqli_num_rows($query) > 0) {
            $dato = mysqli_fetch_array($query);
            $_SESSION['active'] = true;
            $_SESSION['idUser'] = $dato['idusuario'];
            $_SESSION['nombre'] = $dato['nombre'];
            $_SESSION['user'] = $dato['usuario'];

            $nombre_usuario = $_SESSION['nombre'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $fyh = date('Y-m-d H:i:s');
            mysqli_query($conexion, "INSERT INTO historial(usuario, ip, fyh, sector, acciones) VALUES ('$nombre_usuario', '$ip', '$fyh', '$ip', 'Inicio de Sesión')");
            
            $login_success = true;
        } else {
            $alert = 'Usuario o Contraseña Incorrecta';
            session_destroy();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>UNERG | Sistema de Gestion</title>
    <link href="assets/css/styles.css" rel="stylesheet" />
    <script src="assets/js/all.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #09090b; font-family: 'Segoe UI', sans-serif; overflow: hidden; position: relative;
        }

        /* --- Fondo Animado --- */
        .circle { position: absolute; border-radius: 50%; filter: blur(80px); z-index: -1; animation: move 20s infinite alternate; }
        .c1 { width: 400px; height: 400px; background: #4facfe; top: -10%; left: -10%; }
        .c2 { width: 350px; height: 350px; background: #00f2fe; bottom: -5%; right: -5%; animation-delay: -5s; }
        @keyframes move { from { transform: translate(0, 0); } to { transform: translate(100px, 100px); } }

        /* --- Contenedor del Logo --- */
        .logo-wrapper {
            width: 100px; height: 100px; margin: 0 auto 20px;
            background: white; border-radius: 20px; display: flex;
            align-items: center; justify-content: center; overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            border: 3px solid #4facfe;
        }

        /* --- Tarjeta de Login --- */
        .login-box {
            background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1); padding: 40px; border-radius: 35px;
            width: 400px; text-align: center; box-shadow: 0 25px 45px rgba(0,0,0,0.5);
        }

        .input-box { position: relative; margin-bottom: 20px; text-align: left; }
        .input-box input {
            width: 100%; background: rgba(255,255,255,0.05); border: 1px solid transparent;
            outline: none; padding: 15px 20px 15px 45px; border-radius: 12px; color: #fff; transition: 0.3s;
        }
        .input-box input:focus { border: 1px solid #4facfe; background: rgba(255,255,255,0.1); }
        .input-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #4facfe; }

        .btn-submit {
            width: 100%; padding: 15px; border-radius: 12px; border: none;
            background: linear-gradient(45deg, #4facfe, #00f2fe); color: #fff;
            font-weight: 700; cursor: pointer; transition: 0.4s; text-transform: uppercase;
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(79, 172, 254, 0.4); }

        /* --- Loader --- */
        #loader-screen {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: #09090b; display: flex; flex-direction: column;
            align-items: center; justify-content: center; z-index: 100; display: none;
        }
        .progress-container { width: 250px; height: 4px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; margin-top: 20px; }
        .progress-bar { width: 0%; height: 100%; background: #4facfe; transition: width 0.1s; }
        
        .error-msg { color: #ff4757; background: rgba(255,71,87,0.1); padding: 10px; border-radius: 10px; margin-bottom: 15px; font-size: 0.8rem; }
    </style>
</head>
<body>

    <div class="circle c1"></div>
    <div class="circle c2"></div>

    <!-- Pantalla de Carga -->
    <div id="loader-screen">
        <div class="logo-wrapper">
             <img src="assets/img/logo.png" width="80" alt="Logo">
        </div>
        <div style="color: white; font-size: 1.4rem; font-weight: 600;">Bienvenido, <?php echo $nombre_usuario; ?></div>
        <div class="progress-container">
            <div class="progress-bar" id="bar"></div>
        </div>
    </div>

    <!-- Caja de Login -->
    <div class="login-box" id="login-form">
        <div class="logo-wrapper">
            <img src="assets/img/logo.png" width="80" alt="Logo">
        </div>
        
        <h2 style="color: white; margin-bottom: 5px;">¡Hola!</h2>
        <p style="color: #888; margin-bottom: 25px; font-size: 0.9rem;">Portal Administrativo UNERG</p>

        <form method="POST">
            <?php if(!empty($alert)): ?>
                <div class="error-msg"><?php echo $alert; ?></div>
            <?php endif; ?>
            <div class="input-box">
                <i class="fas fa-user"></i>
                <input type="text" name="usuario" placeholder="Usuario" required>
            </div>
            <div class="input-box">
                <i class="fas fa-lock"></i>
                <input type="password" name="clave" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn-submit">Entrar</button>
        </form>
        <noscript>
            <div class="error-msg" style="background: rgba(255,255,255,0.08); color: #fff;">
                JavaScript está deshabilitado. Si el inicio de sesión fue exitoso, haga clic <a href="src/" style="color: #4facfe; text-decoration: underline;">aquí</a> para continuar.
            </div>
        </noscript>
    </div>

    <script>
        <?php if($login_success): ?>
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('loader-screen').style.display = 'flex';
            
            let width = 0;
            let bar = document.getElementById('bar');
            let interval = setInterval(() => {
                if (width >= 100) {
                    clearInterval(interval);
                    window.location.href = 'src/';
                } else {
                    width += 4; // Un poco más rápido para mejorar la experiencia
                    bar.style.width = width + '%';
                }
            }, 60);
        <?php endif; ?>
    </script>

</body>
</html>
