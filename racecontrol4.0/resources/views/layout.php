<?php
/** Plantilla principal (usuario autenticado): header, sidebar colapsable, contenido y modales comunes. */
require_once __DIR__ . '/partials/helpers.php';
use RaceControl\Presentation\Pagina;
$menu = Pagina::menu($u);
$base = $p->base;
// Sección activa del menú: el recurso, 'campeonatos' en el detalle, o el nombre de la vista.
$seccion = $extra['recurso'] ?? ($vista === 'campeonato' ? 'campeonatos' : $vista);
if ($seccion === 'equipos') $seccion = 'escuderias';
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
  <script type="application/json" id="rc-datos"><?= $js ?></script>
  <script type="module" src="<?= e($base) ?>/assets/app.js"></script>
</head>
<body class="rc-app" data-pagina="<?= e($vista) ?>">
<?php require __DIR__ . '/partials/iconos.php'; ?>
<a class="rc-saltar" href="#contenido">Saltar al contenido</a>

<header class="rc-header">
  <button type="button" class="rc-btn-icono" id="rc-hamburguesa" aria-label="Abrir o fijar el menú lateral" aria-controls="rc-sidebar" aria-expanded="false">
    <?= icono('menu') ?>
  </button>
  <a class="rc-logo" href="<?= e($base) ?>/" aria-label="RaceControl, ir al panel principal">RaceControl</a>
  <span class="rc-header-espacio"></span>
  <button type="button" class="rc-chip rc-tema-btn" id="rc-tema-btn" aria-label="Cambiar al modo claro">
    <span class="rc-tema-icono" aria-hidden="true">☀</span>
    <span class="rc-tema-texto">Modo claro</span>
  </button>
  <button type="button" class="rc-btn-icono" data-bs-toggle="modal" data-bs-target="#rc-modal-panel" aria-label="Panel de control: preferencias y ajustes rápidos">
    <?= icono('engranaje') ?>
  </button>
  <div class="dropdown">
    <button class="rc-btn-usuario dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="rc-menu-usuario">
      <?= icono('usuario') ?><span class="rc-usuario-nombre"><?= e($u['nombre']) ?></span>
      <span class="rc-rol"><?= e(ucfirst($u['rol'])) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end rc-dropdown" aria-labelledby="rc-menu-usuario">
      <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#rc-modal-perfil"><?= icono('usuario') ?> Mi perfil</button></li>
      <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#rc-modal-clave"><?= icono('llave') ?> Cambiar contraseña</button></li>
      <li><hr class="dropdown-divider"></li>
      <li><button class="dropdown-item" type="button" id="rc-salir"><?= icono('salir') ?> Cerrar sesión</button></li>
    </ul>
  </div>
</header>

<div class="rc-sidebar-fondo" id="rc-sidebar-fondo" hidden></div>
<nav class="rc-sidebar" id="rc-sidebar" aria-label="Navegación principal">
  <?php foreach ($menu as $grupo => $items): ?>
    <div class="rc-nav-grupo">
      <p class="rc-nav-titulo"><?= e($grupo) ?></p>
      <ul>
        <?php foreach ($items as [$ruta, $ico, $texto, $mod]): ?>
          <li>
            <?php $activo = ($ruta === '/') ? $vista === 'dashboard' : ltrim($ruta, '/') === $seccion; ?>
            <a class="rc-nav-link<?= $activo ? ' activo' : '' ?>" href="<?= e($base . $ruta) ?>" title="<?= e($texto) ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
              <?= icono($ico) ?><span class="rc-nav-texto"><?= e($texto) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
  <div class="rc-sidebar-pie">
    <button type="button" class="rc-nav-link" id="rc-fijar" aria-pressed="false" title="Fijar el menú abierto"><?= icono('pin') ?><span class="rc-nav-texto">Fijar menú</span></button>
  </div>
</nav>

<main id="contenido" class="rc-main" tabindex="-1">
  <?php require __DIR__ . "/$vista.php"; ?>
