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

require_once __DIR__ . '/recuerdo.php';

function mj_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('bacano_mail');
        session_set_cookie_params([
            'lifetime' => 0,          // la sesión muere con el navegador;
            'path'     => '/',        // lo que sobrevive es el vale de recuerdo.php
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
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
    require_once __DIR__ . '/recuerdo.php';

    mj_recuerdo_borrar((string) ($_COOKIE[MJ_RECUERDO_COOKIE] ?? ''));
    mj_recuerdo_cookie('');

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

    // ¿Hay un vale de "mantener la sesión abierta"? Se restaura desde él.
    if (!empty($_COOKIE[MJ_RECUERDO_COOKIE] ?? '')) {
        require_once __DIR__ . '/recuerdo.php';
        $vale = mj_recuerdo_leer((string) $_COOKIE[MJ_RECUERDO_COOKIE]);

        if ($vale !== null) {
            session_regenerate_id(true);
            $_SESSION['mj_acceso']  = true;
            $_SESSION['mj_usuario'] = $vale['usuario'];
            $_SESSION['mj_clave']   = $vale['clave'];

            // el vale se renueva en cada uso: si alguien copió el token viejo,
            // deja de servir en cuanto la persona vuelve a entrar
            mj_recuerdo_borrar((string) $_COOKIE[MJ_RECUERDO_COOKIE]);
            $nuevo = mj_recuerdo_crear($vale['usuario'], $vale['clave']);
            if ($nuevo !== '') { mj_recuerdo_cookie($nuevo); }

            return true;
        }
        mj_recuerdo_cookie('');
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

                    if (isset($_POST['mj_recordar'])) {
                        require_once __DIR__ . '/recuerdo.php';
                        $token = mj_recuerdo_crear($correo, $clave);
                        if ($token !== '') { mj_recuerdo_cookie($token); }
                    }

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
    $marca  = $cfg['marca']['nombre'] ?? 'Correo';
    $corto  = $cfg['marca']['nombre_corto'] ?? mb_substr($marca, 0, 1);
    $logo   = (string) ($cfg['marca']['logo'] ?? '');
    $sitio  = (string) ($cfg['marca']['url'] ?? '../');
    $lema   = (string) ($cfg['acceso']['lema'] ?? '');
    $soporte = (string) ($cfg['acceso']['soporte'] ?? '');

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
  *,*::before,*::after{ box-sizing:border-box; margin:0; padding:0 }

  :root{
    --tinta:#0a0a0a;
    --tinta-2:#525252;
    --tinta-3:#8a8a8a;
    --linea:#e4e4e4;
    --papel:#ffffff;
    --fondo:#ececec;
    --marca-agua:#f4f4f4;
  }

  html{ -webkit-text-size-adjust:100% }
  body{
    min-height:100svh;
    display:grid; place-items:center;
    padding:clamp(16px,4vw,48px);
    background:var(--fondo);
    color:var(--tinta);
    font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    font-size:15px; line-height:1.55;
    -webkit-font-smoothing:antialiased;
  }

  /* ---------- La tarjeta, en dos columnas ---------- */
  .tarjeta{
    width:100%; max-width:980px;
    display:grid; grid-template-columns:1fr 1fr;
    background:var(--papel);
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 1px 2px rgba(0,0,0,.04), 0 24px 60px -30px rgba(0,0,0,.25);
  }

  /* ---------- Columna izquierda ---------- */
  .presenta{
    position:relative;
    display:flex; flex-direction:column; justify-content:space-between;
    padding:clamp(20px,3vw,32px);
    min-height:420px;
    overflow:hidden;
  }
  .marca-agua{
    position:absolute; top:-6%; left:-4%;
    font-size:230px; font-weight:800; line-height:.8; letter-spacing:-.06em;
    color:var(--marca-agua);
    user-select:none; pointer-events:none;
  }
  .volver{
    position:relative; z-index:1;
    display:grid; place-items:center;
    width:34px; height:34px;
    border:1px solid var(--linea); border-radius:9px;
    color:var(--tinta); text-decoration:none; font-size:15px;
    transition:border-color .2s, background-color .2s;
  }
  .volver:hover{ border-color:var(--tinta); background:#fafafa }

  .presenta__pie{ position:relative; z-index:1 }
  .marca{ display:flex; align-items:center; gap:10px; margin-bottom:14px }
  .marca__icono{
    display:grid; place-items:center;
    width:30px; height:30px; border-radius:8px;
    background:var(--tinta); color:var(--papel);
    font-size:13px; font-weight:700; letter-spacing:-.02em;
  }
  .marca__icono img{ width:100%; height:100%; object-fit:contain; border-radius:8px }
  .marca__nombre{ font-size:15px; font-weight:600; letter-spacing:-.01em }

  .presenta p{ font-size:13px; color:var(--tinta-2); max-width:38ch; margin-bottom:26px }

  .enlaces{ display:flex; gap:22px; font-size:13px }
  .enlaces a{ color:var(--tinta-2); text-decoration:none }
  .enlaces a:hover{ color:var(--tinta) }

  /* ---------- Columna derecha ---------- */
  .acceso{
    display:flex; flex-direction:column; justify-content:center;
    padding:clamp(24px,3.4vw,40px);
    border-left:1px solid var(--linea);
  }
  .acceso h1{ font-size:clamp(21px,2.4vw,26px); font-weight:600; letter-spacing:-.02em; margin-bottom:6px }
  .acceso__sub{ font-size:13px; color:var(--tinta-2); margin-bottom:22px; max-width:34ch }

  label{ display:block; font-size:12.5px; font-weight:500; margin-bottom:6px }

  .campo{ margin-bottom:14px }
  .campo__caja{ position:relative }
  input[type=email],input[type=password],input[type=text]{
    width:100%; height:42px;
    padding:0 40px 0 13px;
    border:1px solid var(--linea); border-radius:9px;
    background:var(--papel); color:var(--tinta);
    font-family:inherit; font-size:14px;
    transition:border-color .2s, box-shadow .2s;
  }
  input::placeholder{ color:#b8b8b8 }
  input:focus{
    outline:none; border-color:var(--tinta);
    box-shadow:0 0 0 3px rgba(10,10,10,.06);
  }
  .ojo{
    position:absolute; top:50%; right:6px; transform:translateY(-50%);
    display:grid; place-items:center;
    width:32px; height:32px;
    background:none; border:0; cursor:pointer; color:var(--tinta-3);
    border-radius:7px;
  }
  .ojo:hover{ color:var(--tinta); background:#f5f5f5 }

  .olvido{ display:block; text-align:right; font-size:12px; color:var(--tinta-2); text-decoration:none; margin-top:-4px }
  .olvido:hover{ color:var(--tinta); text-decoration:underline }

  /* ---------- Interruptor ---------- */
  .opcion{
    display:flex; align-items:flex-start; gap:12px;
    margin:20px 0 18px;
  }
  .opcion__txt strong{ display:block; font-size:13px; font-weight:500 }
  .opcion__txt span{ font-size:12px; color:var(--tinta-2) }
  .palanca{ position:relative; flex:none; width:38px; height:22px; margin-top:2px }
  .palanca input{ position:absolute; opacity:0; width:100%; height:100%; margin:0; cursor:pointer; z-index:1 }
  .palanca i{
    position:absolute; inset:0; border-radius:999px;
    background:#e0e0e0; transition:background-color .2s;
  }
  .palanca i::after{
    content:""; position:absolute; top:3px; left:3px;
    width:16px; height:16px; border-radius:50%;
    background:var(--papel); box-shadow:0 1px 2px rgba(0,0,0,.25);
    transition:transform .2s;
  }
  .palanca input:checked + i{ background:var(--tinta) }
  .palanca input:checked + i::after{ transform:translateX(16px) }
  .palanca input:focus-visible + i{ box-shadow:0 0 0 3px rgba(10,10,10,.12) }

  /* ---------- Botón ---------- */
  .entrar{
    width:100%; height:44px;
    border:0; border-radius:9px;
    background:var(--tinta); color:var(--papel);
    font-family:inherit; font-size:14px; font-weight:600;
    cursor:pointer; transition:opacity .2s, transform .1s;
  }
  .entrar:hover{ opacity:.86 }
  .entrar:active{ transform:translateY(1px) }

  .aviso{
    margin-bottom:16px; padding:10px 12px;
    border:1px solid var(--linea); border-left:2px solid var(--tinta);
    border-radius:8px; background:#fafafa;
    font-size:13px;
  }

  .creditos{
    margin-top:26px; padding-top:16px;
    border-top:1px solid var(--linea);
    font-size:11.5px; color:var(--tinta-3);
  }

  /* ---------- Pantallas pequeñas ---------- */
  @media (max-width:760px){
    body{ padding:0; place-items:stretch }
    /* la presentación ocupa lo suyo y el formulario se queda el resto */
    .tarjeta{
      grid-template-columns:1fr; grid-template-rows:auto 1fr;
      max-width:none; border-radius:0; min-height:100svh; box-shadow:none;
    }
    .presenta{ min-height:auto; padding:18px 20px 22px }
    .marca-agua{ font-size:150px; top:-14%; }
    .presenta p{ display:none }
    .presenta__pie{ display:flex; align-items:center; justify-content:space-between; gap:16px; margin-top:52px }
    .marca{ margin-bottom:0 }
    .acceso{ border-left:0; border-top:1px solid var(--linea); justify-content:flex-start; padding:26px 20px 40px }
  }
  @media (max-width:420px){
    .enlaces{ gap:14px; font-size:12px }
  }
</style>
</head>
<body>
  <main class="tarjeta">

    <section class="presenta">
      <span class="marca-agua" aria-hidden="true"><?= mj_e(mb_substr($corto, 0, 1)) ?></span>

      <a class="volver" href="<?= mj_e($sitio) ?>" aria-label="Volver al sitio">&larr;</a>

      <div class="presenta__pie">
        <div class="marca">
          <span class="marca__icono">
            <?php if ($logo !== ''): ?><img src="<?= mj_e($logo) ?>" alt=""><?php else: ?><?= mj_e($corto) ?><?php endif; ?>
          </span>
          <span class="marca__nombre"><?= mj_e($marca) ?></span>
        </div>

        <?php if ($lema !== ''): ?><p><?= mj_e($lema) ?></p><?php endif; ?>

        <nav class="enlaces">
          <a href="<?= mj_e($sitio) ?>">Sitio</a>
          <?php if ($soporte !== ''): ?><a href="mailto:<?= mj_e($soporte) ?>">Soporte</a><?php endif; ?>
        </nav>
      </div>
    </section>

    <section class="acceso">
      <h1>Entrar al correo</h1>
      <p class="acceso__sub">
        <?= $modo === 'casilla'
            ? 'Usa tu dirección y la contraseña de tu casilla.'
            : 'Esta casilla es privada.' ?>
      </p>

      <?php if ($error !== ''): ?><p class="aviso"><?= mj_e($error) ?></p><?php endif; ?>

      <?php if (!$soloAviso): ?>
      <form method="post">
        <?php if ($modo === 'casilla'): ?>
          <div class="campo">
            <label for="u">Correo</label>
            <div class="campo__caja">
              <input type="email" id="u" name="mj_correo" required autofocus
                     autocomplete="username" placeholder="tucorreo@tudominio.cl"
                     value="<?= mj_e((string) ($_POST['mj_correo'] ?? '')) ?>">
            </div>
          </div>
        <?php endif; ?>

        <div class="campo">
          <label for="c"><?= $modo === 'casilla' ? 'Contraseña' : 'Clave de acceso' ?></label>
          <div class="campo__caja">
            <input type="password" id="c" name="mj_clave" required
                   <?= $modo === 'clave' ? 'autofocus' : '' ?> autocomplete="current-password">
            <button class="ojo" type="button" id="ver" aria-label="Mostrar la contraseña">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/>
                <circle cx="12" cy="12" r="2.6"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="opcion">
          <span class="palanca">
            <input type="checkbox" id="rec" name="mj_recordar" <?= isset($_POST['mj_recordar']) ? 'checked' : '' ?>>
            <i></i>
          </span>
          <span class="opcion__txt">
            <strong>Mantener la sesión abierta</strong>
            <span>No la actives en un computador compartido.</span>
          </span>
        </div>

        <button class="entrar" type="submit">Entrar</button>
      </form>
      <?php endif; ?>

      <p class="creditos"><?= mj_e($marca) ?> — acceso privado a la casilla.</p>
    </section>

  </main>

<script>
  (function () {
    var ojo = document.getElementById('ver'), campo = document.getElementById('c');
    if (!ojo || !campo) return;
    ojo.addEventListener('click', function () {
      var oculto = campo.type === 'password';
      campo.type = oculto ? 'text' : 'password';
      ojo.setAttribute('aria-label', oculto ? 'Ocultar la contraseña' : 'Mostrar la contraseña');
      campo.focus();
    });
  })();
</script>
</body>
</html>
    <?php
}
