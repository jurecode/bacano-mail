<?php
/* ============================================================
   BACANO.MAIL · Guardar los ajustes de la cuenta
   ------------------------------------------------------------
   Responde a la vista de ajustes que vive dentro de la ventana.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/cuenta.php';
require_once __DIR__ . '/inc/cpanel.php';
require_once __DIR__ . '/inc/cuentas.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = mj_config();

$responder = static function (bool $ok, string $mensaje, int $codigo = 200, array $extra = []): void {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};

if (!empty($cfg['acceso']['proteger']) && !mj_dentro()) {
    $responder(false, 'Tu sesión se cerró. Vuelve a entrar a la bandeja.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $responder(false, 'Método no permitido.', 405);
}
if (!mj_token_valido($_POST['token'] ?? null)) {
    $responder(false, 'Recarga la página e inténtalo otra vez.', 419);
}

$correo = mj_buzon_actual($cfg);
if ($correo === '') {
    $responder(false, 'No se sabe de qué casilla son los ajustes.', 409);
}

$que = (string) ($_POST['que'] ?? '');

/* --- nombre y firma --- */
if ($que === 'perfil') {
    $datos = mj_cuenta($correo);
    $datos['nombre'] = (string) ($_POST['nombre'] ?? '');
    $datos['firma']  = (string) ($_POST['firma'] ?? '');

    if (!mj_cuenta_guardar($correo, $datos)) {
        $responder(false, 'No se pudo guardar: revisa los permisos de la carpeta data.');
    }
    $guardado = mj_cuenta($correo);
    $responder(true, 'Guardado', 200, [
        'nombre' => $guardado['nombre'] ?: (strtok($correo, '@') ?: $correo),
    ]);
}

/* --- contraseña de la casilla --- */
if ($que === 'clave') {
    $actual = (string) ($_POST['clave_actual'] ?? '');
    $nueva  = (string) ($_POST['clave_nueva'] ?? '');
    $otra   = (string) ($_POST['clave_repite'] ?? '');

    if ($actual === '' || $nueva === '') $responder(false, 'Rellena la contraseña de ahora y la nueva.');
    if ($nueva !== $otra)                $responder(false, 'La contraseña nueva no coincide con la repetida.');
    if (mb_strlen($nueva) < 10)          $responder(false, 'Usa al menos 10 caracteres.');
    if ($nueva === $actual)              $responder(false, 'La contraseña nueva es la misma de ahora.');

    // La de ahora se comprueba contra el servidor: sin eso, quien pillara una
    // sesión abierta podría cambiarla sin saberla.
    require_once __DIR__ . '/inc/imap-cliente.php';
    $imap = new MjImap([
        'host'    => (string) ($cfg['origen']['imap']['host'] ?? ''),
        'puerto'  => (int) ($cfg['origen']['imap']['puerto'] ?? 993),
        'cifrado' => (string) ($cfg['origen']['imap']['cifrado'] ?? 'ssl'),
        'validar_certificado' => !empty($cfg['origen']['imap']['validar_certificado']),
        'usuario' => $correo,
        'clave'   => $actual,
    ]);
    if (!$imap->conectar() || !$imap->entrar()) {
        $responder(false, 'La contraseña de ahora no es correcta.');
    }
    $imap->cerrar();

    $r = mj_cpanel_cambiar_clave($cfg, $correo, $nueva);
    if (!$r['ok']) {
        $responder(false, 'No se pudo cambiar: ' . $r['mensaje']);
    }

    // La sesión seguía con la vieja: sin esto la bandeja dejaría de abrir.
    $_SESSION['mj_clave'] = $nueva;

    if (!empty($_COOKIE[MJ_RECUERDO_COOKIE] ?? '')) {
        mj_recuerdo_borrar((string) $_COOKIE[MJ_RECUERDO_COOKIE]);
        $token = mj_recuerdo_crear($correo, $nueva);
        if ($token !== '') { mj_recuerdo_cookie($token); }
    }
    if (mj_cuenta_clave($correo) !== null) {
        mj_cuenta_recordar($correo, $nueva);
    }

    $responder(true, 'Contraseña cambiada. Acuérdate de actualizarla también en el celular.');
}

$responder(false, 'Acción desconocida.');
