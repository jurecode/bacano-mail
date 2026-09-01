<?php
/* ============================================================
   BACANO.MAIL · Acceso a la bandeja
   ------------------------------------------------------------
   La casilla es privada: sin clave no se entra. Se usa la misma
   clave de administración que se define en instalar.php.
   Al incrustar el módulo en una página que ya tiene su propio
   control de acceso, se puede desactivar con acceso.proteger.
   ============================================================ */

declare(strict_types=1);

function mj_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('bacano_mail');
        session_start();
    }
}

function mj_dentro(): bool
{
    mj_sesion();
    return !empty($_SESSION['mj_acceso']);
}

function mj_salir(): void
{
    mj_sesion();
    $_SESSION = [];
    session_destroy();
}

/** Token para los formularios que hacen algo (enviar, por ejemplo). */
function mj_token_sesion(): string
{
    mj_sesion();
    if (empty($_SESSION['mj_token'])) {
        $_SESSION['mj_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['mj_token'];
}

function mj_token_valido(?string $token): bool
{
    mj_sesion();
    return is_string($token) && !empty($_SESSION['mj_token'])
        && hash_equals($_SESSION['mj_token'], $token);
}

/** La casilla con la que se entró, si la sesión es de tipo correo. */
function mj_credenciales(): ?array
{
    mj_sesion();
    if (empty($_SESSION['mj_usuario']) || !isset($_SESSION['mj_clave'])) {
        return null;
    }
    return ['usuario' => (string) $_SESSION['mj_usuario'], 'clave' => (string) $_SESSION['mj_clave']];
}

/**
 * Aplica al config la casilla con la que se entró: se lee y se envía con
 * esa cuenta, sin necesidad de dejar la clave guardada en el servidor.
 */
function mj_aplicar_credenciales(array $cfg): array
{
    $cred = mj_credenciales();
    if ($cred === null) {
        return $cfg;
    }

    $cfg['origen']['tipo'] = 'imap';
    $cfg['origen']['imap']['usuario'] = $cred['usuario'];
    $cfg['origen']['imap']['clave']   = $cred['clave'];

    $cfg['origen']['smtp']['usuario'] = $cred['usuario'];
    $cfg['origen']['smtp']['clave']   = $cred['clave'];
    if (trim((string) ($cfg['origen']['smtp']['desde'] ?? '')) === '') {
        $cfg['origen']['smtp']['desde'] = $cred['usuario'];
    }
    if (trim((string) ($cfg['origen']['smtp']['host'] ?? '')) === '') {
        $cfg['origen']['smtp']['host'] = (string) ($cfg['origen']['imap']['host'] ?? '');
    }

    $cfg['usuario']['email'] = $cred['usuario'];
    if (($cfg['usuario']['nombre'] ?? '') === 'Mi cuenta') {
        $cfg['usuario']['nombre'] = ucfirst(strtok($cred['usuario'], '@') ?: 'Mi cuenta');
    }
    return $cfg;
}

/**
 * Puerta de entrada. Si no hay sesión, dibuja el acceso y corta la página.
 * Devuelve true cuando se puede seguir.
 */
function mj_exigir_acceso(array $cfg): bool
{
    if (empty($cfg['acceso']['proteger'])) {
        return true;                       // la página anfitriona se hace cargo
    }

    mj_sesion();

    if (isset($_GET['salir'])) {
        mj_salir();
        header('Location: ./');
        exit;
    }
    if (mj_dentro()) {
        return true;
    }

    $modo = ($cfg['acceso']['modo'] ?? 'casilla') === 'clave' ? 'clave' : 'casilla';
    $hash = (string) ($cfg['admin']['clave_hash'] ?? '');
    $servidor = trim((string) ($cfg['origen']['imap']['host'] ?? ''));

    if ($modo === 'casilla' && $servidor === '') {
        mj_pantalla_acceso($cfg, 'Falta indicar el servidor IMAP en instalar.php.', true, $modo);
        return false;
    }
    if ($modo === 'clave' && $hash === '') {
        mj_pantalla_acceso($cfg, 'Todavía no defines la clave. Ábrela en instalar.php.', true, $modo);
        return false;
    }

    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['mj_clave'])) {
        usleep(400000);                    // pequeño freno contra la fuerza bruta
        $clave = (string) $_POST['mj_clave'];

        if ($modo === 'clave') {
            if (password_verify($clave, $hash)) {
                session_regenerate_id(true);
                $_SESSION['mj_acceso'] = true;
                header('Location: ./');
                exit;
            }
            $error = 'Clave incorrecta.';
        } else {
            $correo = trim((string) ($_POST['mj_correo'] ?? ''));

            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $error = 'Escribe tu dirección de correo completa.';
            } else {
                require_once __DIR__ . '/imap-cliente.php';

                $imap = new MjImap([
                    'host'    => $servidor,
                    'puerto'  => (int) ($cfg['origen']['imap']['puerto'] ?? 993),
                    'cifrado' => (string) ($cfg['origen']['imap']['cifrado'] ?? 'ssl'),
                    'validar_certificado' => !empty($cfg['origen']['imap']['validar_certificado']),
                    'usuario' => $correo,
                    'clave'   => $clave,
                ]);

                if ($imap->conectar() && $imap->entrar()) {
                    $imap->cerrar();
                    session_regenerate_id(true);
                    $_SESSION['mj_acceso']  = true;
                    $_SESSION['mj_usuario'] = $correo;
                    $_SESSION['mj_clave']   = $clave;
                    header('Location: ./');
                    exit;
                }
                $error = $imap->error ?: 'No se pudo entrar con esos datos.';
            }
        }
    }

    mj_pantalla_acceso($cfg, $error, false, $modo);
    return false;
}

