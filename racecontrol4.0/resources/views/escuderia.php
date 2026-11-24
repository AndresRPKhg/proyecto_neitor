<header class="rc-cabecera-pagina d-flex flex-wrap align-items-end gap-3">
  <div class="me-auto">
    <h1 class="rc-titulo-pagina" id="escuderia-titulo">Escudería</h1>
    <p class="rc-texto-sec mb-0">Información y pilotos pertenecientes a esta escudería.</p>
  </div>
  <a class="btn rc-btn-secundario" href="<?= e($base) ?>/escuderias">Volver a escuderías</a>
</header>
<?php require __DIR__ . '/partials/filigrana.php'; ?>
<div class="row g-3">
  <section class="col-12" aria-labelledby="escuderia-datos-titulo">
    <article class="rc-tarjeta">
      <h2 class="rc-titulo-tarjeta" id="escuderia-datos-titulo">Datos de la escudería</h2>
      <dl class="rc-lista-def mb-0" id="escuderia-datos" aria-live="polite" aria-busy="true">
        <dt>Nombre</dt><dd><span class="rc-spinner" role="status" aria-label="Cargando"></span></dd>
      </dl>
    </article>
  </section>
  <section class="col-12" aria-labelledby="escuderia-pilotos-titulo">
    <article class="rc-tarjeta">
      <h2 class="rc-titulo-tarjeta" id="escuderia-pilotos-titulo">Pilotos de la escudería</h2>
      <div id="escuderia-pilotos" aria-live="polite" aria-busy="true"></div>
    </article>
  </section>
</div>
