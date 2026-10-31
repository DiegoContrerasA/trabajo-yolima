<?php
// ==========================================
// CONFIGURACIÓN Y DATOS DEL FORMULARIO
// ==========================================

$url_flask = getenv('FLASK_URL') ?: 'http://127.0.0.1:5000/procesar';

$form = [
    'cedula'    => '',
    'nombre1'   => '',
    'nombre2'   => '',
    'apellido1' => '',
    'apellido2' => '',
    'telefono'  => '',
    'direccion' => ''
];

$mensaje = '';
$tipo_mensaje = '';
$accion_actual = '';

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// ==========================================
// PROCESAR LOS BOTONES DEL FORMULARIO
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion_actual = $_POST['accion'] ?? '';

    // Recoger los campos enviados
    foreach ($form as $campo => $valor) {
        $form[$campo] = trim($_POST[$campo] ?? '');
    }

    $acciones_validas = ['guardar', 'consultar', 'editar', 'eliminar'];

    if (!in_array($accion_actual, $acciones_validas, true)) {
        $mensaje = 'Acción no válida.';
        $tipo_mensaje = 'error';

    } elseif ($form['cedula'] === '') {
        $mensaje = 'Debes ingresar la cédula.';
        $tipo_mensaje = 'error';

    } elseif (
        in_array($accion_actual, ['guardar', 'editar'], true)
        && ($form['nombre1'] === '' || $form['apellido1'] === '')
    ) {
        $mensaje = 'El primer nombre y el primer apellido son obligatorios.';
        $tipo_mensaje = 'error';

    } else {

        // Preparar los datos para Flask
        $datos = $form;
        $datos['accion'] = $accion_actual;

        $json = json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            $mensaje = 'No se pudieron preparar los datos para enviar.';
            $tipo_mensaje = 'error';

        } else {

            // Enviar JSON a Flask mediante POST
            $opciones = [
                'http' => [
                    'method' => 'POST',
                    'header' =>
                        "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n",
                    'content' => $json,
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ];

            $contexto = stream_context_create($opciones);

            $resultado = @file_get_contents(
                $url_flask,
                false,
                $contexto
            );

            if ($resultado === false) {
                $mensaje =
                    'No se pudo conectar con Flask. ' .
                    'Verifica que Python esté ejecutándose en el puerto 5000.';
                $tipo_mensaje = 'error';

            } else {

                $respuesta = json_decode($resultado, true);

                if (!is_array($respuesta)) {
                    $mensaje =
                        'Flask no devolvió una respuesta JSON válida.';
                    $tipo_mensaje = 'error';

                } else {

                    $mensaje = $respuesta['mensaje']
                        ?? 'Respuesta recibida del servidor.';

                    // Consultar devuelve los datos del usuario
                    if (
                        $accion_actual === 'consultar'
                        && !empty($respuesta['datos'])
                        && is_array($respuesta['datos'])
                    ) {
                        foreach ($form as $campo => $valor) {
                            if (array_key_exists($campo, $respuesta['datos'])) {
                                $form[$campo] =
                                    (string)($respuesta['datos'][$campo] ?? '');
                            }
                        }
                    }

                    // Determinar si Flask respondió con error HTTP
                    $codigo_http = 0;

                    if (isset($http_response_header[0])) {
                        if (preg_match(
                            '/\s(\d{3})\s/',
                            $http_response_header[0],
                            $coincidencias
                        )) {
                            $codigo_http = (int)$coincidencias[1];
                        }
                    }

                    if ($codigo_http >= 400) {
                        $tipo_mensaje = 'error';
                    } else {
                        $tipo_mensaje = 'exito';
                    }

                    // Limpiar los campos después de eliminar correctamente
                    if (
                        $accion_actual === 'eliminar'
                        && $codigo_http >= 200
                        && $codigo_http < 300
                        && isset($respuesta['mensaje'])
                        && strpos(
                            $respuesta['mensaje'],
                            'Registro eliminado exitosamente.'
                        ) !== false
                    ) {
                        foreach ($form as $campo => $valor) {
                            $form[$campo] = '';
                        }
                    }
                }
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
    <title>Formulario de usuarios</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px 16px;
            background: #f0f0f0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
        }

        .contenedor {
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            padding: 16px;
            background: #dedede;
            border: 1px solid #aaa;
        }

        h1 {
            margin: 0 0 20px;
            padding: 10px 5px;
            background: white;
            border: 1px solid #aaa;
            text-align: center;
            font-size: 24px;
        }

        .campo {
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            margin-bottom: 9px;
        }

        label {
            font-size: 13px;
            font-weight: bold;
        }

        input {
            width: 100%;
            min-width: 0;
            height: 33px;
            padding: 5px 8px;
            background: #ffff00;
            border: 1px solid #bbb;
            font-size: 15px;
        }

        .botones {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }

        button {
            padding: 11px 15px;
            border: 0;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            opacity: .85;
        }

        .guardar {
            background: #00e600;
            color: #000;
        }

        .consultar {
            background: #087dc1;
        }

        .editar {
            background: #e6a000;
            color: #000;
        }

        .eliminar {
            background: #d93030;
        }

        .mensaje {
            margin: 0 0 16px;
            padding: 12px;
            border-radius: 4px;
            overflow-wrap: anywhere;
            font-size: 14px;
        }

        .exito {
            background: #d9f5d9;
            border: 1px solid #71b771;
        }

        .error {
            background: #ffe0e0;
            border: 1px solid #d88;
        }

        @media (max-width: 420px) {
            .campo {
                grid-template-columns: 95px minmax(0, 1fr);
                gap: 7px;
            }

            .contenedor {
                padding: 12px;
            }

            button {
                padding: 10px;
            }
        }
    </style>
</head>

<body>

<div class="contenedor">

    <h1>FORMULARIO</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje <?= h($tipo_mensaje) ?>">
            <?= h($mensaje) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="campo">
            <label for="cedula">CÉDULA</label>
            <input
                type="text"
                id="cedula"
                name="cedula"
                maxlength="20"
                value="<?= h($form['cedula']) ?>"
                required
            >
        </div>

        <div class="campo">
            <label for="nombre1">NOMBRE 1</label>
            <input
                type="text"
                id="nombre1"
                name="nombre1"
                maxlength="50"
                value="<?= h($form['nombre1']) ?>"
            >
        </div>

        <div class="campo">
            <label for="nombre2">NOMBRE 2</label>
            <input
                type="text"
                id="nombre2"
                name="nombre2"
                maxlength="50"
                value="<?= h($form['nombre2']) ?>"
            >
        </div>

        <div class="campo">
            <label for="apellido1">APELLIDO 1</label>
            <input
                type="text"
                id="apellido1"
                name="apellido1"
                maxlength="50"
                value="<?= h($form['apellido1']) ?>"
            >
        </div>

        <div class="campo">
            <label for="apellido2">APELLIDO 2</label>
            <input
                type="text"
                id="apellido2"
                name="apellido2"
                maxlength="50"
                value="<?= h($form['apellido2']) ?>"
            >
        </div>

        <div class="campo">
            <label for="telefono">TELÉFONO</label>
            <input
                type="text"
                id="telefono"
                name="telefono"
                maxlength="20"
                value="<?= h($form['telefono']) ?>"
            >
        </div>

        <div class="campo">
            <label for="direccion">DIRECCIÓN</label>
            <input
                type="text"
                id="direccion"
                name="direccion"
                maxlength="100"
                value="<?= h($form['direccion']) ?>"
            >
        </div>

        <div class="botones">
            <button
                type="submit"
                name="accion"
                value="guardar"
                class="guardar"
            >
                GUARDAR
            </button>

            <button
                type="submit"
                name="accion"
                value="consultar"
                class="consultar"
                formnovalidate
            >
                CONSULTAR
            </button>

            <button
                type="submit"
                name="accion"
                value="editar"
                class="editar"
                formnovalidate
            >
                EDITAR
            </button>

            <button
                type="submit"
                name="accion"
                value="eliminar"
                class="eliminar"
                formnovalidate
            >
                ELIMINAR
            </button>
        </div>

    </form>
</div>

</body>
</html>