</main>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="rc-toasts" aria-live="polite" aria-atomic="false"></div>

<!-- Modal reutilizable: formularios de catálogos -->
<div class="modal fade rc-modal" id="rc-modal-form" tabindex="-1" aria-labelledby="rc-modal-form-titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content rc-vitral">
    <div class="modal-header"><h2 class="modal-title" id="rc-modal-form-titulo"></h2>
      <button type="button" class="rc-btn-icono" data-bs-dismiss="modal" aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
    <form id="rc-form" novalidate>
      <div class="modal-body" id="rc-form-cuerpo"></div>
      <div class="modal-footer">
        <button type="button" class="btn rc-btn-secundario" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn rc-btn-primario" id="rc-form-guardar">Guardar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal de confirmación (acciones destructivas) -->
<div class="modal fade rc-modal" id="rc-modal-confirmar" tabindex="-1" aria-labelledby="rc-confirmar-titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content rc-vitral">
    <div class="modal-header"><h2 class="modal-title" id="rc-confirmar-titulo">Confirmar</h2>
      <button type="button" class="rc-btn-icono" data-bs-dismiss="modal" aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
    <div class="modal-body"><p id="rc-confirmar-mensaje"></p></div>
    <div class="modal-footer">
      <button type="button" class="btn rc-btn-secundario" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn rc-btn-peligro" id="rc-confirmar-ok">Eliminar</button>
    </div>
  </div></div>
</div>

<!-- Panel de control: preferencias de interfaz (se guardan en localStorage) -->
<div class="modal fade rc-modal" id="rc-modal-panel" tabindex="-1" aria-labelledby="rc-panel-titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content rc-vitral">
    <div class="modal-header"><h2 class="modal-title" id="rc-panel-titulo">Panel de control</h2>
      <button type="button" class="rc-btn-icono" data-bs-dismiss="modal" aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
    <div class="modal-body">
      <div class="mb-3">
        <label class="form-label" for="pref-filas">Filas por página en las tablas</label>
        <select class="form-select" id="pref-filas"><option>10</option><option>25</option><option>50</option></select>
      </div>
      <fieldset class="mb-3">
        <legend class="form-label mb-1">Paleta para daltonismo</legend>
        <?php foreach ([
          ['normal', 'Estándar'],
          ['protanopia', 'Protanopia'],
          ['deuteranopia', 'Deuteranopia'],
          ['tritanopia', 'Tritanopia'],
          ['acromatopsia', 'Acromatopsia'],
        ] as [$modo, $etiqueta]): ?>
          <div class="rc-paleta-opcion mb-2">
            <input class="form-check-input" type="radio" name="pref-daltonismo" id="pref-daltonismo-<?= e($modo) ?>" value="<?= e($modo) ?>">
            <label for="pref-daltonismo-<?= e($modo) ?>">
              <span><?= e($etiqueta) ?></span>
              <span class="rc-paleta-muestras" data-paleta="<?= e($modo) ?>" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span>
              </span>
            </label>
          </div>
        <?php endforeach; ?>
        <p class="form-text rc-texto-sec mb-0">Ajusta estados e indicadores; los textos y patrones también ayudan a distinguirlos.</p>
      </fieldset>
      <div class="rc-contraste-control form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="pref-alto-contraste">
        <label class="form-check-label" for="pref-alto-contraste">Modo de alto contraste</label>
        <span class="rc-paleta-muestras" data-paleta="alto-contraste" aria-hidden="true">
          <span></span><span></span><span></span><span></span><span></span>
        </span>
      </div>
      <p class="form-text rc-texto-sec mb-3">El alto contraste se puede combinar con cualquier paleta para daltonismo.</p>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="pref-sidebar">
        <label class="form-check-label" for="pref-sidebar">Mantener el menú lateral fijo abierto</label>
      </div>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="pref-animaciones">
        <label class="form-check-label" for="pref-animaciones">Animaciones de niebla y brillo</label>
      </div>
      <p class="rc-texto-sec mb-0">Estas preferencias se guardan solo en este navegador. Si tu sistema pide reducir el movimiento, las animaciones se desactivan siempre.</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn rc-btn-secundario" id="pref-restablecer">Restablecer</button>
      <button type="button" class="btn rc-btn-primario" data-bs-dismiss="modal">Listo</button>
    </div>
  </div></div>
