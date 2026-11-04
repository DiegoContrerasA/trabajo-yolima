<?php
require_once __DIR__ . '/auth.php';

// Si ya inició sesión, ir directo al formulario
if (sesion_activa()) {
    header('Location: php.php');
    exit;
}

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = $_POST['clave'] ?? '';

    if ($usuario === '' || $clave === '') {
        $error = 'Debes ingresar usuario y contraseña.';
    } elseif (isset($USUARIOS[$usuario]) && password_verify($clave, $USUARIOS[$usuario])) {
        // Login correcto
        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;
        header('Location: php.php');
        exit;
    } else {
        sleep(1); // frena intentos repetidos
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; display: flex; justify-content: center; padding: 60px 16px; }
        .contenedor { width: 100%; max-width: 400px; background: #e0e0e0; border: 2px solid #ccc; padding: 15px; border-radius: 5px; }
        .titulo { text-align: center; font-size: 24px; font-weight: bold; background: white; padding: 10px; margin-bottom: 15px; border: 1px solid #aaa; }
        .fila { margin-bottom: 12px; }
        label { display: block; font-weight: bold; font-size: 14px; margin-bottom: 4px; }
        input { width: 100%; box-sizing: border-box; background-color: #ffff00; border: 1px solid #aaa; padding: 8px; font-size: 16px; }
        .btn { width: 100%; margin-top: 8px; padding: 11px; font-weight: bold; color: white; background-color: #007acc; border: none; cursor: pointer; border-radius: 3px; font-size: 15px; }
        .btn:hover { opacity: .85; }
        .alert { background: #ffb3b3; padding: 10px; margin-bottom: 12px; text-align: center; font-weight: bold; }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="titulo">INICIAR SESIÓN</div>

    <?php if ($error !== ''): ?>
        <div class="alert"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="fila">
            <label for="usuario">USUARIO</label>
            <input type="text" id="usuario" name="usuario" value="<?php echo h($usuario); ?>" autofocus required>
        </div>
        <div class="fila">
            <label for="clave">CONTRASEÑA</label>
            <input type="password" id="clave" name="clave" required>
        </div>
        <button type="submit" class="btn">ENTRAR</button>
    </form>
</div>
</body>
</html>
