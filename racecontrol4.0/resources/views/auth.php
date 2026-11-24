<?php
/** Plantilla para pantallas públicas (login, recuperación): ventana gótica centrada. */
require_once __DIR__ . '/partials/helpers.php';
$base = $p->base;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · RaceControl</title>
  <meta name="robots" content="noindex">
  <script src="<?= e($base) ?>/assets/tema.js"></script>
  <link rel="stylesheet" href="<?= e($base) ?>/assets/app.css">
  <link rel="stylesheet" href="<?= e($base) ?>/assets/auth-extra.css">
  <script type="application/json" id="rc-datos"><?= $js ?></script>
  <script type="module" src="<?= e($base) ?>/assets/app.js"></script>
</head>
<body class="rc-auth" data-pagina="<?= e($vista) ?>">
<?php require __DIR__ . '/partials/iconos.php'; ?>
<div class="rc-niebla" aria-hidden="true"></div>
<div class="rc-barra-auth">
  <button type="button" class="rc-chip" id="rc-info-btn" aria-haspopup="dialog" aria-controls="rc-info">ⓘ Información</button>
  <button type="button" class="rc-chip" id="rc-tema-btn" aria-label="Cambiar al modo claro">
    <span class="rc-tema-icono" aria-hidden="true">☀</span>
    <span class="rc-tema-texto">Modo claro</span>
  </button>
</div>
<div class="rc-info-fondo" id="rc-info" role="dialog" aria-modal="true" aria-labelledby="rc-info-t" hidden>
  <div class="rc-info-caja">
    <h2 id="rc-info-t">Acerca de RaceControl</h2>
    <p>Sistema de gestión de competencias de automovilismo y motociclismo: pilotos, escuderías, vehículos, circuitos, campeonatos, inscripciones, resultados y reportes.</p>
    <ul>
      <li><strong>Registro:</strong> las cuentas nuevas se crean con rol <em>lector</em>; un administrador puede cambiar el rol.</li>
      <li><strong>Seguridad:</strong> las contraseñas se almacenan cifradas (hash), nunca en texto plano. Tras 5 intentos fallidos la cuenta se bloquea 15 minutos.</li>
      <li><strong>Contraseña:</strong> mínimo 12 caracteres con mayúscula, minúscula, número y símbolo.</li>
      <li><strong>Roles:</strong> admin, editor y lector.</li>
    </ul>
    <button type="button" class="btn rc-btn-primario" id="rc-info-cerrar">Cerrar</button>
  </div>
</div>
<main class="rc-auth-caja" id="contenido" tabindex="-1">
  <div class="rc-vitral rc-auth-tarjeta">
    <h1 class="rc-logo rc-logo-grande"><span class="visually-hidden">RaceControl, </span>RaceControl</h1>
    <p class="rc-lema">Pista nocturna en la catedral</p>
    <?php require __DIR__ . '/partials/filigrana.php'; ?>
    <?php require __DIR__ . "/$vista.php"; ?>
  </div>
</main>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="rc-toasts" aria-live="polite" aria-atomic="false"></div>
<script src="<?= e($base) ?>/assets/auth-extra.js"></script>
</body>
</html>