</div>

<!-- Perfil -->
<div class="modal fade rc-modal" id="rc-modal-perfil" tabindex="-1" aria-labelledby="rc-perfil-titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content rc-vitral">
    <div class="modal-header"><h2 class="modal-title" id="rc-perfil-titulo">Mi perfil</h2>
      <button type="button" class="rc-btn-icono" data-bs-dismiss="modal" aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
    <div class="modal-body">
      <dl class="rc-lista-def">
        <dt>Nombre</dt><dd><?= e($u['nombre']) ?></dd>
        <dt>Usuario</dt><dd><?= e($u['usuario']) ?></dd>
        <dt>Correo</dt><dd><?= e($u['email']) ?></dd>
        <dt>Rol</dt><dd><?= e(ucfirst($u['rol'])) ?></dd>
      </dl>
    </div>
    <div class="modal-footer"><button type="button" class="btn rc-btn-primario" data-bs-dismiss="modal">Cerrar</button></div>
  </div></div>
</div>

<!-- Cambiar contraseña -->
<div class="modal fade rc-modal" id="rc-modal-clave" tabindex="-1" aria-labelledby="rc-clave-titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content rc-vitral">
    <div class="modal-header"><h2 class="modal-title" id="rc-clave-titulo">Cambiar contraseña</h2>
      <button type="button" class="rc-btn-icono" data-bs-dismiss="modal" aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
    <form id="rc-form-clave" novalidate>
      <div class="modal-body">
        <?php foreach ([['actual', 'Contraseña actual', 'current-password'], ['nueva', 'Contraseña nueva', 'new-password'], ['confirmar', 'Repite la contraseña nueva', 'new-password']] as [$n, $t, $ac]): ?>
          <div class="mb-3">
            <label class="form-label" for="clave-<?= $n ?>"><?= $t ?></label>
            <input class="form-control" type="password" id="clave-<?= $n ?>" name="<?= $n ?>" autocomplete="<?= $ac ?>" aria-describedby="clave-<?= $n ?>-error">
            <div class="rc-error" id="clave-<?= $n ?>-error" role="alert"></div>
          </div>
        <?php endforeach; ?>
        <p class="rc-texto-sec mb-0">Mínimo 12 caracteres con mayúscula, minúscula, número y símbolo.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn rc-btn-secundario" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn rc-btn-primario">Guardar contraseña</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Aviso: sesión a punto de cerrarse por inactividad -->
<div class="modal fade rc-modal" id="rc-modal-sesion" tabindex="-1" aria-labelledby="rc-sesion-titulo" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content rc-vitral">
    <div class="modal-header"><h2 class="modal-title" id="rc-sesion-titulo"><?= icono('reloj') ?> ¿Sigues ahí?</h2></div>
    <div class="modal-body"><p class="mb-0" role="alert">Tu sesión se cerrará en un minuto por inactividad.</p></div>
    <div class="modal-footer">
      <button type="button" class="btn rc-btn-secundario" id="rc-sesion-salir">Cerrar sesión</button>
      <button type="button" class="btn rc-btn-primario" id="rc-sesion-seguir">Seguir conectado</button>
    </div>
  </div></div>
</div>
<?php if (($extra['recurso'] ?? '') === 'circuitos'): ?>
<link rel="stylesheet" href="<?= e($base) ?>/assets/mapa-circuito.css">
<script src="<?= e($base) ?>/assets/mapa-circuito.js" defer></script>
<?php endif; ?>
</body>
</html>
