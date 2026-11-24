<?php /* Módulo genérico de catálogo: la tabla, filtros y formulario los construye el JavaScript a partir de /api/{recurso}/meta. */ ?>
<header class="rc-cabecera-pagina d-flex flex-wrap align-items-end gap-3">
  <div class="me-auto">
    <h1 class="rc-titulo-pagina"><?= e($titulo) ?></h1>
    <p class="rc-texto-sec mb-0">Busca, filtra y ordena. Los cambios se validan en el servidor.</p>
  </div>
  <button type="button" class="btn rc-btn-primario" id="rc-nuevo" hidden><?= icono('mas') ?> Nuevo <?= e(\RaceControl\Application\Recursos::get($extra['recurso'])['singular']) ?></button>
</header>
<?php require __DIR__ . '/partials/filigrana.php'; ?>
<div id="rc-tabla" data-recurso="<?= e($extra['recurso']) ?>"></div>
