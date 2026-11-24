<?php /* Panel principal: KPI, gráficas con tablas de datos alternativas, estadística, outliers y calidad de datos. */ ?>
<header class="rc-cabecera-pagina">
  <h1 class="rc-titulo-pagina">Panel principal</h1>
  <p class="rc-texto-sec">Estado de la temporada, rendimiento y salud del sistema.</p>
</header>
<?php require __DIR__ . '/partials/filigrana.php'; ?>

<section aria-labelledby="kpi-titulo" class="mb-4">
  <h2 id="kpi-titulo" class="rc-titulo-seccion">Indicadores clave</h2>
  <div class="row g-3" id="dash-kpis" aria-busy="true">
    <?php foreach ([['pilotos', 'casco', 'Pilotos registrados', 'pilotos activos'], ['errores', 'alerta', 'Errores', '% de operaciones, últimos 30 días'],
        ['activo', 'reloj', 'Tiempo activo', 'horas, últimos 30 días'], ['uso', 'panel', 'Uso de módulos', 'aperturas, últimos 30 días']] as [$id, $ico, $titulo, $nota]): ?>
      <div class="col-12 col-sm-6 col-xl-3">
        <article class="rc-tarjeta rc-kpi" aria-labelledby="kpi-<?= $id ?>-t">
          <?= icono($ico, 'rc-kpi-icono') ?>
          <h3 class="rc-kpi-titulo" id="kpi-<?= $id ?>-t"><?= e($titulo) ?></h3>
          <p class="rc-kpi-valor" id="kpi-<?= $id ?>"><span class="rc-spinner" role="status" aria-label="Cargando"></span></p>
          <p class="rc-kpi-nota"><?= e($nota) ?></p>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section aria-labelledby="graf-titulo" class="mb-4">
  <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
    <h2 id="graf-titulo" class="rc-titulo-seccion mb-0 me-auto">Gráficas</h2>
    <div><label class="form-label" for="dash-campeonato">Campeonato</label><select class="form-select" id="dash-campeonato"><option value="">Todos</option></select></div>
    <div><label class="form-label" for="dash-piloto">Piloto (tendencia)</label><select class="form-select" id="dash-piloto"><option value="">Elige un piloto</option></select></div>
  </div>
  <div class="row g-3">
    <?php foreach ([['uso', 'Uso por módulo', 'Aperturas de cada módulo'], ['equipos', 'Puntos por escudería', 'Suma de puntos de los pilotos de cada escudería'],
        ['tendencia', 'Tendencia de puntos del piloto', 'Puntos obtenidos carrera a carrera'], ['pastel', 'Pilotos por escudería', 'Distribución de pilotos activos'],
        ['tipos', 'Vehículos por tipo', 'Autos y motos registrados']] as [$id, $titulo, $desc]): ?>
      <div class="col-12 <?= $id === 'tendencia' ? 'col-xl-12' : 'col-xl-6' ?>">
        <article class="rc-tarjeta" aria-labelledby="g-<?= $id ?>-t">
          <h3 class="rc-titulo-tarjeta" id="g-<?= $id ?>-t"><?= e($titulo) ?></h3>
          <p class="rc-texto-sec"><?= e($desc) ?></p>
          <div class="rc-grafico"><canvas id="g-<?= $id ?>" role="img" aria-label="<?= e($titulo) ?>"></canvas></div>
          <details class="rc-detalles"><summary>Ver los datos en tabla</summary><div class="table-responsive" id="g-<?= $id ?>-tabla"></div></details>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section aria-labelledby="est-titulo" class="mb-4">
  <h2 id="est-titulo" class="rc-titulo-seccion">Estadística descriptiva</h2>
  <div class="rc-tarjeta"><div class="table-responsive" id="dash-estadisticas"></div></div>
</section>
<div class="row g-3 mb-4">
  <section class="col-12 col-xl-6" aria-labelledby="out-titulo"><h2 id="out-titulo" class="rc-titulo-seccion">Tiempos fuera de rango</h2>
    <div class="rc-tarjeta"><div class="table-responsive" id="dash-outliers"></div></div></section>
  <section class="col-12 col-xl-6" aria-labelledby="cal-titulo"><h2 id="cal-titulo" class="rc-titulo-seccion">Calidad de datos</h2>
    <div class="rc-tarjeta"><div class="table-responsive" id="dash-calidad"></div></div></section>
</div>
