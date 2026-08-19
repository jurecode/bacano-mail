# Historial de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/).
Este proyecto usa [versionado semántico](https://semver.org/lang/es/):
`MAYOR.MENOR.PARCHE`.

- **MAYOR** — cambios que obligan a revisar la configuración de los sitios ya instalados
- **MENOR** — funciones nuevas compatibles con lo anterior
- **PARCHE** — correcciones

---

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
