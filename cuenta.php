<?php
/* ============================================================
   BACANO.MAIL · Ajustes de la cuenta
   Nombre con el que sales en los correos, y firma.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/inc/acceso.php';
require __DIR__ . '/correo.php';
require_once __DIR__ . '/inc/cuenta.php';

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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!mj_token_valido($_POST['token'] ?? null)) {
        $aviso = 'La página estuvo demasiado tiempo abierta. Inténtalo otra vez.';
    } else {
        $datos['nombre'] = (string) ($_POST['nombre'] ?? '');
        $datos['firma']  = (string) ($_POST['firma'] ?? '');
        $aviso = mj_cuenta_guardar($correo, $datos)
            ? 'Guardado.'
            : 'No se pudo guardar: revisa los permisos de la carpeta data.';
        $datos = mj_cuenta($correo);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tu cuenta · <?= mj_e($cfg['marca']['nombre']) ?></title>
<meta name="robots" content="noindex, nofollow">
<style>
  :root{ color-scheme: dark }
  *{ box-sizing:border-box }
  body{
    margin:0; min-height:100vh; display:grid; place-items:start center;
    background:#0b1220; color:#e8eaf0; padding:40px 20px;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  }
  .caja{ width:100%; max-width:520px }
  h1{ font-size:22px; margin:0 0 4px }
  .sub{ margin:0 0 24px; font-size:13px; color:#93a0b8 }
  .tarjeta{ padding:24px; background:#111a2b; border:1px solid rgba(255,255,255,.10); border-radius:16px }
  label{ display:block; font-size:12px; color:#93a0b8; margin:16px 0 6px }
  label:first-of-type{ margin-top:0 }
  input,textarea{
    width:100%; padding:11px 12px; border-radius:9px; font-size:15px;
    background:#0b1220; border:1px solid rgba(255,255,255,.14); color:#e8eaf0;
    font-family:inherit; resize:vertical;
  }
  input:focus,textarea:focus{ outline:none; border-color:#3b82f6 }
  .fijo{ opacity:.55 }
  .ayuda{ margin-top:6px; font-size:12px; color:#93a0b8 }
  .acciones{ display:flex; gap:10px; align-items:center; margin-top:22px }
  button{
    padding:11px 20px; border:0; border-radius:9px; cursor:pointer;
    background:#3b82f6; color:#fff; font-size:14px; font-weight:600;
  }
  button:hover{ background:#2f6fd8 }
  a.volver{ color:#93a0b8; font-size:14px; text-decoration:none }
  a.volver:hover{ color:#e8eaf0 }
  .aviso{
    margin-bottom:18px; padding:10px 12px; border-radius:9px; font-size:13px;
    background:rgba(59,130,246,.14); border:1px solid rgba(59,130,246,.35);
  }
  .previo{
    margin-top:18px; padding:12px 14px; border-radius:9px; font-size:13px;
    background:#0b1220; border:1px dashed rgba(255,255,255,.16); color:#93a0b8;
  }
  .previo b{ color:#e8eaf0 }
</style>
</head>
<body>
  <div class="caja">
    <h1>Tu cuenta</h1>
    <p class="sub">Así te verán quienes reciban tus correos.</p>

    <?php if ($aviso !== ''): ?><div class="aviso"><?= mj_e($aviso) ?></div><?php endif; ?>

    <form method="post" class="tarjeta">
      <input type="hidden" name="token" value="<?= mj_e(mj_token_sesion()) ?>">

      <label for="c">Dirección</label>
      <input type="text" id="c" class="fijo" value="<?= mj_e($correo) ?>" readonly>
      <p class="ayuda">Es la casilla con la que entraste. Para usar otra, cierra sesión.</p>

      <label for="n">Nombre que aparece</label>
      <input type="text" id="n" name="nombre" maxlength="80"
             value="<?= mj_e($datos['nombre']) ?>" placeholder="Lorena Nahuelquín">

      <label for="f">Firma</label>
      <textarea id="f" name="firma" rows="4" maxlength="500"
                placeholder="Lorena Nahuelquín&#10;Abogada"><?= mj_e($datos['firma']) ?></textarea>
      <p class="ayuda">Se agrega al final de los correos que envíes.</p>

      <div class="previo">
        Se verá como: <b><?= mj_e($datos['nombre'] ?: strtok($correo, '@')) ?></b>
        &lt;<?= mj_e($correo) ?>&gt;
      </div>

      <div class="acciones">
        <button type="submit">Guardar</button>
        <a class="volver" href="./">Volver a la bandeja</a>
      </div>
    </form>
  </div>
</body>
</html>
