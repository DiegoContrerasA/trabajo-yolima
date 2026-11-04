<?php
require_once __DIR__ . '/auth.php';
requerir_login();

$url_flask = getenv('FLASK_URL') ?: 'http://127.0.0.1:5000/procesar';

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$error = '';
$d = null;
$cedula = trim($_POST['cedula'] ?? '');

if ($cedula === '') {
    $error = 'Debes ingresar la cédula para imprimir.';
} else {
    $json = json_encode(['accion' => 'consultar', 'cedula' => $cedula], JSON_UNESCAPED_UNICODE);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $json,
        'timeout' => 10,
        'ignore_errors' => true
    ]]);
    $resultado = @file_get_contents($url_flask, false, $ctx);

    if ($resultado === false) {
        $error = 'No se pudo conectar con Flask. Verifica que el servicio Python (app.py) esté ejecutándose.';
    } else {
        $resp = json_decode($resultado, true);
        if (is_array($resp) && !empty($resp['datos']) && is_array($resp['datos'])) {
            $d = $resp['datos'];
        } else {
            $error = is_array($resp) && isset($resp['mensaje']) ? $resp['mensaje'] : 'No se pudo consultar el registro.';
        }
    }
}

$filas = [
    'Cédula'     => $d['cedula']    ?? '',
    'Nombre 1'   => $d['nombre1']   ?? '',
    'Nombre 2'   => $d['nombre2']   ?? '',
    'Apellido 1' => $d['apellido1'] ?? '',
    'Apellido 2' => $d['apellido2'] ?? '',
    'Teléfono'   => $d['telefono']  ?? '',
    'Dirección'  => $d['direccion'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de usuario <?php echo h($cedula); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 30px; color: #111; }
        .hoja { max-width: 700px; margin: 0 auto; }
        h1 { text-align: center; font-size: 24px; margin: 0 0 6px; }
        .fecha { text-align: center; font-size: 13px; color: #555; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #888; padding: 10px 12px; font-size: 15px; text-align: left; }
        th { width: 35%; background: #eee; }
        .acciones { text-align: center; margin-top: 24px; }
        button { padding: 10px 18px; font-weight: bold; border: 0; border-radius: 4px; background: #007acc; color: white; cursor: pointer; margin: 0 4px; }
        button.gris { background: #777; }
        .error { background: #ffb3b3; padding: 14px; text-align: center; font-weight: bold; }
        .ayuda { font-size: 12px; color: #666; text-align: center; margin-top: 10px; }
        @media print {
            .acciones, .ayuda { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
<div class="hoja">
    <h1>FICHA DE USUARIO</h1>
    <div class="fecha">Generado el <?php echo date('d/m/Y H:i'); ?></div>

    <?php if ($error !== ''): ?>
        <div class="error"><?php echo h($error); ?></div>
        <div class="acciones"><button class="gris" onclick="window.close()">Cerrar</button></div>
    <?php else: ?>
        <table>
            <?php foreach ($filas as $etiqueta => $valor): ?>
                <tr><th><?php echo h($etiqueta); ?></th><td><?php echo h($valor); ?></td></tr>
            <?php endforeach; ?>
        </table>

        <div class="acciones">
            <button onclick="window.print()">IMPRIMIR / GUARDAR PDF</button>
            <button class="gris" onclick="window.close()">Cerrar</button>
        </div>
        <div class="ayuda">En la ventana de impresión elige "Guardar como PDF" como destino.</div>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</div>
</body>
</html>
