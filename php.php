<?php
require_once __DIR__ . '/auth.php';
requerir_login();   // si no hay sesión, redirige al login

// URL completa del servicio Flask (antes estaba incompleta)
$url_flask = getenv('FLASK_URL') ?: 'http://127.0.0.1:5000/procesar';

// Inicializar variables para los campos
$cedula = $n1 = $n2 = $a1 = $a2 = $tel = $dir = "";
$mensaje = "";
$tipo_mensaje = "exito";

// Escapar texto para mostrarlo de forma segura en HTML
function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Procesar el formulario al hacer clic en un botón
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // Recopilar datos del formulario
    $datos = [
        'accion'    => $accion,
        'cedula'    => trim($_POST['cedula'] ?? ''),
        'nombre1'   => trim($_POST['nombre1'] ?? ''),
        'nombre2'   => trim($_POST['nombre2'] ?? ''),
        'apellido1' => trim($_POST['apellido1'] ?? ''),
        'apellido2' => trim($_POST['apellido2'] ?? ''),
        'telefono'  => trim($_POST['telefono'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? '')
    ];

    // Conservar lo que el usuario escribió en los campos
    $cedula = $datos['cedula'];
    $n1  = $datos['nombre1'];
    $n2  = $datos['nombre2'];
    $a1  = $datos['apellido1'];
    $a2  = $datos['apellido2'];
    $tel = $datos['telefono'];
    $dir = $datos['direccion'];

    // Enviar datos a Flask mediante JSON
    $options = [
        'http' => [
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'method'        => 'POST',
            'content'       => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'timeout'       => 10,
            'ignore_errors' => true   // permite leer la respuesta aunque Flask devuelva 400, 409, etc.
        ],
    ];

    $context   = stream_context_create($options);
    $resultado = @file_get_contents($url_flask, false, $context);

    if ($resultado === false) {
        $mensaje = 'No se pudo conectar con Flask. Verifica que el servicio Python (app.py) esté ejecutándose en el puerto 5000.';
        $tipo_mensaje = 'error';
    } else {
        $respuesta_json = json_decode($resultado, true);

        if (!is_array($respuesta_json)) {
            $mensaje = 'Flask no devolvió una respuesta JSON válida.';
            $tipo_mensaje = 'error';
        } else {
            $mensaje = $respuesta_json['mensaje'] ?? 'Respuesta recibida del servidor.';

            // Código HTTP de la respuesta de Flask
            $codigo_http = 0;
            if (isset($http_response_header[0]) &&
                preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $codigo_http = (int)$m[1];
            }
            $tipo_mensaje = ($codigo_http >= 400) ? 'error' : 'exito';

            // CONSULTAR: rellenar los campos con los datos encontrados
            if ($accion === 'consultar' && !empty($respuesta_json['datos']) && is_array($respuesta_json['datos'])) {
                $d = $respuesta_json['datos'];
                $cedula = $d['cedula']    ?? '';
                $n1     = $d['nombre1']   ?? '';
                $n2     = $d['nombre2']   ?? '';
                $a1     = $d['apellido1'] ?? '';
                $a2     = $d['apellido2'] ?? '';
                $tel    = $d['telefono']  ?? '';
                $dir    = $d['direccion'] ?? '';
            }

            // ELIMINAR: limpiar los campos si se eliminó correctamente
            if ($accion === 'eliminar' && $tipo_mensaje === 'exito' &&
                strpos($mensaje, 'eliminado exitosamente') !== false) {
                $cedula = $n1 = $n2 = $a1 = $a2 = $tel = $dir = "";
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
    <title>Formulario de Registro</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; display: flex; justify-content: center; padding: 20px; }
        .contenedor { width: 700px; max-width: 100%; background: #e0e0e0; border: 2px solid #ccc; padding: 15px; border-radius: 5px; }
        .titulo { text-align: center; font-size: 24px; font-weight: bold; background: white; padding: 10px; margin-bottom: 15px; border: 1px solid #aaa; }
        .fila { display: flex; margin-bottom: 8px; align-items: center; }
        .label { width: 120px; font-weight: bold; font-size: 14px; }
        .input-amarillo { flex-grow: 1; background-color: #ffff00; border: 1px solid #aaa; padding: 6px; font-size: 16px; }
        .panel-botones { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; margin-top: 20px; }
        .btn { padding: 10px 15px; font-weight: bold; color: white; border: none; cursor: pointer; flex-grow: 1; margin: 0 4px; border-radius: 3px; }
        .btn:hover { opacity: .85; }
        .btn-guardar { background-color: #00ff00; color: black; }
        .btn-consultar { background-color: #007acc; }
        .btn-editar { background-color: #ff6600; }
        .btn-eliminar { background-color: #ff3333; }
        .btn-pdf { background-color: #7b3fc4; }
        .btn-excel { background-color: #1d7a3a; }
        .btn-salir { background-color: white; color: black; border: 1px solid #aaa; }
        .alert { padding: 10px; margin-bottom: 10px; text-align: center; font-weight: bold; overflow-wrap: anywhere; }
        .alert.exito { background: #b3ffb3; }
        .alert.error { background: #ffb3b3; }
    </style>
</head>
<body>

<div class="contenedor">
    <div class="titulo">FORMULARIO DE REGISTRO</div>
    <div style="text-align:right; font-size:13px; margin-bottom:10px;">
        Usuario: <strong><?php echo h($_SESSION['usuario']); ?></strong> |
        <a href="logout.php">Cerrar sesión</a>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert <?php echo h($tipo_mensaje); ?>"><?php echo h($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="fila">
            <span class="label">CEDULA</span>
            <input type="text" name="cedula" class="input-amarillo" value="<?php echo h($cedula); ?>" required>
        </div>
        <div class="fila">
            <span class="label">NOMBRE 1</span>
            <input type="text" name="nombre1" class="input-amarillo" value="<?php echo h($n1); ?>">
        </div>
        <div class="fila">
            <span class="label">NOMBRE 2</span>
            <input type="text" name="nombre2" class="input-amarillo" value="<?php echo h($n2); ?>">
        </div>
        <div class="fila">
            <span class="label">APELLIDO 1</span>
            <input type="text" name="apellido1" class="input-amarillo" value="<?php echo h($a1); ?>">
        </div>
        <div class="fila">
            <span class="label">APELLIDO 2</span>
            <input type="text" name="apellido2" class="input-amarillo" value="<?php echo h($a2); ?>">
        </div>
        <div class="fila">
            <span class="label">TELEFONO</span>
            <input type="text" name="telefono" class="input-amarillo" value="<?php echo h($tel); ?>">
        </div>
        <div class="fila">
            <span class="label">DIRECCION</span>
            <input type="text" name="direccion" class="input-amarillo" value="<?php echo h($dir); ?>">
        </div>

        <div class="panel-botones">
            <button type="submit" name="accion" value="guardar" class="btn btn-guardar">GUARDAR</button>
            <button type="submit" name="accion" value="consultar" class="btn btn-consultar" formnovalidate>CONSULTAR</button>
            <button type="submit" name="accion" value="editar" class="btn btn-editar" formnovalidate>EDITAR</button>
            <button type="submit" name="accion" value="eliminar" class="btn btn-eliminar" formnovalidate>ELIMINAR</button>
            <button type="submit" formaction="imprimir.php" formmethod="post" formtarget="_blank" formnovalidate class="btn btn-pdf">IMPRIMIR PDF</button>
            <button type="submit" formaction="exportar.php" formmethod="get" formnovalidate class="btn btn-excel">EXCEL</button>
            <button type="button" class="btn btn-salir" onclick="window.location.href = window.location.pathname;">SALIR</button>
        </div>
    </form>
</div>

</body>
</html>
