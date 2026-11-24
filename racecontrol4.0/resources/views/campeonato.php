<?php /* Detalle de campeonato: flujo lineal con pestañas Información / Carreras / Inscritos / Resultados / Posiciones. */ ?>
<header class="rc-cabecera-pagina">
  <p class="rc-migas"><a class="rc-enlace" href="<?= e($base) ?>/campeonatos">Campeonatos</a> <span aria-hidden="true">›</span> <span id="camp-migas">…</span></p>
  <h1 class="rc-titulo-pagina" id="camp-titulo">Campeonato</h1>
</header>
<?php require __DIR__ . '/partials/filigrana.php'; ?>

<ol class="rc-pasos" aria-label="Flujo del campeonato">
  <li>1. Elegir carrera</li><li>2. Inscribir pilotos</li><li>3. Registrar resultados</li><li>4. Ver posiciones</li>
</ol>

<ul class="nav rc-tabs" id="camp-tabs" role="tablist">
  <?php foreach ([['info', 'Información'], ['carreras', 'Carreras'], ['inscritos', 'Inscritos'], ['resultados', 'Resultados'], ['posiciones', 'Posiciones']] as $i => [$id, $t]): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link<?= $i === 0 ? ' active' : '' ?>" id="tab-<?= $id ?>" data-bs-toggle="tab" data-bs-target="#panel-<?= $id ?>" type="button" role="tab"
              aria-controls="panel-<?= $id ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= $t ?></button>
    </li>
  <?php endforeach; ?>
</ul>
<div class="tab-content rc-tab-contenido">
  <div class="tab-pane fade show active" id="panel-info" role="tabpanel" aria-labelledby="tab-info" tabindex="0"><div id="camp-info"></div></div>
  <div class="tab-pane fade" id="panel-carreras" role="tabpanel" aria-labelledby="tab-carreras" tabindex="0">
    <div class="d-flex justify-content-end mb-3"><button type="button" class="btn rc-btn-primario" id="camp-nueva-carrera" hidden><?= icono('mas') ?> Nueva carrera</button></div>
    <div id="camp-carreras"></div>
  </div>
  <div class="tab-pane fade" id="panel-inscritos" role="tabpanel" aria-labelledby="tab-inscritos" tabindex="0">
    <div class="mb-3"><label class="form-label" for="ins-carrera">Carrera</label><select class="form-select rc-select-ancho" id="ins-carrera"></select></div>
    <div id="ins-contenido"></div>
  </div>
  <div class="tab-pane fade" id="panel-resultados" role="tabpanel" aria-labelledby="tab-resultados" tabindex="0">
    <div class="mb-3"><label class="form-label" for="res-carrera">Carrera</label><select class="form-select rc-select-ancho" id="res-carrera"></select></div>
    <div id="res-contenido"></div>
  </div>
  <div class="tab-pane fade" id="panel-posiciones" role="tabpanel" aria-labelledby="tab-posiciones" tabindex="0">
    <div class="d-flex justify-content-end mb-3"><a class="btn rc-btn-secundario" id="pos-exportar" href="#"><?= icono('bajar') ?> Exportar CSV</a></div>
    <div id="pos-contenido"></div>
  </div>
</div>
