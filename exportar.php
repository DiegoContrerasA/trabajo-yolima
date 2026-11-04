<?php
require_once __DIR__ . '/auth.php';
requerir_login();

$url_listar = getenv('FLASK_LISTAR_URL') ?: 'http://127.0.0.1:5000/listar';

function error_pagina($texto) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body style="font-family:Arial;padding:30px">';
    echo '<h2>No se pudo generar el Excel</h2><p>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="php.php">Volver al formulario</a></p></body></html>';
    exit;
}

// Pedir todos los registros a Flask
$ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true]]);
$resultado = @file_get_contents($url_listar, false, $ctx);

if ($resultado === false) {
    error_pagina('No se pudo conectar con Flask. Verifica que el servicio Python (app.py) esté ejecutándose.');
}

$respuesta = json_decode($resultado, true);

if (!is_array($respuesta) || !isset($respuesta['datos']) || !is_array($respuesta['datos'])) {
    error_pagina($respuesta['mensaje'] ?? 'Flask no devolvió datos válidos. ¿Reiniciaste app.py con la versión nueva?');
}

$filas = $respuesta['datos'];

$encabezados = ['CÉDULA', 'NOMBRE 1', 'NOMBRE 2', 'APELLIDO 1', 'APELLIDO 2', 'TELÉFONO', 'DIRECCIÓN'];
$campos      = ['cedula', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'telefono', 'direccion'];
$anchos      = [16, 16, 16, 16, 16, 16, 40];

// Limpiar texto para XML
function x($v) {
    $v = (string)$v;
    $v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $v);
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function letra($i) { return chr(65 + $i); } // A..G

$nombre = 'usuarios_' . date('Y-m-d_His');

// ---------- Excel real (.xlsx) ----------
if (class_exists('ZipArchive')) {

    $sheet  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheet .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    $sheet .= '<cols>';
    foreach ($anchos as $i => $w) {
        $sheet .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $sheet .= '</cols><sheetData>';

    // Fila de encabezados (estilo 1 = negrita con fondo)
    $sheet .= '<row r="1">';
    foreach ($encabezados as $i => $t) {
        $sheet .= '<c r="' . letra($i) . '1" t="inlineStr" s="1"><is><t>' . x($t) . '</t></is></c>';
    }
    $sheet .= '</row>';

    // Filas de datos (todo como texto para no perder ceros a la izquierda)
    $r = 2;
    foreach ($filas as $fila) {
        $sheet .= '<row r="' . $r . '">';
        foreach ($campos as $i => $campo) {
            $sheet .= '<c r="' . letra($i) . $r . '" t="inlineStr"><is><t xml:space="preserve">' . x($fila[$campo] ?? '') . '</t></is></c>';
        }
        $sheet .= '</row>';
        $r++;
    }

    $ultima = max(1, $r - 1);
    $sheet .= '</sheetData><autoFilter ref="A1:G' . $ultima . '"/></worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Usuarios" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFFF00"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();

    if ($zip->open($tmp, ZipArchive::OVERWRITE) === true) {
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombre . '.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-store');
        readfile($tmp);
        unlink($tmp);
        exit;
    }
    @unlink($tmp);
}

// ---------- Plan B: CSV (Excel lo abre igual) si no existe la extensión zip ----------
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre . '.csv"');
echo "\xEF\xBB\xBF";                       // BOM para que Excel lea bien las tildes
$out = fopen('php://output', 'w');
fputcsv($out, $encabezados, ';');
foreach ($filas as $fila) {
    $linea = [];
    foreach ($campos as $campo) {
        $linea[] = $fila[$campo] ?? '';
    }
    fputcsv($out, $linea, ';');
}
fclose($out);
exit;
