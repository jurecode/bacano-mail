<?php
/* ============================================================
   MÓDULO DE CORREO — Punto de entrada para INCRUSTAR
   ------------------------------------------------------------
   Para usar el correo dentro de cualquier página tuya:

       <?php require __DIR__ . '/mails/correo.php'; ?>
       ...tu html...
       <?php mj_correo(); ?>

   También acepta opciones puntuales sin tocar config.php:

       mj_correo([
         'tema' => ['preset' => 'bosque', 'modo' => 'oscuro'],
         'interfaz' => ['mostrar_rail' => false],
       ]);
   ============================================================ */

require_once __DIR__ . '/inc/vista.php';
