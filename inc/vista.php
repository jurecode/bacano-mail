<?php
/* ============================================================
   MÓDULO DE CORREO — Vista
   ------------------------------------------------------------
   Un solo punto de entrada:   mj_correo();
   Imprime CSS + marcado + JS. Se puede incrustar en cualquier
   página PHP con:   require 'mails/correo.php';  mj_correo();
   ============================================================ */

require_once __DIR__ . '/ayuda.php';
require_once __DIR__ . '/iconos.php';
require_once __DIR__ . '/cargar.php';
require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/proveedores.php';
require_once __DIR__ . '/actualizador.php';   // mj_version(): sirve para refrescar la caché

/** Punto de entrada. $ov permite sobrescribir cualquier opción del config. */
function mj_correo(array $ov = []): void
{
  static $instancias = 0;
  $instancias++;

  $cfg = mj_config($ov);

  $base = mj_base($cfg);
  $prov = mj_proveedor($cfg);
  $msgs = $prov->mensajes();

  // La agenda es de quien entró, no del servidor de correo
  require_once __DIR__ . '/contactos.php';
  require_once __DIR__ . '/mime.php';
  require_once __DIR__ . '/novedades.php';
  $buzon     = mj_buzon_actual($cfg);
  $contactos = $buzon !== '' ? mj_contactos($buzon) : [];

  // ---- Estado desde la URL (funciona incluso sin JavaScript) ----
  $carpetas_ids = array_merge(
    array_column($cfg['carpetas'], 'id'),
    array_column($cfg['carpetas_propias'], 'id')
  );
  $carpetas_ids[] = 'cuenta';     // vista de ajustes: no es carpeta ni sale en el menú
  $carpetas_ids[] = 'novedades';
  $carpeta = mj_param('carpeta', $carpetas_ids, $carpetas_ids[0] ?? 'entrada');
  $filtro  = mj_param('f', ['todos', 'leidos', 'no_leidos'], 'todos');
  $busca   = trim((string) ($_GET['q'] ?? ''));
  $activo  = (string) ($_GET['m'] ?? '');

  // ---- Al abrir un mensaje, se marca leído en el servidor ----
  // Va antes de contar, para que el número del menú baje en el acto.
  if ($activo !== '' && !empty($cfg['interfaz']['auto_marcar_leido'])
      && method_exists($prov, 'marcar_leido')) {
    foreach ($msgs as $i => $m) {
      if ($m['id'] === $activo && !$m['leido']) {
        // Se marca la conversación entera: si sólo se marca el mensaje
        // abierto, sus hermanos siguen sin leer y el contador no baja.
        $porMarcar = method_exists($prov, 'hermanos') ? $prov->hermanos($activo) : [$activo];
        if (!in_array($activo, $porMarcar, true)) { $porMarcar[] = $activo; }

        $hechos = method_exists($prov, 'marcar_varios')
          ? $prov->marcar_varios($porMarcar)
          : ($prov->marcar_leido($activo) ? 1 : 0);

        if ($hechos > 0) {
          foreach ($msgs as $j => $otro) {
            if (in_array($otro['id'], $porMarcar, true)) { $msgs[$j]['leido'] = true; }
          }
        } elseif (!empty($prov->ultimo_error)) {
          error_log('BACANO.MAIL: no se pudo marcar leído ' . $activo . ' — ' . $prov->ultimo_error);
        }
        break;
      }
    }
  }

  // ---- Conteos por carpeta (para los badges) ----
  $conteo = [];
  foreach ($msgs as $m) {
    $c = $m['carpeta'];
    $conteo[$c]['total'] = ($conteo[$c]['total'] ?? 0) + 1;
    $conteo[$c]['no_leidos'] = ($conteo[$c]['no_leidos'] ?? 0) + ($m['leido'] ? 0 : 1);
  }

  $conteo['contactos']['total'] = count($contactos);

  // Se marcan al abrirlas, antes de contar: si no, el aviso seguiría encendido
  // hasta la siguiente recarga y parecería que no se enteró.
  // Se marcan al abrirlas, pero hace falta saber qué había visto antes para
  // poder señalar cuáles son nuevas en esta misma visita.
  $vistoAntes = '';
  if ($carpeta === 'novedades') {
    $vistoAntes = mj_novedades_visto($buzon);
    mj_novedades_marcar($buzon);
  }
  $sinVer = mj_novedades_sin_ver($buzon);

  // ---- Mensajes de la carpeta activa ----
  $lista = array_values(array_filter($msgs, fn($m) => $m['carpeta'] === $carpeta));
  if ($carpeta === 'destacado') {
    $lista = array_values(array_filter($msgs, fn($m) => $m['destacado'] || $m['importante']));
  }
  // Una fila por conversación: se muestra el mensaje más reciente de cada
  // hilo y se cuenta cuántos lleva, como hace Gmail.
  if (!empty($cfg['interfaz']['agrupar_conversaciones'])) {
    $porHilo = [];
    $cuenta  = [];
    $alguno_sin_leer = [];

    foreach ($lista as $m) {
      $h = ($m['hilo'] ?? '') !== '' ? $m['hilo'] : $m['id'];
      $cuenta[$h] = ($cuenta[$h] ?? 0) + 1;
      if (!$m['leido']) { $alguno_sin_leer[$h] = true; }

      // se queda el más reciente como cara visible de la conversación
      if (!isset($porHilo[$h]) || strcmp($m['fecha'], $porHilo[$h]['fecha']) > 0) {
        $porHilo[$h] = $m;
      }
    }
    foreach ($porHilo as $h => $m) {
      $porHilo[$h]['en_hilo'] = $cuenta[$h];
      $porHilo[$h]['leido']   = empty($alguno_sin_leer[$h]);
    }
    $lista = array_values($porHilo);
    usort($lista, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
  }

  $lista = array_slice($lista, 0, (int) ($cfg['interfaz']['mensajes_por_pagina'] ?? 50));

  // Mensaje abierto: el que venga en la URL. Sólo se abre uno solo si está
  // pedido en la configuración; si no, el lector espera a que elijas.
  // Se busca entre todos los mensajes, no sólo en la lista agrupada: al
  // abrir uno de dentro de una conversación no está como fila propia.
  $sel = null;
  foreach ($msgs as $m) { if ($m['id'] === $activo) { $sel = $m; break; } }

  if (!$sel && !empty($cfg['interfaz']['abrir_primero'])
      && $cfg['interfaz']['panel_lectura'] !== 'oculto') {
    foreach ($lista as $m) {
      if ($filtro === 'todos' || ($filtro === 'leidos') === (bool) $m['leido']) { $sel = $m; break; }
    }
  }

  $t = $cfg['textos'];

  // ---- CSS + fuentes (una sola vez por página) ----
  if ($instancias === 1) {
    if (!empty($cfg['tema']['fuente_google'])) {
      echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
      echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
      echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family='
         . mj_e($cfg['tema']['fuente_google']) . '&display=swap">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . mj_e($base) . '/assets/css/mail.css?v=' . mj_e(mj_version()) . '">' . "\n";
  }

  // ---- Variables de tema en línea ----
  $estilo = mj_estilo_tema($cfg);

  $raiz_attrs = mj_datos([
    'tema'      => $cfg['tema']['preset'],
    'modo'      => $cfg['tema']['modo'],
    'fondo'     => $cfg['tema']['fondo'],
    'animar'    => !empty($cfg['tema']['animar_fondo']),
    'densidad'  => $cfg['tema']['densidad'],
    'panel'     => $cfg['interfaz']['panel_lectura'],
    'carpeta'   => $carpeta,
    'filtro'    => $filtro,
    'vista'     => ($sel && $activo !== '') ? 'lector' : 'lista',
    'ventana'   => !empty($cfg['tema']['ventana']),
  ]);
  ?>
  <div class="mjmail" style="<?= $estilo ?>" <?= $raiz_attrs ?>>

    <?php if ($cfg['tema']['fondo'] !== 'solido'): ?>
      <div class="mj-fondo" aria-hidden="true"><i></i><i></i><i></i></div>
    <?php endif; ?>

    <div class="mj-ventana">

      <?php if ($cfg['interfaz']['mostrar_rail']) mj_v_rail($cfg, $base); ?>

      <?php if ($cfg['interfaz']['mostrar_carpetas']) mj_v_carpetas($cfg, $carpeta, $conteo, $sinVer); ?>

      <?php if ($carpeta === 'contactos'): ?>

        <?php mj_v_contactos($cfg, $contactos); ?>

      <?php elseif ($carpeta === 'cuenta'): ?>

        <?php mj_v_ajustes($cfg); ?>

      <?php elseif ($carpeta === 'novedades'): ?>

        <?php mj_v_novedades($cfg, $vistoAntes); ?>

      <?php else: ?>

        <?php mj_v_lista($cfg, $lista, $carpeta, $filtro, $busca, $sel, $sinVer); ?>

        <?php if ($cfg['interfaz']['panel_lectura'] !== 'oculto') {
                mj_v_lector($cfg, $sel, mj_conversacion($msgs, $sel));
              } ?>

      <?php endif; ?>

    </div>

    <?php /* Contenido de cada mensaje: el JS los intercambia sin recargar */ ?>
    <div class="mj-plantillas" hidden>
      <?php foreach ($lista as $m): ?>
        <template data-mensaje="<?= mj_e($m['id']) ?>"><?php
          // La misma conversación que vería al recargar: si no, al abrir con
          // un clic el mensaje salía suelto y sólo aparecía al refrescar.
          mj_v_lector_contenido($cfg, $m, mj_conversacion($msgs, $m));
        ?></template>
      <?php endforeach; ?>
    </div>

    <?php if ($cfg['interfaz']['menu_contextual']) mj_v_menu($cfg); ?>
    <?php if ($cfg['interfaz']['boton_redactar'])  mj_v_compositor($cfg); ?>
    <?php if ($cfg['interfaz']['mostrar_ayuda_atajos'] && $cfg['interfaz']['atajos_teclado']) mj_v_atajos($cfg); ?>
    <?php mj_v_confirmar(); ?>
    <?php if ($carpeta === 'contactos') mj_v_ficha_contacto(); ?>
    <?php if (function_exists('mj_dentro') && mj_dentro()) mj_v_nueva_casilla(); ?>

    <div class="mj-avisos" role="status" aria-live="polite"></div>

    <?php if (!mj_instalado()): ?>
      <div class="mj-sin-instalar">
        <strong>Módulo sin configurar</strong>
        <span>Estás viendo datos de ejemplo.</span>
        <a href="<?= mj_e($base) ?>/instalar.php">Configurar ahora</a>
      </div>
    <?php endif; ?>

    <script type="application/json" class="mj-datos"><?= json_encode([
      'textos'   => $t,
      'colores'  => $cfg['colores_estrella'],
      'carpetas' => array_map(fn($c) => ['id' => $c['id'], 'nombre' => $c['nombre']],
                     array_merge($cfg['carpetas'], $cfg['carpetas_propias'])),
      // Para sugerir destinatarios al redactar. Se recorta: una agenda muy
      // larga no tiene por qué viajar entera en cada carga de la página.
      'contactos' => array_map(
                       fn($c) => ['e' => $c['email'], 'n' => $c['nombre']],
                       array_slice($contactos, 0, 300)),
      'opciones' => [
        'carpeta'     => $carpeta,
        'autoLeer'    => (bool) $cfg['interfaz']['auto_marcar_leido'],
        'atajos'      => (bool) $cfg['interfaz']['atajos_teclado'],
        'avisos'      => (bool) $cfg['interfaz']['notificaciones'],
        'confirmar'   => (bool) $cfg['interfaz']['confirmar_eliminar'],
        'seleccion'   => (bool) $cfg['interfaz']['seleccion_multiple'],
        'modo'        => $cfg['tema']['modo'],
        // Lo que de verdad deja subir este servidor, para avisar antes de enviar
        'topeSubida'  => mj_limite_subida(),
        'topeTexto'   => mj_limite_legible(),
        'permitirModo'=> (bool) $cfg['tema']['permitir_cambio_modo'],
      ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  </div>
  <?php
  if ($instancias === 1) {
    echo '<script src="' . mj_e($base) . '/assets/js/mail.js?v=' . mj_e(mj_version()) . '" defer></script>' . "\n";
  }
}

/* ------------------------------------------------------------
   BLOQUES DE LA VISTA
   ------------------------------------------------------------ */

/** Barra oscura de iconos */
function mj_v_rail(array $cfg, string $base = '.'): void
{
  // Menú del perfil + lo que aporten los módulos activos
  $items = array_merge($cfg['rail'], mj_modulos_rail($cfg, $base));

  // Las secciones sin dirección real no se muestran: un menú donde la mitad
  // de los botones no lleva a ninguna parte confunde más de lo que ayuda.
  // Se pueden mostrar igual, apagadas, con interfaz.rail_mostrar_pendientes.
  $pendiente = static fn(array $it): bool => trim((string) ($it['url'] ?? '')) === ''
                                          || trim((string) ($it['url'] ?? '')) === '#';
  $mostrarPendientes = !empty($cfg['interfaz']['rail_mostrar_pendientes']);
  if (!$mostrarPendientes) {
    $items = array_values(array_filter($items, fn($it) => !$pendiente($it)));
  }
  ?>
  <nav class="mj-rail" aria-label="Secciones del sistema">
    <a class="mj-rail-marca" href="<?= mj_e($cfg['marca']['url']) ?>">
      <?php if ($cfg['marca']['logo']): ?>
        <img src="<?= mj_e($cfg['marca']['logo']) ?>" alt="<?= mj_e($cfg['marca']['nombre_full']) ?>">
      <?php else:
        $corto = $cfg['marca']['nombre_corto'] ?: mb_substr($cfg['marca']['nombre'], 0, 1, 'UTF-8') . '.'; ?>
        <span class="mj-marca-corta" aria-hidden="true"><?= mj_e($corto) ?></span>
        <span class="mj-marca-larga"><?= mj_e($cfg['marca']['nombre']) ?></span>
      <?php endif; ?>
    </a>

    <?php if ($cfg['interfaz']['rail_colapsable']): ?>
      <button class="mj-rail-tirador" type="button" data-accion="rail"
              aria-label="Mostrar u ocultar los nombres del menú"><?= mj_icono('adelante', 16) ?></button>
    <?php endif; ?>

    <ul class="mj-rail-lista">
      <?php foreach ($items as $it): ?>
        <li>
          <?php if ($pendiente($it)): ?>
            <span class="mj-rail-item is-pendiente" title="Todavía no está disponible" aria-disabled="true">
              <?= mj_icono($it['icono'], 20) ?><span><?= mj_e($it['texto']) ?></span>
            </span>
          <?php else: ?>
            <a class="mj-rail-item<?= !empty($it['activo']) ? ' is-activo' : '' ?>"
               href="<?= mj_e($it['url']) ?>" <?= !empty($it['activo']) ? 'aria-current="page"' : '' ?>>
              <?= mj_icono($it['icono'], 20) ?><span><?= mj_e($it['texto']) ?></span>
            </a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <ul class="mj-rail-lista mj-rail-pie">
      <?php foreach ($cfg['rail_pie'] as $it):
            if ($pendiente($it) && !$mostrarPendientes) continue; ?>
        <li>
          <a class="mj-rail-item" href="<?= mj_e($it['url']) ?>">
            <?= mj_icono($it['icono'], 20) ?><span><?= mj_e($it['texto']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
<?php }

/** Los demás mensajes del hilo de $m, del más antiguo al más nuevo. */
function mj_conversacion(array $msgs, ?array $m): array
{
    if (!$m || ($m['hilo'] ?? '') === '') {
        return [];
    }

    $otros = [];
    foreach ($msgs as $x) {
        if (($x['hilo'] ?? '') === $m['hilo'] && $x['id'] !== $m['id']) {
            $otros[] = $x;
        }
    }
    usort($otros, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    return $otros;
}

/**
 * Esconde la parte citada de una respuesta detrás de un botón, como hace
 * Gmail: lo que se escribió ahora queda arriba, y "El … escribió:" con sus
 * ">" se pliega. El corte puede caer dentro de un párrafo, porque los
 * correos de texto llegan como un solo bloque con <br>.
 */
function mj_plegar_citado(string $html): string
{
    $marcas = [
        '/(?:<br\s*\/?>\s*)*(?:El |On |&gt;\s*El )[^<]{0,220}?escribi[oó]\s*:/iu',
        '/(?:<br\s*\/?>\s*)+&gt;/u',      // la primera línea citada
        '/(?:<br\s*\/?>\s*)*-{2,}\s*Mensaje reenviado\s*-{2,}/iu',
    ];

    $corte = null;
    foreach ($marcas as $patron) {
        if (preg_match($patron, $html, $m, PREG_OFFSET_CAPTURE)) {
            $corte = $corte === null ? $m[0][1] : min($corte, $m[0][1]);
        }
    }
    if ($corte === null || $corte === 0) {
        return $html;
    }

    $nuevo  = substr($html, 0, $corte);
    $citado = ltrim(substr($html, $corte));
    $citado = preg_replace('/^(?:<br\s*\/?>\s*)+/i', '', $citado);

    if (trim(strip_tags($nuevo)) === '' || trim(strip_tags($citado)) === '') {
        return $html;
    }

    // El corte suele caer dentro de un <p>: se cierra el visible y se abre
    // uno nuevo para la cita, así el HTML sigue siendo válido.
    if (substr_count($nuevo, '<p') > substr_count($nuevo, '</p')) {
        $nuevo .= '</p>';
    }
    if (substr_count($citado, '</p') > substr_count($citado, '<p')) {
        $citado = '<p>' . $citado;
    }

    return $nuevo
        . '<details class="mj-citado"><summary title="Mostrar lo anterior">···</summary>'
        . '<div class="mj-citado-cuerpo">' . $citado . '</div></details>';
}

/** Novedades: qué cambió y qué se puede hacer con ello */
function mj_v_novedades(array $cfg, string $visto = ''): void
{
  require_once __DIR__ . '/novedades.php';
  $lista = mj_novedades();
  ?>
  <section class="mj-novedades" aria-label="Novedades">
    <div class="mj-novedades-col">

      <header class="mj-novedades-cab">
        <div class="mj-agenda-titulo">
          <?php if ($cfg['interfaz']['mostrar_carpetas']): ?>
            <button class="mj-icono-btn mj-solo-movil" type="button" data-accion="abrir-carpetas"
                    aria-label="Abrir el menú de carpetas"><?= mj_icono('menu', 20) ?></button>
          <?php endif; ?>
          <h1 class="mj-h1">Novedades</h1>
        </div>
        <p class="mj-agenda-nota">Lo que se ha ido agregando a tu correo, y cómo usarlo.</p>
      </header>

      <div class="mj-novedades-cuerpo">
        <?php foreach ($lista as $i => $x):
          $nueva = mj_novedad_nueva($x, $visto); ?>
          <article class="mj-nov<?= $nueva ? ' is-nueva' : '' ?>">
            <div class="mj-nov-marca" aria-hidden="true"><?= mj_icono($x['icono'], 20) ?></div>

            <div class="mj-nov-txt">
              <div class="mj-nov-alto">
                <h2 class="mj-nov-t"><?= mj_e($x['titulo']) ?></h2>
                <?php if ($nueva): ?><span class="mj-nov-tag">Nuevo</span><?php endif; ?>
              </div>
              <p class="mj-nov-que"><?= mj_e($x['que']) ?></p>

              <?php if (!empty($x['hacer'])): ?>
                <ul class="mj-nov-pasos">
                  <?php foreach ($x['hacer'] as $paso): ?>
                    <li><?= mj_e($paso) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <?php if (!empty($x['ojo'])): ?>
                <p class="mj-nov-ojo"><strong>Ojo:</strong> <?= mj_e($x['ojo']) ?></p>
              <?php endif; ?>

              <p class="mj-nov-pie">Versión <?= mj_e($x['v']) ?> · <?= mj_e(mj_fecha_corta_iso($x['fecha'])) ?></p>
            </div>
          </article>
        <?php endforeach; ?>

        <p class="mj-nov-final">
          ¿Echas algo en falta o algo no funciona como esperabas? Cuéntalo y se revisa.
        </p>
      </div>

    </div>
  </section>
<?php }

/** "2026-09-04" → "4 de septiembre de 2026" */
function mj_fecha_corta_iso(string $iso): string
{
  $meses = ['enero','febrero','marzo','abril','mayo','junio','julio',
            'agosto','septiembre','octubre','noviembre','diciembre'];
  $t = strtotime($iso);
  if ($t === false) { return $iso; }
  return (int) date('j', $t) . ' de ' . ($meses[(int) date('n', $t) - 1] ?? '') . ' de ' . date('Y', $t);
}

/** Ajustes de la cuenta, dentro de la propia ventana */
function mj_v_ajustes(array $cfg): void
{
  require_once __DIR__ . '/cuenta.php';
  require_once __DIR__ . '/cpanel.php';
  require_once __DIR__ . '/cuentas.php';

  $correo = function_exists('mj_buzon_actual') ? mj_buzon_actual($cfg) : '';
  $datos  = $correo !== '' ? mj_cuenta($correo) : ['nombre' => '', 'firma' => ''];
  $tok    = function_exists('mj_token_sesion') ? mj_token_sesion() : '';
  $otras  = function_exists('mj_cuentas_lista') ? mj_cuentas_lista() : [];
  ?>
  <section class="mj-ajustes" aria-label="Ajustes de tu cuenta">
    <div class="mj-ajustes-col">

      <header class="mj-ajustes-cab">
        <div class="mj-agenda-titulo">
          <?php if ($cfg['interfaz']['mostrar_carpetas']): ?>
            <button class="mj-icono-btn mj-solo-movil" type="button" data-accion="abrir-carpetas"
                    aria-label="Abrir el menú de carpetas"><?= mj_icono('menu', 20) ?></button>
          <?php endif; ?>
          <h1 class="mj-h1">Tu cuenta</h1>
        </div>
        <p class="mj-agenda-nota"><?= mj_e($correo) ?></p>
      </header>

      <div class="mj-ajustes-cuerpo">

        <form class="mj-bloque" data-rol="form-perfil" data-token="<?= mj_e($tok) ?>">
          <div class="mj-bloque-cab">
            <h2 class="mj-bloque-t">Cómo te ven</h2>
            <p class="mj-bloque-d">El nombre y la firma con los que salen tus correos.</p>
          </div>

          <label class="mj-set">
            <span class="mj-set-l">Nombre</span>
            <input class="mj-set-c" type="text" name="nombre" maxlength="80" autocomplete="off"
                   value="<?= mj_e($datos['nombre']) ?>" placeholder="Nombre y apellido">
          </label>

          <label class="mj-set">
            <span class="mj-set-l">Firma</span>
            <textarea class="mj-set-c" name="firma" rows="3" maxlength="500"
                      placeholder="Se agrega al final de los correos que envíes"><?= mj_e($datos['firma']) ?></textarea>
          </label>

          <div class="mj-set">
            <span class="mj-set-l">Logo de la firma</span>
            <div class="mj-logo" data-rol="logo">
              <div class="mj-logo-caja">
                <?php if (mj_logo_hay($correo)): ?>
                  <img src="logo.php?v=<?= mj_e((string) @filemtime(mj_logo_archivo($correo) . '.bin')) ?>" alt="Logo de tu firma">
                <?php else: ?>
                  <span class="mj-logo-vacio">Sin logo</span>
                <?php endif; ?>
              </div>
              <div class="mj-logo-btns">
                <button class="mj-btn mj-btn-2 mj-btn-chico" type="button" data-accion="subir-logo">
                  <?= mj_logo_hay($correo) ? 'Cambiar' : 'Subir imagen' ?>
                </button>
                <button class="mj-btn mj-btn-2 mj-btn-chico" type="button" data-accion="quitar-logo"
                        <?= mj_logo_hay($correo) ? '' : 'hidden' ?>>Quitar</button>
                <input class="mj-sr" type="file" accept="image/png,image/jpeg,image/gif" data-rol="logo-archivo"
                       aria-label="Imagen del logo">
              </div>
            </div>
            <p class="mj-set-ayuda mj-set-ayuda-suelta">
              PNG, JPG o GIF, hasta 500 KB. Viaja dentro del correo, así que se ve
              aunque quien lo reciba bloquee las imágenes de internet.
            </p>
          </div>

          <p class="mj-set-previo">
            <?= mj_v_avatar(['nombre' => $datos['nombre'], 'email' => $correo], 30) ?>
            <span>
              <strong data-rol="previo-nombre"><?= mj_e($datos['nombre'] ?: strtok($correo, '@')) ?></strong>
              <em><?= mj_e($correo) ?></em>
            </span>
          </p>

          <div class="mj-bloque-pie">
            <button class="mj-btn" type="submit">Guardar</button>
          </div>
        </form>

        <form class="mj-bloque" data-rol="form-clave" data-token="<?= mj_e($tok) ?>">
          <div class="mj-bloque-cab">
            <h2 class="mj-bloque-t">Contraseña</h2>
            <p class="mj-bloque-d">La de esta casilla, la misma que usas en el celular.</p>
          </div>

          <?php if (!mj_cpanel_listo($cfg)): ?>
            <p class="mj-set-nota">
              Para cambiarla hace falta conectar el panel del hosting: por IMAP no se
              puede, sólo el panel manda sobre las casillas. Se configura una vez en
              <a href="instalar.php">instalar.php</a>, en «Panel del hosting».
            </p>
          <?php else: ?>
            <label class="mj-set">
              <span class="mj-set-l">Ahora</span>
              <input class="mj-set-c" type="password" name="clave_actual" autocomplete="current-password">
            </label>
            <label class="mj-set">
              <span class="mj-set-l">Nueva</span>
              <input class="mj-set-c" type="password" name="clave_nueva" autocomplete="new-password" minlength="10">
            </label>
            <label class="mj-set">
              <span class="mj-set-l">Repite la nueva</span>
              <input class="mj-set-c" type="password" name="clave_repite" autocomplete="new-password" minlength="10">
            </label>
            <p class="mj-set-ayuda">Al menos 10 caracteres. Mejor larga que complicada.</p>
            <p class="mj-set-nota mj-set-nota-ojo">
              Se cambia en el servidor: después hay que actualizarla en el celular y en
              cualquier otro programa que abra esta casilla.
            </p>
            <div class="mj-bloque-pie">
              <button class="mj-btn mj-btn-peligro" type="submit">Cambiar la contraseña</button>
            </div>
          <?php endif; ?>
        </form>

        <section class="mj-bloque">
          <div class="mj-bloque-cab">
            <h2 class="mj-bloque-t">Casillas en este equipo</h2>
            <p class="mj-bloque-d">Para cambiar de una a otra sin escribir la contraseña.</p>
          </div>

          <ul class="mj-set-casillas" data-rol="lista-casillas">
            <?= mj_v_casillas($correo, $otras, $tok) ?>
          </ul>

          <div class="mj-bloque-pie mj-bloque-pie-izq">
            <?php /* El enlace sigue sirviendo sin JavaScript; con él, se abre
                     el cuadro sin salir de la ventana. */ ?>
            <a class="mj-btn mj-btn-2" href="?agregar=1" data-accion="nueva-casilla"><?= mj_icono('mas', 16) ?><span>Agregar otra casilla</span></a>
            <a class="mj-btn mj-btn-2" href="?salir=1"><?= mj_icono('salir', 16) ?><span>Cerrar sesión</span></a>
          </div>
        </section>

      </div>
    </div>
  </section>
<?php }

/** Las filas de las casillas guardadas. Las usan la vista y el guardado. */
function mj_v_casillas(string $correo, array $otras, string $tok): string
{
  $actual = strtolower($correo);
  $vistas = array_map(fn($g) => strtolower($g['usuario']), $otras);
  if (!in_array($actual, $vistas, true) && $actual !== '') {
    array_unshift($otras, ['usuario' => $actual]);
  }

  ob_start();
  foreach ($otras as $g): $es = strtolower($g['usuario']) === $actual; ?>
    <li class="mj-set-casilla<?= $es ? ' is-activa' : '' ?>">
      <?= mj_v_avatar(['nombre' => '', 'email' => $g['usuario']], 28) ?>
      <span class="mj-set-casilla-txt"><?= mj_e($g['usuario']) ?></span>
      <?php if ($es): ?>
        <span class="mj-set-marca"><?= mj_icono('check', 15) ?> En uso</span>
      <?php else: ?>
        <a class="mj-btn mj-btn-2 mj-btn-chico" href="?cuenta=<?= rawurlencode($g['usuario']) ?>&amp;t=<?= mj_e($tok) ?>">Usar</a>
        <a class="mj-icono-btn" title="Quitar de este equipo"
           aria-label="Quitar <?= mj_e($g['usuario']) ?> de este equipo"
           href="?olvidar=<?= rawurlencode($g['usuario']) ?>&amp;t=<?= mj_e($tok) ?>"><?= mj_icono('cerrar', 15) ?></a>
      <?php endif; ?>
    </li>
  <?php endforeach;
  return (string) ob_get_clean();
}

/** Cuadro para agregar otra casilla sin salir de la ventana */
function mj_v_nueva_casilla(): void
{ ?>
  <div class="mj-modal" data-modal="casilla" hidden>
    <div class="mj-modal-fondo" data-accion="cerrar-modal"></div>
    <div class="mj-modal-caja mj-modal-chica" role="dialog" aria-modal="true" aria-labelledby="mj-cas-t">
      <header class="mj-modal-cab">
        <h2 class="mj-h2" id="mj-cas-t">Agregar otra casilla</h2>
        <button class="mj-icono-btn" type="button" data-accion="cerrar-modal" aria-label="Cerrar"><?= mj_icono('cerrar', 18) ?></button>
      </header>
      <form class="mj-form" data-rol="form-casilla"
            data-token="<?= mj_e(function_exists('mj_token_sesion') ? mj_token_sesion() : '') ?>">
        <p class="mj-set-nota">
          Quedará guardada en este equipo para cambiar de una a otra sin volver a
          escribir la contraseña. No lo hagas en un computador compartido.
        </p>
        <label class="mj-campo">
          <span>Correo</span>
          <input type="email" name="correo" placeholder="otra@tudominio.cl" autocomplete="off" required>
        </label>
        <label class="mj-campo">
          <span>Contraseña</span>
          <input type="password" name="clave" autocomplete="new-password" required>
        </label>
        <div class="mj-modal-pie">
          <div class="mj-modal-pie-btns">
            <button class="mj-btn mj-btn-2" type="button" data-accion="cerrar-modal">Cancelar</button>
            <button class="mj-btn" type="submit">Agregar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php }

/** Las variables de tema que van en el atributo style de la raíz */
function mj_estilo_tema(array $cfg): string
{
  return '--mj-radio:' . $cfg['tema']['radio'] . ';'
       . '--mj-ancho:' . $cfg['tema']['ancho_maximo'] . ';'
       . '--mj-alto:'  . ($cfg['tema']['alto'] ?? '100svh') . ';'
       . '--mj-fuente:' . $cfg['tema']['fuente_texto'] . ';'
       . '--mj-fuente-titulo:' . $cfg['tema']['fuente_titulo'] . ';'
       . '--mj-fuente-marca:' . ($cfg['tema']['fuente_marca'] ?? $cfg['tema']['fuente_titulo']) . ';'
       . (!empty($cfg['tema']['color_marca']) ? '--mj-marca-color:' . $cfg['tema']['color_marca'] . ';' : '')
       . (!empty($cfg['tema']['acento']) ? '--mj-acento:' . $cfg['tema']['acento'] . ';' : '')
       . ($cfg['tema']['fondo'] === 'imagen' && $cfg['tema']['fondo_imagen']
           ? "--mj-fondo-img:url('" . mj_e($cfg['tema']['fondo_imagen']) . "');" : '');
}

/** La agenda: se llena sola con cada envío, y se puede editar a mano */
function mj_v_contactos(array $cfg, array $contactos): void
{
  ?>
  <section class="mj-agenda" aria-label="Contactos">
    <div class="mj-agenda-col">

      <header class="mj-agenda-cab">
        <div class="mj-agenda-titulo">
          <?php if ($cfg['interfaz']['mostrar_carpetas']): ?>
            <button class="mj-icono-btn mj-solo-movil" type="button" data-accion="abrir-carpetas"
                    aria-label="Abrir el menú de carpetas"><?= mj_icono('menu', 20) ?></button>
          <?php endif; ?>
          <h1 class="mj-h1">Contactos</h1>
          <button class="mj-btn mj-agenda-nuevo" type="button" data-accion="nuevo-contacto">
            <?= mj_icono('persona_mas', 17) ?><span>Agregar</span>
          </button>
        </div>
        <p class="mj-agenda-nota">
          <span data-rol="cuantos-contactos"><?= count($contactos) === 1
              ? '1 persona' : mj_e((string) count($contactos)) . ' personas' ?></span>
          · se agregan solas cada vez que envías un mensaje
        </p>
        <div class="mj-buscador mj-agenda-buscar" <?= $contactos ? '' : 'hidden' ?>>
          <?= mj_icono('buscar', 18) ?>
          <input type="search" placeholder="Buscar contacto…" aria-label="Buscar entre los contactos"
                 data-rol="buscar-contacto" autocomplete="off" spellcheck="false">
        </div>
      </header>

      <ul class="mj-agenda-lista" data-rol="lista-contactos">
        <?php foreach ($contactos as $c) { mj_v_contacto($c); } ?>
      </ul>

      <div class="mj-vacio" data-rol="sin-contactos" <?= $contactos ? 'hidden' : '' ?>>
        <?= mj_icono('personas', 34) ?>
        <p class="mj-vacio-t">Todavía no hay contactos</p>
        <p class="mj-vacio-d">Se agregan solos al enviar un mensaje, o puedes anotar uno tú.</p>
        <button class="mj-btn mj-btn-2" type="button" data-accion="nuevo-contacto">
          <?= mj_icono('persona_mas', 17) ?><span>Agregar contacto</span>
        </button>
      </div>

      <div class="mj-vacio" data-rol="sin-coincidencias" hidden>
        <?= mj_icono('buscar', 34) ?>
        <p class="mj-vacio-t">Ningún contacto coincide</p>
        <p class="mj-vacio-d">Prueba con otro nombre o con parte de la dirección.</p>
      </div>

    </div>
  </section>
<?php }

/** Una ficha de la agenda. La usan la lista y el guardado por JS. */
function mj_v_contacto(array $c): void
{
  $persona = ['nombre' => $c['nombre'] ?? '', 'email' => $c['email']];
  $busq    = mb_strtolower(($c['nombre'] ?? '') . ' ' . $c['email'] . ' ' . ($c['telefono'] ?? ''), 'UTF-8');
  $quien   = ($c['nombre'] ?? '') !== '' ? $c['nombre'] : $c['email'];
  ?>
  <li class="mj-contacto" <?= mj_datos([
        'email'    => $c['email'],
        'nombre'   => $c['nombre'] ?? '',
        'telefono' => $c['telefono'] ?? '',
        'nota'     => $c['nota'] ?? '',
        'buscar'   => $busq,
      ]) ?>>
    <?= mj_v_avatar($persona, 40) ?>
    <div class="mj-contacto-txt">
      <strong class="mj-contacto-nombre"><?= mj_e($quien) ?></strong>
      <span class="mj-contacto-email"><?= mj_e($c['email']) ?></span>
      <?php if (!empty($c['telefono']) || !empty($c['nota'])): ?>
        <span class="mj-contacto-extra">
          <?= mj_e(trim(($c['telefono'] ?? '') . (!empty($c['telefono']) && !empty($c['nota']) ? '  ·  ' : '') . ($c['nota'] ?? ''))) ?>
        </span>
      <?php endif; ?>
    </div>
    <?php if ((int) ($c['envios'] ?? 0) > 0): ?>
      <span class="mj-contacto-veces" title="Mensajes que le has enviado"><?= (int) $c['envios'] ?></span>
    <?php endif; ?>
    <div class="mj-contacto-btns">
      <button class="mj-icono-btn" type="button" data-accion="escribir-a"
              aria-label="Escribir a <?= mj_e($quien) ?>" title="Escribir"><?= mj_icono('enviar', 17) ?></button>
      <button class="mj-icono-btn" type="button" data-accion="editar-contacto"
              aria-label="Editar a <?= mj_e($quien) ?>" title="Editar"><?= mj_icono('lapiz', 17) ?></button>
      <button class="mj-icono-btn" type="button" data-accion="borrar-contacto"
              aria-label="Quitar a <?= mj_e($quien) ?> de la agenda"
              title="Quitar de la agenda"><?= mj_icono('papelera', 17) ?></button>
    </div>
  </li>
<?php }

/** Formulario para dar de alta o corregir un contacto */
function mj_v_ficha_contacto(): void
{ ?>
  <div class="mj-modal" data-modal="contacto" hidden>
    <div class="mj-modal-fondo" data-accion="cerrar-modal"></div>
    <div class="mj-modal-caja mj-modal-chica" role="dialog" aria-modal="true" aria-labelledby="mj-ficha-t">
      <header class="mj-modal-cab">
        <h2 class="mj-h2" id="mj-ficha-t" data-rol="ficha-titulo">Nuevo contacto</h2>
        <button class="mj-icono-btn" type="button" data-accion="cerrar-modal" aria-label="Cerrar"><?= mj_icono('cerrar', 18) ?></button>
      </header>
      <form class="mj-form" data-rol="form-contacto">
        <input type="hidden" name="original" value="">
        <label class="mj-campo">
          <span>Nombre</span>
          <input type="text" name="nombre" placeholder="Nombre y apellido" autocomplete="off">
        </label>
        <label class="mj-campo">
          <span>Correo</span>
          <input type="email" name="email" placeholder="persona@dominio.cl" autocomplete="off" required>
        </label>
        <label class="mj-campo">
          <span>Teléfono</span>
          <input type="tel" name="telefono" placeholder="opcional" autocomplete="off">
        </label>
        <label class="mj-campo">
          <span>Nota</span>
          <input type="text" name="nota" placeholder="opcional — causa, empresa, lo que sirva" autocomplete="off">
        </label>
        <div class="mj-modal-pie">
          <div class="mj-modal-pie-btns">
            <button class="mj-btn mj-btn-2" type="button" data-accion="cerrar-modal">Cancelar</button>
            <button class="mj-btn" type="submit">Guardar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php }

/** Columna de carpetas */
function mj_v_carpetas(array $cfg, string $carpeta, array $conteo, int $sinVer = 0): void
{
  $t = $cfg['textos'];
  $enlace = fn($id) => '?' . http_build_query(['carpeta' => $id]);
  ?>
  <aside class="mj-carpetas" id="mj-carpetas" aria-label="Carpetas de correo">
    <div class="mj-carpetas-cab">
      <h2 class="mj-h2"><?= mj_e($t['correo']) ?></h2>
      <button class="mj-icono-btn mj-solo-movil" type="button" data-accion="cerrar-carpetas"
              aria-label="Cerrar el menú de carpetas"><?= mj_icono('cerrar', 18) ?></button>
    </div>

    <nav class="mj-carpetas-scroll">
      <ul class="mj-nav">
        <?php foreach ($cfg['carpetas'] as $c):
          $n = $c['contador'] === 'no_leidos' ? ($conteo[$c['id']]['no_leidos'] ?? 0)
             : ($c['contador'] === 'total' ? ($conteo[$c['id']]['total'] ?? 0) : 0); ?>
          <li>
            <a class="mj-nav-item<?= $carpeta === $c['id'] ? ' is-activo' : '' ?>"
               href="<?= mj_e($enlace($c['id'])) ?>" data-carpeta="<?= mj_e($c['id']) ?>"
               <?= $carpeta === $c['id'] ? 'aria-current="true"' : '' ?>>
              <?= mj_icono($c['icono'], 19) ?>
              <span><?= mj_e($c['nombre']) ?></span>
              <?php if ($n > 0): ?><em class="mj-badge"><?= $n ?></em><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
        <li>
          <a class="mj-nav-item mj-nav-nuevo<?= $carpeta === 'novedades' ? ' is-activo' : '' ?>"
             href="?carpeta=novedades" data-carpeta="novedades">
            <?= mj_icono('estrella', 19) ?>
            <span>Novedades</span>
            <?php if ($sinVer > 0): ?><em class="mj-badge mj-badge-nuevo"><?= (int) $sinVer ?></em><?php endif; ?>
          </a>
        </li>
      </ul>

      <?php if ($cfg['carpetas_propias'] || $cfg['mostrar_agregar_carpeta']): ?>
        <div class="mj-nav-titulo">
          <span><?= mj_e($t['carpetas']) ?></span>
          <button class="mj-icono-btn" type="button" data-accion="config-carpetas"
                  aria-label="Administrar carpetas"><?= mj_icono('ajustes', 16) ?></button>
        </div>
        <ul class="mj-nav">
          <?php if ($cfg['mostrar_agregar_carpeta']): ?>
            <li>
              <button class="mj-nav-item mj-nav-agregar" type="button" data-accion="nueva-carpeta">
                <?= mj_icono('carpeta_mas', 19) ?><span><?= mj_e($t['agregar_carpeta']) ?></span>
              </button>
            </li>
          <?php endif; ?>
          <?php foreach ($cfg['carpetas_propias'] as $c): ?>
            <li>
              <a class="mj-nav-item<?= $carpeta === $c['id'] ? ' is-activo' : '' ?>"
                 href="<?= mj_e($enlace($c['id'])) ?>" data-carpeta="<?= mj_e($c['id']) ?>"
                 <?php if (!empty($c['color'])): ?>style="--mj-carpeta-color:<?= mj_e($c['color']) ?>"<?php endif; ?>>
                <?= mj_icono($c['icono'] ?? 'carpeta', 19) ?>
                <span><?= mj_e($c['nombre']) ?></span>
                <?php if (!empty($conteo[$c['id']]['total'])): ?>
                  <em class="mj-badge mj-badge-suave"><?= (int) $conteo[$c['id']]['total'] ?></em>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </nav>

    <div class="mj-carpetas-pie">
      <?php
        $dentro  = function_exists('mj_dentro') && mj_dentro();
        $guardadas = [];
        if ($dentro && function_exists('mj_cuentas_lista')) {
          $guardadas = mj_cuentas_lista();
        }
        $tok = $dentro && function_exists('mj_token_sesion') ? mj_token_sesion() : '';
      ?>
      <?php if ($dentro): ?>
        <button class="mj-usuario mj-usuario-btn" type="button" data-accion="cuentas"
                aria-haspopup="true" aria-expanded="false" title="Cambiar de casilla">
          <?= mj_v_avatar(['nombre' => $cfg['usuario']['nombre'], 'avatar' => $cfg['usuario']['avatar']], 32) ?>
          <div class="mj-usuario-txt">
            <strong><?= mj_e($cfg['usuario']['nombre']) ?></strong>
            <span><?= mj_e($cfg['usuario']['email']) ?></span>
          </div>
        </button>

        <div class="mj-cuentas" data-rol="cuentas" hidden>
          <p class="mj-cuentas-t">Casillas en este equipo</p>
          <ul class="mj-cuentas-lista">
            <?php
              $actual = strtolower((string) $cfg['usuario']['email']);
              $vistas = [];
              foreach ($guardadas as $g) {
                $vistas[] = strtolower($g['usuario']);
              }
              if (!in_array($actual, $vistas, true)) {
                array_unshift($guardadas, ['usuario' => $actual]);
              }
              foreach ($guardadas as $g):
                $es = strtolower($g['usuario']) === $actual;
            ?>
              <li class="mj-cuenta<?= $es ? ' is-activa' : '' ?>">
                <?php if ($es): ?>
                  <span class="mj-cuenta-liga">
                    <?= mj_v_avatar(['nombre' => '', 'email' => $g['usuario']], 26) ?>
                    <span class="mj-cuenta-txt"><?= mj_e($g['usuario']) ?></span>
                    <?= mj_icono('check', 15) ?>
                  </span>
                <?php else: ?>
                  <a class="mj-cuenta-liga" href="?cuenta=<?= rawurlencode($g['usuario']) ?>&amp;t=<?= mj_e($tok) ?>">
                    <?= mj_v_avatar(['nombre' => '', 'email' => $g['usuario']], 26) ?>
                    <span class="mj-cuenta-txt"><?= mj_e($g['usuario']) ?></span>
                  </a>
                  <a class="mj-icono-btn mj-cuenta-x" title="Quitar de este equipo"
                     aria-label="Quitar <?= mj_e($g['usuario']) ?> de este equipo"
                     href="?olvidar=<?= rawurlencode($g['usuario']) ?>&amp;t=<?= mj_e($tok) ?>"><?= mj_icono('cerrar', 14) ?></a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <a class="mj-cuentas-mas" href="?agregar=1" data-accion="nueva-casilla"><?= mj_icono('mas', 16) ?><span>Agregar otra casilla</span></a>
          <p class="mj-cuentas-nota">Las contraseñas se guardan cifradas en el servidor. No lo hagas en un equipo compartido.</p>
        </div>
      <?php else: ?>
        <div class="mj-usuario">
          <?= mj_v_avatar(['nombre' => $cfg['usuario']['nombre'], 'avatar' => $cfg['usuario']['avatar']], 32) ?>
          <div class="mj-usuario-txt">
            <strong><?= mj_e($cfg['usuario']['nombre']) ?></strong>
            <span><?= mj_e($cfg['usuario']['email']) ?></span>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($dentro): ?>
        <a class="mj-icono-btn<?= $carpeta === 'cuenta' ? ' is-activo' : '' ?>" href="?carpeta=cuenta"
           title="Ajustes de tu cuenta" aria-label="Ajustes de tu cuenta">
          <?= mj_icono('ajustes', 18) ?>
        </a>
        <a class="mj-icono-btn mj-salir" href="?salir=1" title="Cerrar sesión" aria-label="Cerrar sesión">
          <?= mj_icono('salir', 18) ?>
        </a>
      <?php endif; ?>
      <?php if ($cfg['tema']['permitir_cambio_modo']): ?>
        <button class="mj-icono-btn" type="button" data-accion="modo" aria-label="Cambiar entre modo claro y oscuro">
          <span class="mj-modo-claro"><?= mj_icono('luna', 18) ?></span>
          <span class="mj-modo-oscuro"><?= mj_icono('sol', 18) ?></span>
        </button>
      <?php endif; ?>
    </div>
  </aside>
<?php }

/** Columna del listado */
function mj_v_lista(array $cfg, array $lista, string $carpeta, string $filtro, string $busca, ?array $sel, int $sinVer = 0): void
{
  $t = $cfg['textos'];
  $nombre_carpeta = 'Correo';
  foreach (array_merge($cfg['carpetas'], $cfg['carpetas_propias']) as $c) {
    if ($c['id'] === $carpeta) { $nombre_carpeta = $c['nombre']; break; }
  }
  $grupo_previo = '';
  ?>
  <section class="mj-col-lista" aria-label="Lista de mensajes">

    <header class="mj-lista-cab">
      <?php if ($cfg['interfaz']['mostrar_carpetas']): ?>
        <button class="mj-icono-btn mj-solo-movil" type="button" data-accion="abrir-carpetas"
                aria-label="Abrir el menú de carpetas"><?= mj_icono('menu', 20) ?></button>
      <?php endif; ?>
      <h1 class="mj-h1" data-rol="titulo"><?= mj_e($nombre_carpeta) ?></h1>
      <button class="mj-icono-btn mj-refrescar" type="button" data-accion="refrescar"
              aria-label="Actualizar la lista"><?= mj_icono('refrescar', 18) ?></button>
      <?php if ($cfg['interfaz']['boton_redactar']): ?>
        <button class="mj-redactar" type="button" data-accion="redactar"
                aria-label="<?= mj_e($t['redactar']) ?>" title="<?= mj_e($t['redactar']) ?>">
          <?= mj_icono('mas', 20) ?>
        </button>
      <?php endif; ?>
    </header>

    <?php if ($sinVer > 0): ?>
      <a class="mj-avisonov" href="?carpeta=novedades">
        <span class="mj-avisonov-i"><?= mj_icono('estrella', 15) ?></span>
        <span class="mj-avisonov-t">
          <strong><?= $sinVer === 1 ? 'Hay una novedad' : 'Hay ' . (int) $sinVer . ' novedades' ?> en tu correo</strong>
          <span>Mira qué cambió y cómo usarlo</span>
        </span>
        <?= mj_icono('adelante', 15) ?>
      </a>
    <?php endif; ?>

    <?php if ($cfg['interfaz']['mostrar_buscador']): ?>
      <div class="mj-buscador">
        <?= mj_icono('buscar', 18) ?>
        <input type="search" id="mj-buscar" name="q" value="<?= mj_e($busca) ?>"
               placeholder="<?= mj_e($t['buscar']) ?>" aria-label="<?= mj_e($t['buscar']) ?>"
               autocomplete="off" spellcheck="false">
        <button class="mj-limpiar" type="button" data-accion="limpiar-busqueda"
                aria-label="Limpiar la búsqueda" <?= $busca === '' ? 'hidden' : '' ?>><?= mj_icono('cerrar', 14) ?></button>
      </div>
    <?php endif; ?>

    <?php if ($cfg['interfaz']['mostrar_filtros']): ?>
      <div class="mj-filtros" role="tablist" aria-label="Filtrar mensajes">
        <?php foreach (['todos' => $t['todos'], 'leidos' => $t['leidos'], 'no_leidos' => $t['no_leidos']] as $k => $txt): ?>
          <a role="tab" class="mj-filtro<?= $filtro === $k ? ' is-activo' : '' ?>"
             href="?<?= mj_e(http_build_query(['carpeta' => $carpeta, 'f' => $k])) ?>"
             data-filtro="<?= $k ?>" aria-selected="<?= $filtro === $k ? 'true' : 'false' ?>">
            <?= mj_e($txt) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($cfg['interfaz']['seleccion_multiple']): ?>
      <div class="mj-barra-sel" hidden>
        <label class="mj-check mj-check-todos">
          <input type="checkbox" data-accion="marcar-todos" aria-label="Seleccionar todos los mensajes">
          <span></span>
        </label>
        <strong data-rol="conteo-sel">0</strong>
        <span><?= mj_e($t['seleccionados']) ?></span>
        <div class="mj-barra-sel-acciones">
          <button class="mj-icono-btn" type="button" data-masivo="leido" aria-label="Marcar como leídos"><?= mj_icono('sobre_abrir', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-masivo="destacar" aria-label="Destacar"><?= mj_icono('estrella', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-masivo="archivar" aria-label="Archivar"><?= mj_icono('archivar', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-masivo="eliminar" aria-label="Eliminar"><?= mj_icono('papelera', 17) ?></button>
          <button class="mj-icono-btn" type="button" data-accion="cancelar-sel" aria-label="Cancelar selección"><?= mj_icono('cerrar', 17) ?></button>
        </div>
      </div>
    <?php endif; ?>

    <?php if (($mj_fallo = mj_fallo_imap()) !== null): ?>
      <p class="mj-aviso-demo">
        <strong>Estás viendo mensajes de ejemplo.</strong>
        No se pudo leer la casilla real: <?= mj_e($mj_fallo) ?>
        Revisa los datos en <code>instalar.php</code>.
      </p>
    <?php elseif (($cfg['origen']['tipo'] ?? 'demo') !== 'imap'): ?>
      <p class="mj-aviso-demo">
        <strong>Estás viendo mensajes de ejemplo.</strong>
        Para leer tu casilla real, elige <em>Cuenta real por IMAP</em> en <code>instalar.php</code>.
      </p>
    <?php endif; ?>

    <ol class="mj-lista" id="mj-lista">
      <?php
      $pasa = static fn(array $m): bool => $filtro === 'todos'
        || ($filtro === 'leidos' && $m['leido'])
        || ($filtro === 'no_leidos' && !$m['leido']);

      foreach ($lista as $i => $m):
        if ($cfg['interfaz']['agrupar_por_fecha']):
          $g = mj_grupo_fecha($m['fecha'], $cfg);
          if ($g !== $grupo_previo): $grupo_previo = $g;
            // El separador se oculta si su grupo no tiene ningún mensaje visible
            $hay = false;
            foreach (array_slice($lista, $i) as $sig) {
              if (mj_grupo_fecha($sig['fecha'], $cfg) !== $g) break;
              if ($pasa($sig)) { $hay = true; break; }
            } ?>
            <li class="mj-sep" data-grupo="<?= $g ?>" aria-hidden="true" <?= $hay ? '' : 'hidden' ?>><?= mj_e($cfg['textos'][$g] ?? $g) ?></li>
          <?php endif;
        endif;
        mj_v_item($cfg, $m, $sel && $sel['id'] === $m['id'], $carpeta, !$pasa($m));
      endforeach; ?>
    </ol>

    <div class="mj-vacio" <?= $lista ? 'hidden' : '' ?> data-rol="vacio">
      <?= mj_icono('bandeja', 34) ?>
      <p class="mj-vacio-t"><?= mj_e($t['sin_mensajes']) ?></p>
      <p class="mj-vacio-d"><?= mj_e($t['sin_mensajes_desc']) ?></p>
    </div>
    <div class="mj-vacio" hidden data-rol="sin-resultados">
      <?= mj_icono('buscar', 34) ?>
      <p class="mj-vacio-t"><?= mj_e($t['sin_resultados']) ?></p>
      <p class="mj-vacio-d">Prueba con otras palabras o revisa otra carpeta.</p>
    </div>

    <footer class="mj-lista-pie">
      <?php $visibles = count(array_filter($lista, $pasa)); ?>
      <span><?= $visibles ?> mensaje<?= $visibles === 1 ? '' : 's' ?></span>
      <?php if ($cfg['interfaz']['atajos_teclado'] && $cfg['interfaz']['mostrar_ayuda_atajos']): ?>
        <button type="button" class="mj-link" data-accion="atajos"><?= mj_icono('teclado', 15) ?> <?= mj_e($t['atajos']) ?></button>
      <?php endif; ?>
    </footer>
  </section>
<?php }

/** Una fila del listado */
function mj_v_item(array $cfg, array $m, bool $activo, string $carpeta, bool $oculto = false): void
{
  $url = '?' . http_build_query(['carpeta' => $carpeta, 'm' => $m['id']]);
  $persona = in_array($m['carpeta'], ['enviados', 'borrador'], true)
    ? ($m['para'][0] ?? $m['de']) : $m['de'];
  $busq = mb_strtolower(
    $persona['nombre'] . ' ' . $persona['email'] . ' ' . $m['asunto'] . ' ' . $m['extracto'], 'UTF-8');
  ?>
  <li class="mj-item<?= $activo ? ' is-activo' : '' ?><?= $m['leido'] ? '' : ' is-nuevo' ?>"
      <?= $oculto ? 'hidden' : '' ?>
      <?= mj_datos([
        'id'        => $m['id'],
        'leido'     => $m['leido'] ? '1' : '0',
        'destacado' => $m['destacado'] ?: '',
        'buscar'    => $busq,
        'grupo'     => mj_grupo_fecha($m['fecha'], $cfg),
        // los usa la ventana de confirmación para decir de quién y cuántos
        'nombre'    => $persona['nombre'] ?: $persona['email'],
        'hilo'      => (string) max(1, (int) ($m['en_hilo'] ?? 1)),
      ]) ?>>
    <a class="mj-item-liga" href="<?= mj_e($url) ?>">
      <span class="mj-sr">Abrir mensaje: <?= mj_e($m['asunto']) ?></span>
    </a>

    <?php if ($cfg['interfaz']['seleccion_multiple']): ?>
      <label class="mj-check mj-item-check">
        <input type="checkbox" aria-label="Seleccionar: <?= mj_e($m['asunto']) ?>"><span></span>
      </label>
    <?php endif; ?>

    <span class="mj-punto" aria-hidden="true"></span>

    <?php if ($cfg['interfaz']['mostrar_avatares']): ?>
      <?= mj_v_avatar($persona, 38) ?>
    <?php endif; ?>

    <div class="mj-item-txt">
      <div class="mj-item-linea">
        <span class="mj-de"><?= mj_e($persona['nombre'] ?: $persona['email']) ?></span>
        <time class="mj-hora" datetime="<?= mj_e($m['fecha']) ?>"><?= mj_e(mj_fecha_corta($m['fecha'], $cfg)) ?></time>
      </div>
      <div class="mj-item-asunto">
        <?= mj_e($m['asunto']) ?>
        <?php if (($m['en_hilo'] ?? 1) > 1): ?>
          <span class="mj-item-hilo" title="Mensajes en esta conversación"><?= (int) $m['en_hilo'] ?></span>
        <?php endif; ?>
      </div>
      <?php if ($cfg['interfaz']['mostrar_extracto']): ?>
        <p class="mj-item-extracto" style="--mj-lineas:<?= (int) $cfg['interfaz']['lineas_extracto'] ?>">
          <?= mj_e($m['extracto']) ?>
        </p>
      <?php endif; ?>
      <?php if ($m['etiquetas'] || ($m['adjuntos'] && $cfg['interfaz']['mostrar_adjuntos'])): ?>
        <div class="mj-item-meta">
          <?php foreach ($m['etiquetas'] as $et):
            $e = $cfg['etiquetas'][$et] ?? null; if (!$e) continue; ?>
            <span class="mj-tag" style="--mj-tag:<?= mj_e($e['color']) ?>"><?= mj_e($e['nombre']) ?></span>
          <?php endforeach; ?>
          <?php if ($m['adjuntos'] && $cfg['interfaz']['mostrar_adjuntos']): ?>
            <span class="mj-clip" title="<?= count($m['adjuntos']) ?> adjunto(s)">
              <?= mj_icono('clip', 14) ?><?= count($m['adjuntos']) ?>
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <button class="mj-estrella<?= $m['destacado'] ? ' is-on' : '' ?>" type="button"
            <?php if ($m['destacado']): ?>style="--mj-estrella:<?= mj_e($m['destacado']) ?>"<?php endif; ?>
            aria-label="Destacar mensaje" aria-pressed="<?= $m['destacado'] ? 'true' : 'false' ?>">
      <?= mj_icono('estrella', 17) ?>
    </button>
  </li>
<?php }

/** Panel de lectura */
function mj_v_lector(array $cfg, ?array $sel, array $conversacion = []): void
{
  $t = $cfg['textos'];
  ?>
  <section class="mj-lector" aria-label="Mensaje">
    <header class="mj-lector-barra">
      <button class="mj-btn-txt mj-solo-movil" type="button" data-accion="volver">
        <?= mj_icono('atras', 18) ?><span><?= mj_e($t['volver']) ?></span>
      </button>
      <div class="mj-acciones">
        <?php foreach ($cfg['acciones_lector'] as $a): ?>
          <button class="mj-btn-txt" type="button" data-accion="<?= mj_e($a['id']) ?>">
            <?= mj_icono($a['icono'], 17) ?><span><?= mj_e($a['texto']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="mj-acciones-fin">
        <button class="mj-icono-btn" type="button" data-accion="agendar" aria-label="Agendar en el calendario">
          <?= mj_icono('calendario', 19) ?>
        </button>
      </div>
    </header>

    <div class="mj-lector-scroll" data-rol="lector">
      <?php if ($sel) { mj_v_lector_contenido($cfg, $sel, $conversacion); } else { ?>
        <div class="mj-vacio mj-vacio-lector">
          <?= mj_icono('sobre_abrir', 34) ?>
          <p class="mj-vacio-t"><?= mj_e($t['sin_seleccion']) ?></p>
          <p class="mj-vacio-d"><?= mj_e($t['sin_seleccion_desc']) ?></p>
        </div>
      <?php } ?>
    </div>
  </section>
<?php }

/** Contenido de un mensaje dentro del lector */
function mj_v_lector_contenido(array $cfg, array $m, array $conversacion = []): void
{
  $t     = $cfg['textos'];
  $html  = mj_plegar_citado(mj_html_seguro($m['cuerpo'], $cfg));
  $block = mj_tiene_imagenes_bloqueadas($html);
  ?>
  <article class="mj-msg"
           data-id="<?= mj_e($m['id']) ?>"
           data-nombre="<?= mj_e($m['de']['nombre']) ?>"
           data-id-mensaje="<?= mj_e($m['id_mensaje'] ?? '') ?>"
           data-email="<?= mj_e($m['de']['email']) ?>"
           data-asunto="<?= mj_e($m['asunto']) ?>"
           data-todos="<?= mj_e(implode(', ', array_filter(array_merge(
                 array_column($m['para'], 'email'), array_column($m['cc'], 'email'))))) ?>">
    <header class="mj-msg-cab">
      <?= mj_v_avatar($m['de'], 46) ?>
      <div class="mj-msg-quien">
        <div class="mj-msg-linea">
          <strong class="mj-msg-de"><?= mj_e($m['de']['nombre'] ?: $m['de']['email']) ?></strong>
          <time class="mj-msg-fecha" datetime="<?= mj_e($m['fecha']) ?>"><?= mj_e(mj_fecha_larga($m['fecha'], $cfg)) ?></time>
        </div>
        <h2 class="mj-msg-asunto"><?= mj_e($m['asunto']) ?></h2>
        <div class="mj-msg-destinos">
          <?php if ($m['para']): ?>
            <span><em><?= mj_e($t['para']) ?>:</em> <?= mj_e(mj_personas($m['para'])) ?></span>
          <?php endif; ?>
          <?php if ($m['cc']): ?>
            <span><em><?= mj_e($t['cc']) ?>:</em> <?= mj_e(mj_personas($m['cc'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <?php if ($m['etiquetas']): ?>
      <div class="mj-msg-tags">
        <?php foreach ($m['etiquetas'] as $et):
          $e = $cfg['etiquetas'][$et] ?? null; if (!$e) continue; ?>
          <span class="mj-tag" style="--mj-tag:<?= mj_e($e['color']) ?>"><?= mj_e($e['nombre']) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($block): ?>
      <div class="mj-aviso-img">
        <?= mj_icono('spam', 16) ?>
        <span><?= mj_e($t['imagenes_bloqueadas']) ?></span>
        <button type="button" class="mj-link" data-accion="mostrar-imagenes"><?= mj_e($t['mostrar_imagenes']) ?></button>
      </div>
    <?php endif; ?>

    <?php if ($conversacion): ?>
      <div class="mj-hilo">
        <p class="mj-hilo-titulo"><?= count($conversacion) ?>
          <?= count($conversacion) === 1 ? 'mensaje anterior' : 'mensajes anteriores' ?> en esta conversación</p>

        <?php foreach ($conversacion as $previo):
              $quien = $previo['de']['nombre'] ?: $previo['de']['email']; ?>
          <details class="mj-hilo-item">
            <summary>
              <b><?= mj_e($quien) ?></b>
              <span><?= mj_e(mb_substr(trim(strip_tags($previo['cuerpo'])), 0, 70)) ?></span>
              <time><?= mj_e(mj_fecha_corta($previo['fecha'], $cfg)) ?></time>
            </summary>
            <div class="mj-hilo-cuerpo"><?= mj_plegar_citado(mj_html_seguro($previo['cuerpo'], $cfg)) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="mj-msg-cuerpo"><?= $html ?></div>

    <?php if ($m['adjuntos'] && $cfg['interfaz']['mostrar_adjuntos']): ?>
      <section class="mj-adjuntos">
        <h3 class="mj-h3"><?= mj_icono('clip', 15) ?> <?= mj_e($t['adjuntos']) ?> (<?= count($m['adjuntos']) ?>)</h3>
        <ul>
          <?php foreach ($m['adjuntos'] as $a): ?>
            <li class="mj-adjunto">
              <span class="mj-adjunto-tipo" data-tipo="<?= mj_e($a['tipo']) ?>"><?= mj_e(strtoupper($a['tipo'] ?: '?')) ?></span>
              <span class="mj-adjunto-nom"><?= mj_e($a['nombre']) ?></span>
              <span class="mj-adjunto-peso"><?= mj_e(mj_peso($a['peso'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <footer class="mj-msg-pie">
      <button class="mj-btn" type="button" data-accion="responder"><?= mj_icono('responder', 17) ?> Responder</button>
      <button class="mj-btn mj-btn-2" type="button" data-accion="reenviar"><?= mj_icono('reenviar', 17) ?> Reenviar</button>
    </footer>
  </article>
<?php }

/** Menú contextual (clic derecho) */
function mj_v_menu(array $cfg): void
{ ?>
  <div class="mj-menu" role="menu" hidden aria-label="Acciones del mensaje">
    <?php foreach ($cfg['menu_contextual_items'] as $it):
      if (!empty($it['sep'])) { echo '<hr class="mj-menu-sep">'; continue; }
      $tipo = $it['tipo'] ?? 'simple'; ?>

      <?php if ($tipo === 'colores'): ?>
        <div class="mj-menu-item mj-menu-colores">
          <span><?= mj_e($it['texto']) ?></span>
          <span class="mj-colores">
            <?php foreach ($cfg['colores_estrella'] as $c): ?>
              <button type="button" class="mj-color" style="--mj-c:<?= mj_e($c) ?>"
                      data-menu="destacar" data-color="<?= mj_e($c) ?>"
                      aria-label="Destacar en <?= mj_e($c) ?>"><?= mj_icono('estrella', 14) ?></button>
            <?php endforeach; ?>
          </span>
        </div>

      <?php elseif ($tipo === 'carpetas'): ?>
        <div class="mj-menu-item mj-menu-sub" tabindex="0" role="menuitem" aria-haspopup="true">
          <span><?= mj_e($it['texto']) ?></span><?= mj_icono('adelante', 14) ?>
          <div class="mj-submenu" role="menu">
            <?php foreach (array_merge($cfg['carpetas'], $cfg['carpetas_propias']) as $c): ?>
              <button type="button" class="mj-menu-item" role="menuitem"
                      data-menu="<?= mj_e($it['id']) ?>" data-destino="<?= mj_e($c['id']) ?>">
                <?= mj_icono($c['icono'] ?? 'carpeta', 15) ?><span><?= mj_e($c['nombre']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

      <?php else: ?>
        <button type="button" class="mj-menu-item" role="menuitem" data-menu="<?= mj_e($it['id']) ?>">
          <span><?= mj_e($it['texto']) ?></span>
        </button>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php }

/** Ventana de redacción (solo vista: no envía todavía) */
function mj_v_compositor(array $cfg): void
{
  $t = $cfg['textos']; ?>
  <div class="mj-modal" data-modal="redactar" hidden>
    <div class="mj-modal-fondo" data-accion="cerrar-modal"></div>
    <div class="mj-modal-caja" role="dialog" aria-modal="true" aria-label="<?= mj_e($t['redactar']) ?>">
      <header class="mj-modal-cab">
        <h2 class="mj-h2"><?= mj_e($t['redactar']) ?></h2>
        <button class="mj-icono-btn" type="button" data-accion="cerrar-modal" aria-label="Cerrar"><?= mj_icono('cerrar', 18) ?></button>
      </header>
      <form class="mj-form" data-rol="form-redactar" data-token="<?= mj_e(function_exists('mj_token_sesion') ? mj_token_sesion() : '') ?>">
        <label class="mj-campo">
          <span><?= mj_e($t['para']) ?></span>
          <input type="hidden" name="responde_a" value="">
          <input type="email" name="para" multiple placeholder="destinatario@correo.cl" autocomplete="off">
        </label>
        <label class="mj-campo">
          <span><?= mj_e($t['cc']) ?></span>
          <input type="email" name="cc" multiple placeholder="opcional" autocomplete="off">
        </label>
        <label class="mj-campo">
          <span><?= mj_e($t['asunto']) ?></span>
          <input type="text" name="asunto" placeholder="Asunto del mensaje">
        </label>
        <label class="mj-campo mj-campo-area">
          <span class="mj-sr">Mensaje</span>
          <textarea name="cuerpo" rows="9" placeholder="Escribe tu mensaje…"></textarea>
        </label>

        <ul class="mj-adjuntar" data-rol="lista-adjuntos" hidden></ul>
        <footer class="mj-modal-pie">
          <div class="mj-modal-pie-iconos">
            <button class="mj-icono-btn" type="button" data-accion="adjuntar"
                    title="Adjuntar archivos" aria-label="Adjuntar archivos"><?= mj_icono('clip', 18) ?></button>
            <input class="mj-sr" type="file" name="adjuntos[]" multiple data-rol="adjuntos"
                   aria-label="Archivos para adjuntar">
            <button class="mj-icono-btn" type="button" aria-label="Descartar"><?= mj_icono('papelera', 18) ?></button>
          </div>
          <div class="mj-modal-pie-btns">
            <button class="mj-btn mj-btn-2" type="button" data-accion="cerrar-modal"><?= mj_e($t['cancelar']) ?></button>
            <button class="mj-btn" type="submit"><?= mj_icono('enviar', 16) ?> <?= mj_e($t['enviar']) ?></button>
          </div>
        </footer>
      </form>
    </div>
  </div>
<?php }

/** Ayuda de atajos */
/** Ventana de confirmación para las acciones sin vuelta atrás */
function mj_v_confirmar(): void
{ ?>
  <div class="mj-modal mj-modal-conf" data-modal="confirmar" hidden>
    <div class="mj-modal-fondo" data-accion="cerrar-modal"></div>
    <div class="mj-conf" role="alertdialog" aria-modal="true" aria-labelledby="mj-conf-t">
      <button class="mj-conf-x" type="button" data-accion="cerrar-modal" aria-label="Cerrar"><?= mj_icono('cerrar', 17) ?></button>
      <div class="mj-conf-cuerpo">
        <span class="mj-conf-icono" aria-hidden="true"><?= mj_icono('papelera', 22) ?></span>
        <h2 class="mj-conf-titulo" id="mj-conf-t" data-rol="confirmar-titulo">Eliminar definitivamente</h2>
        <p class="mj-conf-texto" data-rol="confirmar-texto"></p>
        <p class="mj-conf-nota">Esta acción no se puede deshacer.</p>
      </div>
      <div class="mj-conf-pie">
        <button class="mj-btn mj-btn-2" type="button" data-accion="cerrar-modal">Cancelar</button>
        <button class="mj-btn mj-btn-peligro" type="button" data-rol="confirmar-si">Eliminar</button>
      </div>
    </div>
  </div>
<?php }

function mj_v_atajos(array $cfg): void
{
  $filas = [
    ['↑ / ↓ · J / K', 'Moverse por la lista'],
    ['Enter',          'Abrir el mensaje'],
    ['Esc',            'Cerrar el mensaje o la ventana'],
    ['/',              'Ir al buscador'],
    ['R',              'Responder'],
    ['A',              'Responder a todos'],
    ['F',              'Reenviar'],
    ['E',              'Archivar'],
    ['S',              'Destacar'],
    ['U',              'Marcar como no leído'],
    ['C',              'Redactar un correo nuevo'],
    ['Supr',           'Eliminar'],
    ['?',              'Ver esta ayuda'],
  ]; ?>
  <div class="mj-modal" data-modal="atajos" hidden>
    <div class="mj-modal-fondo" data-accion="cerrar-modal"></div>
    <div class="mj-modal-caja mj-modal-chica" role="dialog" aria-modal="true" aria-label="<?= mj_e($cfg['textos']['atajos']) ?>">
      <header class="mj-modal-cab">
        <h2 class="mj-h2"><?= mj_e($cfg['textos']['atajos']) ?></h2>
        <button class="mj-icono-btn" type="button" data-accion="cerrar-modal" aria-label="Cerrar"><?= mj_icono('cerrar', 18) ?></button>
      </header>
      <ul class="mj-atajos">
        <?php foreach ($filas as [$k, $d]): ?>
          <li><kbd><?= mj_e($k) ?></kbd><span><?= mj_e($d) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php }

/** Avatar con imagen o iniciales */
function mj_v_avatar(array $p, int $tam = 38): string
{
  $nombre = $p['nombre'] ?: ($p['email'] ?? '');
  $st = 'width:' . $tam . 'px;height:' . $tam . 'px';
  if (!empty($p['avatar'])) {
    return '<img class="mj-avatar" src="' . mj_e($p['avatar']) . '" alt="" loading="lazy" style="' . $st . '">';
  }
  return '<span class="mj-avatar mj-avatar-txt" aria-hidden="true" style="' . $st
       . ';--mj-av:' . mj_color_avatar($nombre) . '">' . mj_e(mj_iniciales($nombre)) . '</span>';
}

/* ------------------------------------------------------------
   Internos
   ------------------------------------------------------------ */

/** Lee un parámetro GET validándolo contra una lista blanca */
function mj_param(string $clave, array $validos, string $defecto): string
{
  $v = (string) ($_GET[$clave] ?? '');
  return in_array($v, $validos, true) ? $v : $defecto;
}


