# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/).
Este proyecto usa [versionado semántico](https://semver.org/lang/es/):
`MAYOR.MENOR.PARCHE`.

- **MAYOR** — cambios que obligan a revisar la configuración de los sitios ya instalados
- **MENOR** — funciones nuevas compatibles con lo anterior
- **PARCHE** — correcciones

---

## [1.11.0] — 2026-09-01

### Añadido
- **Cambiar la contraseña de la casilla**, en "Tu cuenta". Por IMAP no se
  puede —ese protocolo lee y escribe mensajes, no administra cuentas—, así
  que se le pide al panel del hosting por su API. Hay que conectar cPanel una
  vez en `instalar.php` → "Panel del hosting", con un token de API.
  Antes de cambiarla se comprueba la de ahora contra el servidor, y después
  la sesión, el vale de "mantener la sesión abierta" y la casilla guardada se
  actualizan solos para no quedarse fuera.
- **Dos casillas, y cambiar de una a otra con un clic.** Desde tu nombre, al
  pie del menú: "Agregar otra casilla" pide su dirección y contraseña sin
  cerrar la que ya está abierta, y desde ahí se salta entre ellas. Cada una
  conserva su nombre, su firma y su agenda.
- Las casillas guardadas se pueden quitar de este equipo una a una.

### Seguridad
- Las contraseñas guardadas se cifran con AES-256-GCM y la llave del
  servidor, la misma de "mantener la sesión abierta"; el archivo vive fuera
  de la web y sólo se abre con el token del navegador que las guardó.
- Cambiar de casilla y quitar una exigen el token de la sesión, para que no
  baste con hacer clic en un enlace ajeno.
- El token de cPanel manda sobre TODAS las casillas del panel. Se guarda en
  `config.local.php`, que no se sube al repositorio; si se filtra, hay que
  revocarlo desde cPanel.

## [1.10.1] — 2026-09-01

### Añadido
- **Al redactar, el correo se completa solo.** En "Para" y en "CC", según se
  escribe aparecen los contactos de la agenda que coinciden —nombre y
  dirección— y se eligen con el ratón o con las flechas y Enter. Si la
  dirección ya está entera, como al responder, no molesta con sugerencias.

## [1.10.0] — 2026-09-01

### Añadido
- **Contactos a mano.** Botón "Agregar" en la agenda y lápiz en cada ficha:
  se pueden anotar, corregir y borrar contactos, no sólo esperar a que los
  deje un envío. Cada ficha admite nombre, correo, teléfono y una nota
  (causa, empresa, lo que sirva), y el teléfono y la nota se ven bajo la
  dirección.
- Al corregir a alguien no se pierde lo que ya llevaba: si cambia el correo,
  los envíos contados viajan con él. Dos fichas con la misma dirección no se
  admiten.

### Corregido
- **La agenda vacía se veía descolocada:** la cabecera iba pegada al borde
  izquierdo y el cartel de "todavía no hay contactos" se centraba en todo el
  panel, así que no parecían la misma sección. Ahora comparten una sola
  columna centrada, y el cartel trae su propio botón para agregar.
- Con la agenda vacía se escondía el buscador, que no tenía nada que buscar.

## [1.9.0] — 2026-09-01

### Añadido
- **Contactos.** Nueva sección en el menú, entre Enviados y Borradores. La
  agenda se llena sola: cada vez que se envía un mensaje, el destinatario y
  quien vaya en copia quedan guardados, con el nombre y cuántas veces se les
  ha escrito. Se ordena por lo más reciente.
- Buscador en vivo dentro de la agenda, botón para escribir a un contacto (abre
  el compositor con la dirección puesta) y botón para quitarlo, con
  confirmación.
- La agenda es de cada casilla, no del servidor: se guarda en
  `data/contactos/`, con su `.htaccess` para que no se pueda pedir por la web.

### Seguridad
- `data/contactos/`, `data/cuentas/` y `data/sesiones/` pasan al `.gitignore`.
  Guardan datos personales y claves cifradas, y hasta ahora sólo dependía de
  que nadie hiciera `git add` de más.

## [1.8.3] — 2026-09-01

### Corregido
- **La ventana de confirmación decía "el mensaje de este mensaje"** y siempre
  hablaba en singular: las filas de la lista no llevaban el nombre de quien
  escribe ni cuántos mensajes tiene la conversación, así que no había con qué
  redactar la frase. Ahora sí, y si no hay nombre la frase se acorta en vez de
  quedar coja.
- **La ventana no era responsive.** Reutilizaba la caja de los formularios,
  cuyo pie no tiene margen lateral, y en el móvil los botones se salían por el
  borde derecho. Ahora es una pieza propia: tarjeta centrada en el escritorio,
  hoja que sube desde abajo en el móvil —con sitio para la barra del sistema— y
  botones a lo ancho, el de eliminar arriba.
- La confirmación se veía sosa: ahora lleva el icono de la papelera en un
  círculo rojo, el texto centrado y "Cancelar" en gris en lugar de blanco.

## [1.8.2] — 2026-09-01

### Corregido
- **Al eliminar, el mensaje seguía en Recibidos.** La lista agrupa
  conversaciones, pero a la papelera sólo viajaba el mensaje visible; los
  demás se quedaban y la fila reaparecía en cuanto se recargaba. Ahora se
  mueve la conversación entera.
- **Los hermanos de la conversación se pedían con la conexión ya abierta**,
  lo que obligaba a abrir una segunda anidada contra el mismo servidor. Se
  resuelven antes de conectar.

### Añadido
- **Eliminar permanentemente.** Dentro de la papelera, el menú del botón
  derecho ya no ofrece "Eliminar" (no hay dónde caer): ofrece "Eliminar
  permanentemente", en rojo, y pide confirmación en una ventana propia que
  dice cuántos mensajes se van y avisa de que no hay vuelta atrás. Por
  debajo hace `UID STORE \Deleted` + `UID EXPUNGE`.
- "Archivar" se oculta en la papelera, donde no tiene sentido.

## [1.8.1] — 2026-09-01

### Corregido
- **"Mantener la sesión abierta" no mantenía nada.** La cookie se decidía
  antes de saber si la persona había marcado la casilla, y aunque hubiera
  durado, PHP borra los datos de sesión a los 24 minutos. Ahora se guarda un
  vale en el servidor con la casilla y su clave cifrada (AES-256-GCM), y el
  navegador conserva sólo el token que lo abre, 30 días.
- El vale se renueva en cada uso, se borra al cerrar sesión, caduca solo y se
  limpia del disco cuando vence.

## [1.8.0] — 2026-09-01

### Cambiado
- **Pantalla de acceso nueva**, en blanco y negro y a dos columnas: la marca y
  su descripción a un lado, el formulario al otro. Se adapta a móvil apilando
  las dos partes. El texto y el contacto de soporte se configuran en
  `acceso.lema` y `acceso.soporte`.

### Agregado
- Botón para ver la contraseña mientras se escribe.
- **Mantener la sesión abierta**: si se activa, la sesión dura 30 días; si no,
  se cierra al cerrar el navegador.

## [1.7.3] — 2026-09-01

### Corregido
- **Al abrir un correo con un clic no aparecían los mensajes anteriores de la
  conversación; sólo al recargar.** El lector que dibuja el servidor recibía
  la conversación, pero las plantillas que intercambia el JavaScript —que son
  las que se usan al pinchar en la lista— se generaban sin ella.

## [1.7.2] — 2026-09-01

### Corregido
- **Abrir una conversación marca ahora todos sus mensajes.** La lista agrupa
  por hilo y muestra el más reciente, pero al abrirlo sólo se marcaba ése:
  sus hermanos seguían sin leer en el servidor y el contador —que cuenta
  mensajes, no conversaciones— no bajaba nunca. Se marcan todos, en una sola
  conexión y agrupados por carpeta.
- Al agrupar se perdía el estado de lectura de los hermanos si el más
  reciente reemplazaba al representante: una conversación con mensajes sin
  leer podía verse leída.

## [1.7.1] — 2026-09-01

### Corregido
- **El marcado como leído podía fallar sin que nadie se enterara.** Un
  `UID STORE` sobre un mensaje que no está en la carpeta abierta —o con el
  buzón en sólo lectura— también responde `OK` y no hace nada. Ahora se
  releen las banderas después de marcar y, si no quedaron guardadas, se dice.
- Se detecta cuando el servidor abre la carpeta en **sólo lectura**, donde
  ninguna marca se guardaría.

### Agregado
- `diagnostico.php`: recorre la cadena completa contra tu propio servidor
  —carpetas, UID y banderas de cada mensaje, prueba de marcado y verificación
  en otra sesión— y muestra el diálogo IMAP en crudo.

## [1.7.0] — 2026-09-01

### Agregado
- **Las acciones del menú ahora sí llegan al servidor**: marcar leído y no
  leído, destacar, archivar, eliminar y mover a otra carpeta. Antes sólo
  cambiaban la pantalla y al recargar volvía todo atrás.
- Al abrir un correo se marca leído aunque no se recargue la página, que es
  lo que pasa siempre: la bandeja abre los mensajes sin ir al servidor. Por
  eso el contador de no leídos no bajaba nunca.

### Cambiado
- Copiar a otra carpeta y silenciar dicen que no están disponibles, en vez de
  fingir que hicieron algo.

### Corregido
- El submenú de "Mover a" se cortaba cuando el menú se abría abajo: ahora se
  ancla por su borde inferior y, si aun así no cabe, se desplaza por dentro.

## [1.6.3] — 2026-09-01

### Corregido
- En la ficha de la cuenta, los botones se montaban sobre el nombre: el
  nombre no se recortaba y empujaba a los iconos. Ahora se corta con puntos
  suspensivos y los botones no se encogen.

## [1.6.2] — 2026-09-01

### Corregido
- **Los correos abiertos seguían contando como no leídos.** Nunca se marcaba
  `\Seen` en el servidor: el punto verde y el número del menú no bajaban
  nunca, y en el celular seguían apareciendo sin leer. Ahora se marca al
  abrirlos, y el contador baja en el acto.

## [1.6.1] — 2026-09-01

### Cambiado
- **Al entrar ya no se abre solo el primer correo**: el lector espera a que
  elijas uno. Quien prefiera lo anterior, `interfaz.abrir_primero => true`.

### Corregido
- Con la lista agrupada, abrir un mensaje que no fuera el más reciente de su
  conversación no mostraba nada: se buscaba sólo entre las filas visibles.

## [1.6.0] — 2026-09-01

### Agregado
- **La lista agrupa por conversación**: una fila por hilo, con el mensaje más
  reciente y un contador de cuántos lleva. Si alguno está sin leer, la
  conversación entera se ve sin leer. Se puede apagar con
  `interfaz.agrupar_conversaciones`.
- Dos mensajes con el mismo asunto se consideran la misma conversación aunque
  sus cadenas de referencias no coincidan, cosa que pasa cuando alguien del
  medio respondió desde otro programa.

## [1.5.1] — 2026-09-01

### Corregido
- **Lo enviado no se agrupaba con su respuesta.** La copia que se guarda en
  Enviados no llevaba `Message-ID`, así que quedaba en una conversación
  distinta a la respuesta que la citaba. Ahora el envío y su copia comparten
  el mismo identificador.
- Al responder desde la bandeja, el correo sale con `In-Reply-To` y
  `References`: quien lo reciba lo verá dentro del hilo, no suelto.

## [1.5.0] — 2026-09-01

### Agregado
- **Conversaciones.** Los mensajes se agrupan por hilo usando las cabeceras
  `Message-ID`, `In-Reply-To` y `References` (y el asunto sin "Re:" cuando el
  correo no las trae). Al abrir una respuesta se ven arriba los mensajes
  anteriores, plegados, como en Gmail.
- **El texto citado se pliega** detrás de un "···": lo que se escribió ahora
  queda a la vista y el "El … escribió:" con sus ">" se esconde. Reconoce
  también los reenvíos.

## [1.4.1] — 2026-09-01

### Corregido
- **La "v" de las etiquetas rompía la comparación de versiones.** Con un
  release llamado `v1.4.0`, `version_compare` lo daba por más antiguo que la
  1.3.2 instalada y el panel decía "ya tienes la última". Ahora se normaliza.
- El buscador mira el último release **y** la rama, y ofrece el más nuevo de
  los dos: no hace falta publicar un release por cada corrección.
- El aviso decía "Ya tienes la última versión (1.3.2)" mostrando la instalada,
  sin decir qué encontró en GitHub. Ahora muestra ambas y de dónde salió.

## [1.4.0] — 2026-09-01

### Agregado
- **Ajustes de la cuenta** (`cuenta.php`): el nombre con el que sales en los
  correos y una firma, guardados por casilla. Se llega desde la rueda que hay
  junto a tu cuenta en la barra lateral.
- Los correos enviados se **guardan en la carpeta Enviados del servidor**, así
  quedan también en el celular y en cualquier otro cliente.

### Corregido
- **No se veía el cuerpo de los mensajes.** La lista traía sólo las cabeceras
  y el cuerpo se pedía aparte, pero el lector dibuja desde la lista.
- **Sólo se leía INBOX**, así que Enviados, Papelera y las demás carpetas se
  veían vacías. Ahora se recorren las que el servidor reconoce.
- Al leer el cuerpo se colaba el cierre de la respuesta IMAP: no se respetaba
  el tamaño del literal.
- El remitente salía como "BACANO.MAIL" en vez del nombre de la persona.

## [1.3.2] — 2026-09-01

### Corregido
- **Los estilos y el JavaScript quedaban cacheados para siempre.** Se pedían
  con un `?v=1.0` escrito a mano, que nunca cambiaba: después de actualizar,
  el navegador seguía ejecutando la versión vieja. Ahora la dirección lleva la
  versión instalada, así cada actualización refresca la caché sola.

## [1.3.1] — 2026-09-01

### Agregado
- Botón para cerrar sesión, junto a la cuenta en la barra lateral. Antes se
  entraba con el correo y no había manera de salir sin conocer `?salir=1`.

## [1.3.0] — 2026-09-01

### Agregado
- **Actualizar desde GitHub sin publicar releases.** Si el repositorio no
  tiene ninguno, se compara el archivo `VERSION` de la rama y se descarga el
  zip de esa rama. Con un token, funciona igual en repositorios privados.

### Corregido
- El token no llegaba a la descarga del paquete: `mj_aplicar_actualizacion`
  usaba una variable que nunca recibía, así que en repositorios privados la
  descarga fallaba en silencio.
- Tres botones sin implementar (crear carpeta, ajustes de carpetas y agendar)
  decían "Disponible al conectar la cuenta de correo", como si el problema
  fuera la conexión. Ahora dicen lo que realmente pasa.

## [1.2.1] — 2026-09-01

### Corregido
- Página en blanco al entrar a la bandeja: al reorganizar los archivos, la
  clase que lee por sockets quedó sin el `require` del cliente y PHP moría con
  un error fatal antes de dibujar nada.

## [1.2.0] — 2026-09-01

### Agregado
- **Se entra con el correo y su contraseña**, comprobados contra el servidor
  IMAP. La sesión usa esa casilla para leer y enviar, así que ya no hace falta
  dejar la clave guardada en el servidor. Queda disponible el modo de clave
  única con `acceso.modo => 'clave'`.
- **La bandeja pide clave.** Antes cualquiera con la dirección veía la
  casilla; ahora se entra con la misma clave del panel. Se puede desactivar
  con `acceso.proteger` sólo si se incrusta en una página que ya pide acceso.
- **Envío por SMTP** desde la ventana de redacción, con la cuenta configurada
  en el instalador. El endpoint exige sesión y token, para no quedar como un
  relay abierto.

## [1.1.1] — 2026-09-01

### Agregado
- Lectura por sockets como respaldo: si el hosting no tiene la extensión
  `imap` (desde PHP 8.4 ya no viene de serie), la casilla se lee igual.
  La fábrica elige sola según lo que haya en el servidor.
- El instalador prueba la conexión entrando de verdad a la casilla: informa
  cuántos mensajes hay, o en qué punto del diálogo falló.

### Cambiado
- La extensión `imap` pasa a ser opcional en la lista de requisitos.
- El buscador de actualizaciones dice qué pasó de verdad: si el servidor no
  llegó a GitHub, si el repositorio es privado, si el token no sirve o si
  simplemente no hay ningún release publicado.

### Agregado (actualizador)
- Campo de token en el instalador, para repositorios privados.

### Corregido
- Al usar "Probar la conexión" ya no se pierde lo escrito en el formulario:
  antes se repintaba con lo guardado y había que teclearlo todo de nuevo.
- La bandeja avisa cuando está mostrando mensajes de ejemplo, con el motivo.
- El menú lateral ya no muestra secciones que no llevan a ninguna parte:
  las que no tienen dirección quedan ocultas hasta que se configuren o se
  instale el módulo correspondiente.

## [1.1.0] — 2026-08-19

### Agregado
- Lectura real de la casilla por IMAP: `MjProveedorImap` conecta, lista los
  últimos mensajes, decodifica cabeceras MIME y cuerpos en base64 o
  quoted-printable, convierte a UTF-8 y detecta adjuntos.
- `mj_fallo_imap()` expone por qué no se pudo leer la casilla, para que el
  panel no muestre datos de ejemplo creyendo que son reales.

### Cambiado
- La fábrica de proveedores intenta la conexión antes de decidir: una casilla
  vacía ya no se confunde con un fallo.

### Pendiente
- Envío por SMTP desde la ventana de redacción.
- Probado contra una casilla real (requiere la extensión imap en el hosting).

## [1.0.0] — 2026-08-19

Primera versión publicable.

### Agregado
- Bandeja de entrada completa: carpetas, contadores, buscador, filtros,
  panel de lectura, menú contextual, destacados con color, selección
  múltiple, redacción, avisos con Deshacer y atajos de teclado.
- Cuatro temas (Aurora, Nieve, Tinta, Bosque), modo claro/oscuro automático
  y diseño responsive de escritorio a teléfono.
- Instalador web con comprobación de requisitos, panel de configuración
  protegido por clave y prueba de conexión al servidor IMAP.
- Ocho perfiles de rubro que arman menú, carpetas y etiquetas.
- Capa de datos con proveedores intercambiables (demo/JSON, IMAP preparado).
- Registro de módulos: la carpeta `modulos/` se descubre sola.
- Actualizaciones por `git pull` o desde el panel, con respaldo automático.

### Pendiente para 1.1
- Proveedor IMAP funcionando (lectura real de la casilla).
- Envío por SMTP desde la ventana de redacción.
