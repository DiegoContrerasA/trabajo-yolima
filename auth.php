<?php
// ==========================================
// AUTENTICACIÓN (usuarios y sesión)
// ==========================================

session_start();

// Usuarios permitidos: 'usuario' => 'hash de la contraseña'
// Usuario inicial:  admin  /  admin123   (cámbialo, ver instrucciones)
$USUARIOS = [
    'admin' => '$2y$10$KvIWwvySQO1ifdzgkrScmO.l9MDRpDkhDLJ99D.Ow/Yf2E9m4YVgK',
];

// Devuelve true si el usuario ya inició sesión
function sesion_activa() {
    return !empty($_SESSION['usuario']);
}

// Si no hay sesión, manda al login y detiene la página
function requerir_login() {
    if (!sesion_activa()) {
        header('Location: login.php');
        exit;
    }
}