function mj_pantalla_acceso(array $cfg, string $error = '', bool $soloAviso = false, string $modo = 'casilla'): void
{
    $marca = $cfg['marca']['nombre'] ?? 'Correo';
    http_response_code($soloAviso ? 503 : 401);
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= mj_e($marca) ?> · Acceso</title>
<meta name="robots" content="noindex, nofollow">
<style>
  :root{ color-scheme: dark }
  *{ box-sizing:border-box }
  body{
    margin:0; min-height:100vh; display:grid; place-items:center;
    background:#0b1220; color:#e8eaf0; padding:24px;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  }
  .caja{
    width:100%; max-width:340px; padding:28px;
    background:#111a2b; border:1px solid rgba(255,255,255,.10); border-radius:16px;
  }
  h1{ margin:0 0 4px; font-size:19px }
  p.sub{ margin:0 0 20px; font-size:13px; color:#93a0b8 }
  label{ display:block; font-size:12px; color:#93a0b8; margin-bottom:6px }
  input{
    width:100%; padding:11px 12px; border-radius:9px;
    background:#0b1220; border:1px solid rgba(255,255,255,.14); color:#e8eaf0;
    font-size:15px;
  }
  input:focus{ outline:none; border-color:#3b82f6 }
  button{
    width:100%; margin-top:14px; padding:11px; border:0; border-radius:9px;
    background:#3b82f6; color:#fff; font-size:14px; font-weight:600; cursor:pointer;
  }
  button:hover{ background:#2f6fd8 }
  .error{
    margin:0 0 14px; padding:9px 11px; border-radius:9px; font-size:13px;
    background:rgba(239,68,68,.14); border:1px solid rgba(239,68,68,.35); color:#fca5a5;
  }
</style>
</head>
<body>
  <div class="caja">
    <h1><?= mj_e($marca) ?></h1>
    <p class="sub"><?= $modo === 'casilla'
        ? 'Entra con tu cuenta de correo.'
        : 'Esta casilla es privada.' ?></p>

    <?php if ($error !== ''): ?><p class="error"><?= mj_e($error) ?></p><?php endif; ?>

    <?php if (!$soloAviso): ?>
    <form method="post">
      <?php if ($modo === 'casilla'): ?>
        <label for="u">Correo</label>
        <input type="email" id="u" name="mj_correo" required autofocus
               autocomplete="username" placeholder="tucorreo@tudominio.cl"
               value="<?= mj_e((string) ($_POST['mj_correo'] ?? '')) ?>">
        <label for="c" style="margin-top:12px">Contraseña</label>
      <?php else: ?>
        <label for="c">Clave de acceso</label>
      <?php endif; ?>
      <input type="password" id="c" name="mj_clave" required
             <?= $modo === 'clave' ? 'autofocus' : '' ?> autocomplete="current-password">
      <button type="submit">Entrar</button>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>
    <?php
}
