<?php
/* ============================================================
   BACANO.MAIL · Ajustes de la cuenta
   Nombre con el que sales en los correos, y firma.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/cuenta.php';
require_once __DIR__ . '/inc/cpanel.php';
require_once __DIR__ . '/inc/cuentas.php';
require_once __DIR__ . '/inc/pagina.php';

$cfg = mj_config();
if (!mj_exigir_acceso($cfg)) { exit; }

$cred = mj_credenciales();
if ($cred === null) {
    header('Location: ./');
    exit;
}

$correo = $cred['usuario'];
$datos  = mj_cuenta($correo);
$aviso  = '';

$avisoClave = '';
$claveOk    = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!mj_token_valido($_POST['token'] ?? null)) {
        $aviso = 'La página estuvo demasiado tiempo abierta. Inténtalo otra vez.';
    } elseif (($_POST['que'] ?? '') === 'clave') {
        [$avisoClave, $claveOk] = mj_cambiar_clave_casilla($cfg, $correo, $_POST);
    } else {
        $datos['nombre'] = (string) ($_POST['nombre'] ?? '');
        $datos['firma']  = (string) ($_POST['firma'] ?? '');
        $aviso = mj_cuenta_guardar($correo, $datos)
            ? 'Guardado.'
            : 'No se pudo guardar: revisa los permisos de la carpeta data.';
        $datos = mj_cuenta($correo);
    }
}

/**
 * Cambia la contraseña de la casilla en el hosting y deja la sesión al día.
 * Devuelve [mensaje, ok].
 */
function mj_cambiar_clave_casilla(array $cfg, string $correo, array $post): array
{
    $actual = (string) ($post['clave_actual'] ?? '');
    $nueva  = (string) ($post['clave_nueva'] ?? '');
    $otra   = (string) ($post['clave_repite'] ?? '');

    if ($actual === '' || $nueva === '')  return ['Rellena la contraseña de ahora y la nueva.', false];
    if ($nueva !== $otra)                 return ['La contraseña nueva no coincide con la repetida.', false];
    if (mb_strlen($nueva) < 10)           return ['Usa al menos 10 caracteres.', false];
    if ($nueva === $actual)               return ['La contraseña nueva es la misma de ahora.', false];

    // La de ahora se comprueba contra el servidor: sin eso, cualquiera que
    // pillara la sesión abierta podría cambiarla sin saberla.
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
        return ['La contraseña de ahora no es correcta.', false];
    }
    $imap->cerrar();

    $r = mj_cpanel_cambiar_clave($cfg, $correo, $nueva);
    if (!$r['ok']) {
        return ['No se pudo cambiar: ' . $r['mensaje'], false];
    }

    // La sesión seguía con la vieja: sin esto, la bandeja dejaría de abrir.
    $_SESSION['mj_clave'] = $nueva;

    if (!empty($_COOKIE[MJ_RECUERDO_COOKIE] ?? '')) {
        mj_recuerdo_borrar((string) $_COOKIE[MJ_RECUERDO_COOKIE]);
        $token = mj_recuerdo_crear($correo, $nueva);
        if ($token !== '') { mj_recuerdo_cookie($token); }
    }
    if (mj_cuenta_clave($correo) !== null) {
        mj_cuenta_recordar($correo, $nueva);
    }

    return ['Contraseña cambiada. Acuérdate de actualizarla también en el celular y en cualquier otro programa de correo.', true];
}
?>
<?php mj_pagina_abrir($cfg, 'Tu cuenta', 'Así te verán quienes reciban tus correos.'); ?>

  <?php if ($aviso !== ''): ?>
    <p class="mj-nota"><?= mj_e($aviso) ?></p>
  <?php endif; ?>

  <form method="post" class="mj-tarjeta">
    <input type="hidden" name="token" value="<?= mj_e(mj_token_sesion()) ?>">

    <label class="mj-campo">
      <span>Dirección</span>
      <input type="text" value="<?= mj_e($correo) ?>" readonly>
    </label>
    <p class="mj-pista">Es la casilla con la que entraste. Para usar otra, cámbiala desde tu nombre, al pie del menú.</p>

    <label class="mj-campo">
      <span>Nombre</span>
      <input type="text" name="nombre" maxlength="80" autocomplete="off"
             value="<?= mj_e($datos['nombre']) ?>" placeholder="Lorena Nahuelquín">
    </label>

    <label class="mj-campo mj-campo-area">
      <span>Firma</span>
      <textarea name="firma" rows="4" maxlength="500"
                placeholder="Lorena Nahuelquín&#10;Abogada"><?= mj_e($datos['firma']) ?></textarea>
    </label>
    <p class="mj-pista">Se agrega al final de los correos que envíes.</p>

    <p class="mj-previo">
      Se verá como <strong><?= mj_e($datos['nombre'] ?: strtok($correo, '@')) ?></strong>
      &lt;<?= mj_e($correo) ?>&gt;
    </p>

    <div class="mj-modal-pie">
      <div class="mj-modal-pie-btns">
        <a class="mj-btn mj-btn-2" href="./">Volver a la bandeja</a>
        <button class="mj-btn" type="submit">Guardar</button>
      </div>
    </div>
  </form>

  <h2 class="mj-hoja-t2">Contraseña de la casilla</h2>
  <p class="mj-hoja-d">La que usas para entrar aquí y en cualquier programa de correo.</p>

  <?php if ($avisoClave !== ''): ?>
    <p class="mj-nota <?= $claveOk ? 'mj-nota-bien' : 'mj-nota-mal' ?>"><?= mj_e($avisoClave) ?></p>
  <?php endif; ?>

  <?php if (!mj_cpanel_listo($cfg)): ?>
    <section class="mj-tarjeta">
      <p class="mj-pista mj-pista-sola">
        Para cambiar la contraseña hace falta conectar el panel del hosting: por IMAP
        no se puede, sólo el panel manda sobre las casillas. Se configura en
        <a href="instalar.php">instalar.php</a>, en «Panel del hosting».
      </p>
    </section>
  <?php else: ?>
    <form method="post" class="mj-tarjeta">
      <input type="hidden" name="token" value="<?= mj_e(mj_token_sesion()) ?>">
      <input type="hidden" name="que" value="clave">

      <label class="mj-campo">
        <span>Ahora</span>
        <input type="password" name="clave_actual" autocomplete="current-password" required>
      </label>

      <label class="mj-campo">
        <span>Nueva</span>
        <input type="password" name="clave_nueva" autocomplete="new-password" minlength="10" required>
      </label>

      <label class="mj-campo">
        <span>Repite</span>
        <input type="password" name="clave_repite" autocomplete="new-password" minlength="10" required>
      </label>
      <p class="mj-pista">Al menos 10 caracteres. Mejor larga que complicada.</p>

      <p class="mj-previo mj-previo-ojo">
        Cambia la contraseña de <strong><?= mj_e($correo) ?></strong> en el servidor.
        Después hay que actualizarla en el celular y en cualquier otro programa que
        abra esta casilla.
      </p>

      <div class="mj-modal-pie">
        <div class="mj-modal-pie-btns">
          <button class="mj-btn mj-btn-peligro" type="submit">Cambiar la contraseña</button>
        </div>
      </div>
    </form>
  <?php endif; ?>

<?php mj_pagina_cerrar(); ?>
