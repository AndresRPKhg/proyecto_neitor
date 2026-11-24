<?php /* Configuración del sistema (solo Admin). */ ?>
<header class="rc-cabecera-pagina">
  <h1 class="rc-titulo-pagina">Configuración</h1>
  <p class="rc-texto-sec">Ajustes generales de RaceControl.</p>
</header>
<?php require __DIR__ . '/partials/filigrana.php'; ?>
<div class="row g-4">
  <section class="col-12 col-lg-6" aria-labelledby="cfg-puntos-t">
    <div class="rc-tarjeta">
      <h2 class="rc-titulo-tarjeta" id="cfg-puntos-t">Sistema de puntos por defecto</h2>
      <p class="rc-texto-sec">Se propone al crear campeonatos nuevos. Cada campeonato puede tener su propio sistema; cambiarlo allí recalcula sus resultados.</p>
      <form id="rc-form-config" novalidate>
        <label class="form-label" for="cfg-puntos">Puntos por posición (separados por coma)</label>
        <input class="form-control" id="cfg-puntos" name="puntos_por_defecto" aria-describedby="cfg-puntos-error">
        <div class="rc-error" id="cfg-puntos-error" role="alert"></div>
        <button type="submit" class="btn rc-btn-primario mt-3">Guardar</button>
      </form>
    </div>
  </section>
  <section class="col-12 col-lg-6" aria-labelledby="cfg-usr-t">
    <div class="rc-tarjeta">
      <h2 class="rc-titulo-tarjeta" id="cfg-usr-t">Usuarios y roles</h2>
      <p class="rc-texto-sec">Administra cuentas y permisos (Admin, Editor y Lector).</p>
      <a class="btn rc-btn-secundario" href="<?= e($base) ?>/usuarios"><?= icono('usuario') ?> Ir a usuarios</a>
    </div>
  </section>
  <section class="col-12" aria-labelledby="cfg-pref-t">
    <div class="rc-tarjeta">
      <h2 class="rc-titulo-tarjeta" id="cfg-pref-t">Preferencias de interfaz</h2>
      <p class="rc-texto-sec mb-3">Tamaño de tabla, menú lateral y animaciones se ajustan en el panel de control (icono del engranaje, arriba a la derecha).</p>
      <button type="button" class="btn rc-btn-secundario" data-bs-toggle="modal" data-bs-target="#rc-modal-panel"><?= icono('engranaje') ?> Abrir panel de control</button>
    </div>
  </section>
</div>
