/* ============================================================
   MÓDULO DE CORREO — Interacción (vanilla JS, sin dependencias)
   ------------------------------------------------------------
   Todo es de vista: filtra, abre, destaca, selecciona y avisa.
   Cuando se conecte el correo real, cada acción marcada con
   //  ⇢ API   es el punto donde va la llamada al servidor.
   ============================================================ */
(function () {
  'use strict';

  document.querySelectorAll('.mjmail').forEach(function (raiz) {
    if (raiz.dataset.listo) return;
    raiz.dataset.listo = '1';
    new Correo(raiz);
  });

  function Correo(raiz) {
    var d       = leerDatos(raiz);
    var T       = d.textos || {};
    var OP      = d.opciones || {};
    var AGENDA  = d.contactos || [];
    var lista   = raiz.querySelector('.mj-lista');
    var lector  = raiz.querySelector('[data-rol="lector"]');
    var buscar  = raiz.querySelector('#mj-buscar');
    var menu    = raiz.querySelector('.mj-menu');
    var avisos  = raiz.querySelector('.mj-avisos');
    var plant   = raiz.querySelector('.mj-plantillas');
    var objetivo = null;     // mensaje sobre el que actúa el menú contextual
    var temporizador = null;

    /* ---------------------------------------------------
       MODO CLARO / OSCURO
       --------------------------------------------------- */
    var guardado = almacen('mj-modo');
    if (guardado) raiz.dataset.modo = guardado;
    else if (raiz.dataset.modo === 'auto') {
      raiz.dataset.modo = matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
    }
    if (almacen('mj-rail') === 'abierto') raiz.dataset.rail = 'abierto';

    /* ---------------------------------------------------
       DELEGACIÓN DE EVENTOS
       --------------------------------------------------- */
    raiz.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-accion]');
      if (btn && raiz.contains(btn)) { accion(btn.dataset.accion, btn, ev); return; }

      var opc = ev.target.closest('[data-menu]');
      if (opc) { ev.preventDefault(); accionMenu(opc.dataset.menu, opc); return; }

      var masivo = ev.target.closest('[data-masivo]');
      if (masivo) { accionMasiva(masivo.dataset.masivo); return; }

      var filtro = ev.target.closest('.mj-filtro');
      if (filtro) { ev.preventDefault(); aplicarFiltro(filtro.dataset.filtro); return; }

      var estrella = ev.target.closest('.mj-estrella');
      if (estrella) { ev.preventDefault(); destacar(estrella.closest('.mj-item')); return; }

      var liga = ev.target.closest('.mj-item-liga');
      if (liga) { ev.preventDefault(); abrir(liga.closest('.mj-item'), true); return; }
    });

    raiz.addEventListener('change', function (ev) {
      if (ev.target.closest('.mj-item-check')) refrescarSeleccion();
    });

    /* Buscador ------------------------------------------------ */
    if (buscar) {
      buscar.addEventListener('input', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(filtrar, 110);
        var lim = raiz.querySelector('.mj-limpiar');
        if (lim) lim.hidden = buscar.value === '';
      });
      buscar.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { buscar.value = ''; filtrar(); buscar.blur(); }
      });
    }

    /* Menú contextual ---------------------------------------- */
    if (menu && lista) {
      lista.addEventListener('contextmenu', function (ev) {
        var it = ev.target.closest('.mj-item');
        if (!it) return;
        ev.preventDefault();
        abrirMenu(it, ev.clientX, ev.clientY);
      });
      // Toque largo en móvil
      var pulso = null;
      lista.addEventListener('touchstart', function (ev) {
        var it = ev.target.closest('.mj-item');
        if (!it) return;
        var t = ev.touches[0];
        pulso = setTimeout(function () { abrirMenu(it, t.clientX, t.clientY); }, 500);
      }, { passive: true });
      ['touchend', 'touchmove', 'touchcancel'].forEach(function (e) {
        lista.addEventListener(e, function () { clearTimeout(pulso); }, { passive: true });
      });
      document.addEventListener('click', function (ev) {
        if (!menu.hidden && !menu.contains(ev.target)) cerrarMenu();
      });
      document.addEventListener('scroll', cerrarMenu, true);
    }

    /* Teclado ------------------------------------------------- */
    if (OP.atajos) document.addEventListener('keydown', atajos);

    /* Historial ----------------------------------------------- */
    window.addEventListener('popstate', function (ev) {
      var id = (ev.state && ev.state.m) || new URLSearchParams(location.search).get('m');
      var it = id && lista && lista.querySelector('.mj-item[data-id="' + css(id) + '"]');
      if (it) abrir(it, false); else raiz.dataset.vista = 'lista';
    });

    /* ---------------------------------------------------
       ACCIONES DE BOTONES
       --------------------------------------------------- */
    function accion(nombre, btn, ev) {
      switch (nombre) {
        case 'rail':
          raiz.dataset.rail = raiz.dataset.rail === 'abierto' ? '' : 'abierto';
          almacen('mj-rail', raiz.dataset.rail || 'cerrado');
          break;

        case 'modo':
          raiz.dataset.modo = raiz.dataset.modo === 'oscuro' ? 'claro' : 'oscuro';
          almacen('mj-modo', raiz.dataset.modo);
          break;

        case 'abrir-carpetas':  raiz.dataset.carpetas = 'abierto'; break;
        case 'cerrar-carpetas': raiz.dataset.carpetas = ''; break;

        case 'volver':
          raiz.dataset.vista = 'lista';
          quitarParam('m');
          break;

        case 'limpiar-busqueda':
          if (buscar) { buscar.value = ''; buscar.focus(); }
          btn.hidden = true;
          filtrar();
          break;

        case 'refrescar':
          btn.animate([{ transform: 'rotate(0)' }, { transform: 'rotate(360deg)' }], { duration: 600, easing: 'ease-in-out' });
          //  ⇢ API: volver a pedir la lista de mensajes
          aviso('Bandeja actualizada');
          break;

        case 'redactar':      abrirModal('redactar'); break;
        case 'atajos':        abrirModal('atajos');   break;
        case 'cerrar-modal':  cerrarModal();          break;

        case 'responder':      responder('uno');   break;
        case 'responder_todo': responder('todos'); break;
        case 'reenviar':       responder('reenviar'); break;

        case 'eliminar':
          if (OP.carpeta === 'papelera') { borrarSiempre(itemActivo()); }
          else { quitar(itemActivo(), 'eliminado', 'papelera'); }
          break;
        case 'archivar':   quitar(itemActivo(), 'archivado', 'archivo'); break;
        case 'importante': destacar(itemActivo()); break;

        case 'mostrar-imagenes':
          lector.querySelectorAll('img[data-mj-src]').forEach(function (img) {
            img.src = img.getAttribute('data-mj-src');
            img.removeAttribute('data-mj-src');
          });
          var av = btn.closest('.mj-aviso-img'); if (av) av.remove();
          break;

        case 'escribir-a': {
          var ct = btn.closest('.mj-contacto');
          if (ct) abrirModal('redactar', { para: ct.dataset.email, cc: '', asunto: '', cuerpo: '' });
          break;
        }
        case 'borrar-contacto':
          quitarContacto(btn.closest('.mj-contacto'));
          break;
        case 'cuentas': {
          var caja = raiz.querySelector('[data-rol="cuentas"]');
          if (caja) {
            caja.hidden = !caja.hidden;
            btn.setAttribute('aria-expanded', String(!caja.hidden));
          }
          break;
        }

        case 'nuevo-contacto':
          fichaContacto(null);
          break;
        case 'editar-contacto':
          fichaContacto(btn.closest('.mj-contacto'));
          break;

        case 'nueva-carpeta':
        case 'config-carpetas':
          aviso('Por ahora las carpetas se definen en instalar.php');
          break;

        case 'agendar':
          aviso('Programar el envío todavía no está disponible');
          break;

        case 'marcar-todos':
          var marcar = btn.checked;
          visibles().forEach(function (it) {
            var c = it.querySelector('.mj-item-check input'); if (c) c.checked = marcar;
          });
          refrescarSeleccion();
          break;

        case 'cancelar-sel': limpiarSeleccion(); break;
      }
    }

    /* ---------------------------------------------------
       ABRIR MENSAJE
       --------------------------------------------------- */
    function abrir(item, empujar) {
      if (!item || !lector) return;
      var id = item.dataset.id;
      var tpl = plant && plant.querySelector('template[data-mensaje="' + css(id) + '"]');

      lista.querySelectorAll('.mj-item.is-activo').forEach(function (i) { i.classList.remove('is-activo'); });
      item.classList.add('is-activo');

      if (tpl) {
        lector.replaceChildren(tpl.content.cloneNode(true));
        lector.scrollTop = 0;
      }
      if (OP.autoLeer) marcarLeido(item, true);
      raiz.dataset.vista = 'lector';

      var u = new URL(location.href);
      u.searchParams.set('m', id);
      if (empujar) history.pushState({ m: id }, '', u); else history.replaceState({ m: id }, '', u);
    }

    function itemActivo() { return lista && lista.querySelector('.mj-item.is-activo'); }

    /* ---------------------------------------------------
       ESTADOS DEL MENSAJE
       --------------------------------------------------- */
    /* Envía una acción al servidor. Si falla, avisa: la pantalla ya cambió,
       pero la persona debe saber que en la casilla no se aplicó. */
    function accionServidor(accion, id, valor) {
      var token = raiz.querySelector('[data-rol="form-redactar"]');
      token = token ? (token.dataset.token || '') : '';
      if (!token || !id) return Promise.resolve({ ok: false });

      var datos = new FormData();
      datos.append('accion', accion);
      datos.append('id', id);
      datos.append('valor', valor || '');
      datos.append('token', token);

      return fetch('acciones.php', { method: 'POST', body: datos, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r.ok && r.mensaje) aviso(r.mensaje);
          return r;
        })
        .catch(function () { return { ok: false }; });
    }

    function marcarLeido(item, leido) {
      if (!item || (item.dataset.leido === '1') === leido) return;
      item.dataset.leido = leido ? '1' : '0';
      item.classList.toggle('is-nuevo', !leido);
      badge(leido ? -1 : 1);
      accionServidor(leido ? 'leido' : 'no_leido', item.dataset.id);
    }

    function destacar(item, color) {
      if (!item) return;
      var on = !!item.dataset.destacado && !color;
      var c  = color || (d.colores && d.colores[0]) || '#F59E0B';
      item.dataset.destacado = on ? '' : c;
      var b = item.querySelector('.mj-estrella');
      if (b) {
        b.classList.toggle('is-on', !on);
        b.setAttribute('aria-pressed', on ? 'false' : 'true');
        b.style.setProperty('--mj-estrella', c);
      }
      accionServidor(on ? 'quitar_destacado' : 'destacar', item.dataset.id, c);
    }

    /* Borrado definitivo: sin papelera donde caer y sin Deshacer, así que
       se pregunta con todas las letras antes de tocar nada. */
    function borrarSiempre(item) {
      if (!item) return;

      var quien = (item.dataset.nombre || '').trim();
      var de = quien ? ' de ' + quien : '';
      var cuantos = parseInt(item.dataset.hilo || '1', 10);
      var texto = cuantos > 1
        ? 'Se borrarán los ' + cuantos + ' mensajes de esta conversación' + de + '.'
        : 'Se borrará este mensaje' + de + '.';

      confirmar(texto, function () { borrarYa(item, cuantos); });
    }

    function borrarYa(item, cuantos) {
      var padre = item.parentNode, sig = item.nextSibling;
      if (item.dataset.leido === '0') badge(-1);
      item.remove();
      contar();
      if (item.classList.contains('is-activo')) {
        var otro = visibles()[0];
        if (otro) abrir(otro, false); else vaciarLector();
      }

      accionServidor('borrar', item.dataset.id).then(function (r) {
        if (r && r.ok) {
          aviso(cuantos > 1 ? 'Conversación eliminada definitivamente' : 'Mensaje eliminado definitivamente');
        } else {
          // no se pudo: se devuelve a su sitio para no mentir
          padre.insertBefore(item, sig);
          contar(); filtrar();
        }
      });
    }

    function quitar(item, verbo, destino) {
      if (!item) return;
      if (OP.confirmar && !confirm('¿Eliminar este mensaje?')) return;
      var padre = item.parentNode, sig = item.nextSibling;
      if (!item.dataset.leido || item.dataset.leido === '0') badge(-1);
      item.remove();
      contar();
      if (item.classList.contains('is-activo')) {
        var otro = visibles()[0];
        if (otro) abrir(otro, false); else vaciarLector();
      }
      var carpeta = destino || (verbo && verbo.indexOf('archiv') === 0 ? 'archivo' : 'papelera');
      accionServidor('mover', item.dataset.id, carpeta);

      aviso('Mensaje ' + verbo, T.deshacer || 'Deshacer', function () {
        padre.insertBefore(item, sig);
        if (item.dataset.leido === '0') badge(1);
        contar(); filtrar();
        // se devuelve a donde estaba
        accionServidor('mover', item.dataset.id, item.dataset.carpeta || 'entrada');
      });
    }

    function accionMasiva(tipo) {
      var sel = seleccionados();
      if (!sel.length) return;
      sel.forEach(function (it) {
        if (tipo === 'leido')    marcarLeido(it, true);
        if (tipo === 'destacar') destacar(it, (d.colores || [])[0]);
        if (tipo === 'archivar' || tipo === 'eliminar') {
          if (it.dataset.leido === '0') badge(-1);
          it.remove();
        }
      });
      if (tipo === 'archivar' || tipo === 'eliminar') { contar(); vaciarSiFalta(); }
      limpiarSeleccion();
      aviso(sel.length + ' mensaje' + (sel.length > 1 ? 's' : '') + ' ' +
            ({ leido: 'marcados como leídos', destacar: 'destacados', archivar: 'archivados', eliminar: 'eliminados' }[tipo] || ''));
    }

    /* ---------------------------------------------------
       MENÚ CONTEXTUAL
       --------------------------------------------------- */
    function abrirMenu(item, x, y) {
      objetivo = item;
      menu.hidden = false;
      menu.style.visibility = 'hidden';
      menu.style.left = '0px'; menu.style.top = '0px';
      var r = menu.getBoundingClientRect();
      var iz = Math.min(x, innerWidth  - r.width  - 8);
      var ar = Math.min(y, innerHeight - r.height - 8);
      menu.style.left = Math.max(8, iz) + 'px';
      menu.style.top  = Math.max(8, ar) + 'px';
      menu.querySelectorAll('.mj-submenu').forEach(function (s) {
        s.classList.toggle('mj-izq', iz + r.width + 190 > innerWidth);

        // Si el submenú no cabe hacia abajo, se ancla por su borde inferior
        // en vez de quedar cortado por el borde de la ventana.
        var fila = s.parentNode.getBoundingClientRect();
        var alto = s.scrollHeight;
        s.classList.toggle('mj-arriba', fila.top + alto + 16 > innerHeight);
      });
      menu.style.visibility = '';
      var no = menu.querySelector('[data-menu="no_leido"]');
      if (no) no.querySelector('span').textContent =
        item.dataset.leido === '1' ? 'Marcar como no leído' : 'Marcar como leído';

      // En la papelera no hay a dónde mover: ahí eliminar es para siempre
      var eliminar = menu.querySelector('[data-menu="eliminar"]');
      if (eliminar) {
        eliminar.querySelector('span').textContent =
          OP.carpeta === 'papelera' ? 'Eliminar permanentemente' : 'Eliminar';
        eliminar.classList.toggle('mj-menu-peligro', OP.carpeta === 'papelera');
      }
      var archivar = menu.querySelector('[data-menu="archivar"]');
      if (archivar) archivar.hidden = (OP.carpeta === 'papelera');
    }

    function cerrarMenu() { if (menu && !menu.hidden) { menu.hidden = true; objetivo = null; } }

    function accionMenu(id, btn) {
      var item = objetivo || itemActivo();
      cerrarMenu();
      if (!item) return;
      switch (id) {
        case 'abrir':      abrir(item, true); break;
        case 'no_leido':   marcarLeido(item, item.dataset.leido !== '1'); break;
        case 'destacar':   destacar(item, btn.dataset.color); break;
        case 'eliminar':
          if (OP.carpeta === 'papelera') { borrarSiempre(item); }
          else { quitar(item, 'eliminado', 'papelera'); }
          break;
        case 'archivar':   quitar(item, 'archivado', 'archivo'); break;
        case 'spam':       quitar(item, 'movido a Spam', 'spam'); break;
        case 'silenciar':  aviso('Silenciar todavía no está disponible'); break;
        case 'mover':
        case 'copiar':
          var destino = btn.dataset.destino;
          var nom = nombreCarpeta(destino);
          if (id === 'mover') { quitar(item, 'movido a ' + nom, destino); }
          else { aviso('Copiar a otra carpeta todavía no está disponible'); }
          break;
        case 'responder':      abrir(item, true); responder('uno'); break;
        case 'responder_todo': abrir(item, true); responder('todos'); break;
        case 'reenviar':
        case 'reenviar_adj':   abrir(item, true); responder('reenviar'); break;
      }
    }

    function nombreCarpeta(id) {
      var c = (d.carpetas || []).filter(function (x) { return x.id === id; })[0];
      return c ? c.nombre : id;
    }

    /* ---------------------------------------------------
       FILTROS Y BÚSQUEDA
       --------------------------------------------------- */
    function aplicarFiltro(f) {
      raiz.dataset.filtro = f;
      raiz.querySelectorAll('.mj-filtro').forEach(function (b) {
        var on = b.dataset.filtro === f;
        b.classList.toggle('is-activo', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      filtrar();
      var u = new URL(location.href); u.searchParams.set('f', f); history.replaceState(history.state, '', u);
    }

    function filtrar() {
      if (!lista) return;
      var q = (buscar ? buscar.value : '').trim().toLowerCase();
      var f = raiz.dataset.filtro || 'todos';
      var n = 0;

      lista.querySelectorAll('.mj-item').forEach(function (it) {
        var okF = f === 'todos'
          || (f === 'leidos'    && it.dataset.leido === '1')
          || (f === 'no_leidos' && it.dataset.leido !== '1');
        var okQ = !q || (it.dataset.buscar || '').indexOf(q) !== -1;
        var ver = okF && okQ;
        it.hidden = !ver;
        if (ver) n++;
      });

      // Separadores de fecha: se ocultan si su grupo quedó vacío
      lista.querySelectorAll('.mj-sep').forEach(function (sep) {
        var hay = false, s = sep.nextElementSibling;
        while (s && !s.classList.contains('mj-sep')) { if (!s.hidden) { hay = true; break; } s = s.nextElementSibling; }
        sep.hidden = !hay;
      });

      var vacio = raiz.querySelector('[data-rol="vacio"]');
      var sinRes = raiz.querySelector('[data-rol="sin-resultados"]');
      var total = lista.querySelectorAll('.mj-item').length;
      if (vacio)  vacio.hidden  = total !== 0;
      if (sinRes) sinRes.hidden = !(total > 0 && n === 0);
      contar(n);
    }

    /* ---------------------------------------------------
       SELECCIÓN MÚLTIPLE
       --------------------------------------------------- */
    function seleccionados() {
      return Array.prototype.slice.call(raiz.querySelectorAll('.mj-item')).filter(function (it) {
        var c = it.querySelector('.mj-item-check input');
        return c && c.checked;
      });
    }
    function refrescarSeleccion() {
      var barra = raiz.querySelector('.mj-barra-sel');
      if (!barra) return;
      var n = seleccionados().length;
      barra.hidden = n === 0;
      raiz.dataset.seleccion = n ? 'on' : '';
      var cnt = barra.querySelector('[data-rol="conteo-sel"]');
      if (cnt) cnt.textContent = n;
    }
    function limpiarSeleccion() {
      raiz.querySelectorAll('.mj-item-check input, [data-accion="marcar-todos"]').forEach(function (c) { c.checked = false; });
      refrescarSeleccion();
    }

    /* ---------------------------------------------------
       REDACCIÓN
       --------------------------------------------------- */
    function responder(tipo) {
      var msg = lector && lector.querySelector('.mj-msg');
      if (!msg) { abrirModal('redactar'); return; }
      var asunto = msg.dataset.asunto || '';

      // Se guarda a qué mensaje responde: así el correo lleva In-Reply-To y
      // la conversación no se parte en dos.
      var oculto = raiz.querySelector('[data-rol="form-redactar"] [name="responde_a"]');
      if (oculto) oculto.value = (tipo === 'reenviar') ? '' : (msg.dataset.idMensaje || '');

      abrirModal('redactar', {
        para: tipo === 'reenviar' ? '' : (msg.dataset.email || ''),
        cc: tipo === 'todos' ? (msg.dataset.todos || '') : '',
        asunto: (tipo === 'reenviar' ? 'Rv: ' : 'Re: ') + asunto,
        cuerpo: '\n\n———\n' + (tipo === 'reenviar' ? 'Mensaje reenviado de ' : 'El ') +
                (msg.dataset.nombre || '') + ' escribió:\n'
      });
    }

    /* ---------------------------------------------------------
       Agenda
       --------------------------------------------------------- */
    function quitarContacto(fila) {
      if (!fila) return;
      var quien = fila.dataset.nombre || fila.dataset.email;

      confirmar('Se quitará a ' + quien + ' de tus contactos.', function () {
        var datos = new FormData();
        datos.append('accion', 'borrar');
        datos.append('email', fila.dataset.email);
        datos.append('token', tokenSesion());

        var padre = fila.parentNode, sig = fila.nextSibling;
        fila.remove();
        contarContactos();

        fetch('contactos.php', { method: 'POST', body: datos, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (r) {
            if (r && r.ok) { aviso('Contacto quitado'); }
            else {
              padre.insertBefore(fila, sig);   // no se pudo: vuelve a su sitio
              contarContactos();
              aviso((r && r.mensaje) || 'No se pudo quitar el contacto.');
            }
          })
          .catch(function () {
            padre.insertBefore(fila, sig);
            contarContactos();
            aviso('No se pudo quitar el contacto.');
          });
      }, 'Quitar de la agenda', 'Quitar');
    }

    /* ---------------------------------------------------------
       Ajustes de la cuenta
       --------------------------------------------------------- */
    function enviarAjuste(form, que, alTerminar) {
      var boton = form.querySelector('[type="submit"]');
      var antes = boton ? boton.textContent : '';
      if (boton) { boton.disabled = true; boton.textContent = 'Guardando…'; }

      var datos = new FormData(form);
      datos.append('que', que);
      datos.append('token', form.dataset.token || '');

      fetch('ajustes.php', { method: 'POST', body: datos, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (boton) { boton.disabled = false; boton.textContent = antes; }
          aviso((r && r.mensaje) || 'No se pudo guardar.');
          if (r && r.ok && alTerminar) alTerminar(r);
        })
        .catch(function () {
          if (boton) { boton.disabled = false; boton.textContent = antes; }
          aviso('No se pudo guardar. Revisa la conexión.');
        });
    }

    var formPerfil = raiz.querySelector('[data-rol="form-perfil"]');
    if (formPerfil) {
      formPerfil.addEventListener('submit', function (ev) {
        ev.preventDefault();
        enviarAjuste(formPerfil, 'perfil', function (r) {
          // El nombre sale en la vista previa y en el pie del menú
          var previo = raiz.querySelector('[data-rol="previo-nombre"]');
          if (previo && r.nombre) previo.textContent = r.nombre;
          var pie = raiz.querySelector('.mj-usuario-txt strong');
          if (pie && r.nombre) pie.textContent = r.nombre;
        });
      });

      // La vista previa sigue lo que se escribe, sin esperar a guardar
      var campoNombre = formPerfil.elements.nombre;
      if (campoNombre) campoNombre.addEventListener('input', function () {
        var previo = raiz.querySelector('[data-rol="previo-nombre"]');
        if (previo) previo.textContent = campoNombre.value.trim() || '—';
      });
    }

    /* Agregar otra casilla sin salir de la ventana. El enlace sigue
       llevando a la pantalla de acceso si esto no llega a cargarse. */
    raiz.querySelectorAll('[data-accion="nueva-casilla"]').forEach(function (a) {
      a.addEventListener('click', function (ev) {
        var m = raiz.querySelector('[data-modal="casilla"]');
        if (!m) return;                       // sin cuadro, que siga el enlace
        ev.preventDefault();

        var caja = raiz.querySelector('[data-rol="cuentas"]');
        if (caja) caja.hidden = true;         // el desplegable estorba

        m.querySelector('[data-rol="form-casilla"]').reset();
        m.hidden = false;
        m.querySelector('[name="correo"]').focus();
      });
    });

    var formCasilla = raiz.querySelector('[data-rol="form-casilla"]');
    if (formCasilla) formCasilla.addEventListener('submit', function (ev) {
      ev.preventDefault();

      var boton = formCasilla.querySelector('[type="submit"]');
      var antes = boton.textContent;
      boton.disabled = true; boton.textContent = 'Comprobando…';

      var datos = new FormData(formCasilla);
      datos.append('que', 'agregar');
      datos.append('token', formCasilla.dataset.token || '');

      fetch('ajustes.php', { method: 'POST', body: datos, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          boton.disabled = false; boton.textContent = antes;
          if (!r || !r.ok) { aviso((r && r.mensaje) || 'No se pudo agregar la casilla.'); return; }

          var lista = raiz.querySelector('[data-rol="lista-casillas"]');
          if (lista && r.filas) { lista.innerHTML = r.filas; }
          cerrarModal();
          aviso(r.mensaje);
          if (!lista) { location.reload(); }   // se agregó desde otra carpeta
        })
        .catch(function () {
          boton.disabled = false; boton.textContent = antes;
          aviso('No se pudo agregar la casilla. Revisa la conexión.');
        });
    });

    var formClave = raiz.querySelector('[data-rol="form-clave"]');
    if (formClave) formClave.addEventListener('submit', function (ev) {
      ev.preventDefault();
      enviarAjuste(formClave, 'clave', function () { formClave.reset(); });
    });

    /* ---------------------------------------------------------
       Sugerencias al escribir un destinatario
       --------------------------------------------------------- */
    function sugerencias(campo) {
      if (!campo || !AGENDA.length) return;

      var caja = document.createElement('ul');
      caja.className = 'mj-sugerencias';
      caja.hidden = true;
      campo.parentNode.appendChild(caja);
      campo.setAttribute('autocomplete', 'off');

      var elegido = -1;

      function cerrar() { caja.hidden = true; caja.innerHTML = ''; elegido = -1; }

      function pintar() {
        var q = campo.value.trim().toLowerCase();
        if (q.length < 1) { cerrar(); return; }

        var hallados = AGENDA.filter(function (c) {
          return (c.e + ' ' + (c.n || '')).toLowerCase().indexOf(q) !== -1;
        }).slice(0, 6);

        // Si ya está escrita la dirección entera, no hay nada que sugerir:
        // pasa al responder, que llega con el destinatario puesto.
        if (hallados.length === 1 && hallados[0].e.toLowerCase() === q) { cerrar(); return; }
        if (!hallados.length) { cerrar(); return; }

        caja.innerHTML = '';
        hallados.forEach(function (c, i) {
          var li = document.createElement('li');
          li.className = 'mj-sugerencia';
          li.dataset.email = c.e;
          li.setAttribute('role', 'option');
          li.innerHTML = '<strong></strong><span></span>';
          li.firstChild.textContent = c.n || c.e;
          li.lastChild.textContent = c.e;
          li.addEventListener('mousedown', function (ev) {
            ev.preventDefault();          // no perder el foco antes de tiempo
            campo.value = c.e; cerrar();
          });
          li.addEventListener('mouseenter', function () { marcar(i); });
          caja.appendChild(li);
        });

        elegido = -1;
        caja.hidden = false;
      }

      function marcar(i) {
        var hijos = caja.children;
        elegido = i;
        for (var j = 0; j < hijos.length; j++) {
          hijos[j].classList.toggle('is-activo', j === i);
        }
      }

      campo.addEventListener('input', pintar);
      campo.addEventListener('focus', pintar);
      campo.addEventListener('blur', function () { setTimeout(cerrar, 120); });

      campo.addEventListener('keydown', function (ev) {
        if (caja.hidden) return;
        var n = caja.children.length;

        if (ev.key === 'ArrowDown')      { ev.preventDefault(); marcar((elegido + 1) % n); }
        else if (ev.key === 'ArrowUp')   { ev.preventDefault(); marcar((elegido - 1 + n) % n); }
        else if (ev.key === 'Escape')    { cerrar(); }
        else if (ev.key === 'Enter' || ev.key === 'Tab') {
          if (elegido >= 0) {
            ev.preventDefault();
            campo.value = caja.children[elegido].dataset.email;
            cerrar();
          }
        }
      });
    }

    raiz.querySelectorAll('[data-rol="form-redactar"] [name="para"], ' +
                          '[data-rol="form-redactar"] [name="cc"]')
        .forEach(sugerencias);

    /* Abre la ficha vacía (alta) o con lo que ya tenga la fila (edición) */
    function fichaContacto(fila) {
      var m = raiz.querySelector('[data-modal="contacto"]');
      if (!m) return;

      var f = m.querySelector('[data-rol="form-contacto"]');
      m.querySelector('[data-rol="ficha-titulo"]').textContent =
        fila ? 'Editar contacto' : 'Nuevo contacto';

      f.elements.original.value = fila ? fila.dataset.email : '';
      f.elements.nombre.value   = fila ? (fila.dataset.nombre   || '') : '';
      f.elements.email.value    = fila ? (fila.dataset.email    || '') : '';
      f.elements.telefono.value = fila ? (fila.dataset.telefono || '') : '';
      f.elements.nota.value     = fila ? (fila.dataset.nota     || '') : '';

      m.hidden = false;
      f.elements.nombre.focus();
    }

    var formCt = raiz.querySelector('[data-rol="form-contacto"]');
    if (formCt) formCt.addEventListener('submit', function (ev) {
      ev.preventDefault();

      var boton = formCt.querySelector('[type="submit"]');
      var antes = boton.textContent;
      boton.disabled = true; boton.textContent = 'Guardando…';

      var datos = new FormData(formCt);
      datos.append('accion', 'guardar');
      datos.append('token', tokenSesion());

      fetch('contactos.php', { method: 'POST', body: datos, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          boton.disabled = false; boton.textContent = antes;
          if (!r || !r.ok) { aviso((r && r.mensaje) || 'No se pudo guardar el contacto.'); return; }

          ponerFila(r.fila, formCt.elements.original.value || r.email);
          cerrarModal();
          aviso(r.mensaje);
        })
        .catch(function () {
          boton.disabled = false; boton.textContent = antes;
          aviso('No se pudo guardar el contacto.');
        });
    });

    /* Mete la fila que dibujó el servidor: reemplaza la que se editó, o la
       pone arriba del todo si es nueva. */
    function ponerFila(html, emailAnterior) {
      var lista = raiz.querySelector('[data-rol="lista-contactos"]');
      if (!lista || !html) return;

      // <template> es lo único que parsea un <li> suelto sin descartarlo
      var caja = document.createElement('template');
      caja.innerHTML = html.trim();
      var nueva = caja.content.querySelector('.mj-contacto');
      if (!nueva) return;

      var vieja = emailAnterior
        ? lista.querySelector('.mj-contacto[data-email="' + emailAnterior.replace(/"/g, '\\"') + '"]')
        : null;

      if (vieja) { vieja.replaceWith(nueva); }
      else { lista.insertBefore(nueva, lista.firstChild); }

      contarContactos();
    }

    function tokenSesion() {
      var f = raiz.querySelector('[data-rol="form-redactar"]');
      return f ? (f.dataset.token || '') : '';
    }

    /* Deja el recuento de la cabecera y el del menú al día */
    function contarContactos() {
      var n = raiz.querySelectorAll('.mj-contacto').length;
      var nota = raiz.querySelector('[data-rol="cuantos-contactos"]');
      if (nota) nota.textContent = (n === 1 ? '1 persona' : n + ' personas');

      // Con la agenda vacía sobra el buscador y toca enseñar el cartel
      var vacio = raiz.querySelector('[data-rol="sin-contactos"]');
      if (vacio) vacio.hidden = n > 0;
      var buscador = raiz.querySelector('.mj-agenda-buscar');
      if (buscador) buscador.hidden = n === 0;

      var enlace = raiz.querySelector('.mj-nav-item[data-carpeta="contactos"]');
      if (!enlace) return;
      var badge = enlace.querySelector('.mj-badge');
      if (n === 0) { if (badge) badge.remove(); return; }
      if (!badge) {
        badge = document.createElement('em');
        badge.className = 'mj-badge';
        enlace.appendChild(badge);
      }
      badge.textContent = n;
    }

    var buscaCt = raiz.querySelector('[data-rol="buscar-contacto"]');
    if (buscaCt) buscaCt.addEventListener('input', function () {
      var q = buscaCt.value.trim().toLowerCase();
      var visibles = 0;

      raiz.querySelectorAll('.mj-contacto').forEach(function (c) {
        var pasa = !q || (c.dataset.buscar || '').indexOf(q) !== -1;
        c.hidden = !pasa;
        if (pasa) visibles++;
      });

      var nada = raiz.querySelector('[data-rol="sin-coincidencias"]');
      if (nada) nada.hidden = visibles > 0 || q === '';
    });

    document.addEventListener('click', function (ev) {
      var caja = raiz.querySelector('[data-rol="cuentas"]');
      if (!caja || caja.hidden) return;
      if (caja.contains(ev.target) || ev.target.closest('[data-accion="cuentas"]')) return;
      caja.hidden = true;
      var b = raiz.querySelector('[data-accion="cuentas"]');
      if (b) b.setAttribute('aria-expanded', 'false');
    });

    function abrirModal(nombre, datos) {
      var m = raiz.querySelector('[data-modal="' + nombre + '"]');
      if (!m) return;
      m.hidden = false;
      if (datos) {
        Object.keys(datos).forEach(function (k) {
          var campo = m.querySelector('[name="' + k + '"]');
          if (campo) campo.value = datos[k];
        });
      }
      var foco = m.querySelector('input, textarea, button');
      if (foco) foco.focus();
    }
    function cerrarModal() {
      raiz.querySelectorAll('.mj-modal').forEach(function (m) { m.hidden = true; });
    }

    /* Ventana de confirmación propia: el confirm() del navegador desentona
       y en el móvil algunos lo bloquean. */
    function confirmar(texto, alAceptar, titulo, etiqueta) {
      var m = raiz.querySelector('[data-modal="confirmar"]');
      if (!m) { if (window.confirm(texto)) alAceptar(); return; }

      m.querySelector('[data-rol="confirmar-titulo"]').textContent = titulo || 'Eliminar definitivamente';
      m.querySelector('[data-rol="confirmar-texto"]').textContent = texto;
      var si = m.querySelector('[data-rol="confirmar-si"]');
      var nuevo = si.cloneNode(true);        // se limpia el oyente anterior
      nuevo.textContent = etiqueta || 'Eliminar';
      si.parentNode.replaceChild(nuevo, si);
      nuevo.addEventListener('click', function () { cerrarModal(); alAceptar(); });

      m.hidden = false;
      nuevo.focus();
    }

    var form = raiz.querySelector('[data-rol="form-redactar"]');
    if (form) form.addEventListener('submit', function (ev) {
      ev.preventDefault();

      var boton = form.querySelector('[type="submit"]');
      var textoBoton = boton ? boton.textContent : '';
      if (boton) { boton.disabled = true; boton.textContent = 'Enviando…'; }

      var datos = new FormData(form);
      datos.append('token', form.dataset.token || '');

      fetch('enviar.php', { method: 'POST', body: datos, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (r.ok) {
            cerrarModal();
            form.reset();
          }
          aviso(r.mensaje);
        })
        .catch(function () {
          aviso('No se pudo enviar: revisa la conexión.');
        })
        .finally(function () {
          if (boton) { boton.disabled = false; boton.textContent = textoBoton; }
        });
    });

    /* ---------------------------------------------------
       ATAJOS DE TECLADO
       --------------------------------------------------- */
    function atajos(ev) {
      var et = ev.target;
      if (et.matches('input, textarea, select, [contenteditable]')) {
        if (ev.key === 'Escape') et.blur();
        return;
      }
      var k = ev.key;

      if (k === 'Escape') {
        if (menu && !menu.hidden) return cerrarMenu();
        if (raiz.querySelector('.mj-modal:not([hidden])')) return cerrarModal();
        if (raiz.dataset.carpetas === 'abierto') { raiz.dataset.carpetas = ''; return; }
        if (raiz.dataset.vista === 'lector' && innerWidth <= 900) { raiz.dataset.vista = 'lista'; quitarParam('m'); }
        return;
      }
      if (k === '/' ) { ev.preventDefault(); if (buscar) buscar.focus(); return; }
      if (k === '?' ) { abrirModal('atajos'); return; }

      var vis = visibles();
      var act = itemActivo();
      var i   = vis.indexOf(act);

      if (k === 'ArrowDown' || k === 'j') { ev.preventDefault(); if (vis[i + 1]) abrir(vis[i + 1], true); else if (i < 0 && vis[0]) abrir(vis[0], true); return; }
      if (k === 'ArrowUp'   || k === 'k') { ev.preventDefault(); if (vis[i - 1]) abrir(vis[i - 1], true); return; }
      if (k === 'Enter' && act) { abrir(act, true); return; }

      if (!act) return;
      switch (k.toLowerCase()) {
        case 'r': ev.preventDefault(); responder('uno');   break;
        case 'a': ev.preventDefault(); responder('todos'); break;
        case 'f': ev.preventDefault(); responder('reenviar'); break;
        case 'e': quitar(act, 'archivado', 'archivo'); break;
        case 's': destacar(act); break;
        case 'u': marcarLeido(act, act.dataset.leido !== '1'); break;
        case 'c': ev.preventDefault(); abrirModal('redactar'); break;
      }
      if (k === 'Delete' || k === 'Backspace') { ev.preventDefault(); quitar(act, 'eliminado'); }
    }

    /* ---------------------------------------------------
       AVISOS FLOTANTES
       --------------------------------------------------- */
    function aviso(texto, textoBoton, alPulsar) {
      if (!OP.avisos || !avisos) return;
      var el = document.createElement('div');
      el.className = 'mj-aviso';
      el.appendChild(document.createTextNode(texto));
      if (textoBoton) {
        var b = document.createElement('button');
        b.type = 'button'; b.textContent = textoBoton;
        b.addEventListener('click', function () { alPulsar(); cerrar(); });
        el.appendChild(b);
      }
      avisos.appendChild(el);
      var t = setTimeout(cerrar, textoBoton ? 6000 : 3200);
      function cerrar() {
        clearTimeout(t);
        el.classList.add('is-va');
        setTimeout(function () { el.remove(); }, 260);
      }
    }

    /* ---------------------------------------------------
       AUXILIARES
       --------------------------------------------------- */
    function visibles() {
      return Array.prototype.slice.call(raiz.querySelectorAll('.mj-item')).filter(function (i) { return !i.hidden; });
    }
    function contar(n) {
      var pie = raiz.querySelector('.mj-lista-pie span');
      if (!pie) return;
      var t = typeof n === 'number' ? n : visibles().length;
      pie.textContent = t + ' mensaje' + (t === 1 ? '' : 's');
    }
    function badge(delta) {
      var b = raiz.querySelector('.mj-nav-item.is-activo .mj-badge');
      if (!b) return;
      var v = Math.max(0, (parseInt(b.textContent, 10) || 0) + delta);
      b.textContent = v;
      b.hidden = v === 0;
    }
    function vaciarLector() {
      if (!lector) return;
      lector.innerHTML = '<div class="mj-vacio mj-vacio-lector"><p class="mj-vacio-t">' +
        (T.sin_seleccion || '') + '</p><p class="mj-vacio-d">' + (T.sin_seleccion_desc || '') + '</p></div>';
      raiz.dataset.vista = 'lista';
      quitarParam('m');
    }
    function vaciarSiFalta() {
      if (!visibles().length) vaciarLector();
      var vacio = raiz.querySelector('[data-rol="vacio"]');
      if (vacio) vacio.hidden = lista.querySelectorAll('.mj-item').length !== 0;
    }
    function quitarParam(p) {
      var u = new URL(location.href); u.searchParams.delete(p);
      history.replaceState(history.state, '', u);
    }
    function css(v) { return String(v).replace(/"/g, '\\"'); }
    function leerDatos(r) {
      var s = r.querySelector('script.mj-datos');
      try { return s ? JSON.parse(s.textContent) : {}; } catch (e) { return {}; }
    }
    function almacen(clave, valor) {
      try {
        if (valor === undefined) return localStorage.getItem(clave);
        localStorage.setItem(clave, valor);
      } catch (e) { return null; }
    }

    // Estado inicial coherente con la URL
    filtrar();
  }
})();
