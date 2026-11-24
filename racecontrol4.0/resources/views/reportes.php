<?php /* Reportes y exportación CSV. */ ?>
<header class="rc-cabecera-pagina">
  <h1 class="rc-titulo-pagina">Reportes</h1>
  <p class="rc-texto-sec">Consulta en pantalla o exporta a CSV (compatible con Excel).</p>
</header>
<?php require __DIR__ . '/partials/filigrana.php'; ?>
<div class="rc-tarjeta mb-4">
  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-4"><label class="form-label" for="rep-tipo">Reporte</label><select class="form-select" id="rep-tipo"></select></div>
    <div class="col-12 col-md-4" id="rep-param-camp" hidden><label class="form-label" for="rep-campeonato">Campeonato</label><select class="form-select" id="rep-campeonato"></select></div>
    <div class="col-12 col-md-4" id="rep-param-carrera" hidden><label class="form-label" for="rep-carrera">Carrera</label><select class="form-select" id="rep-carrera"></select></div>
    <div class="col-12 col-md-auto d-flex gap-2">
      <button type="button" class="btn rc-btn-primario" id="rep-ver">Ver</button>
      <a class="btn rc-btn-secundario" id="rep-csv" href="#"><?= icono('bajar') ?> Exportar CSV</a>
    </div>
  </div>
</div>
<div class="rc-tarjeta" id="rep-resultado" aria-live="polite"><p class="rc-texto-sec mb-0">Elige un reporte y pulsa «Ver».</p></div>
