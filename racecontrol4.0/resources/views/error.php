<?php /* Página de error (403, 404, 500) con tema gótico. */ ?>
<section class="rc-vacio" aria-labelledby="err-titulo">
  <?= icono('calavera', 'rc-vacio-icono') ?>
  <h1 id="err-titulo" class="rc-titulo-pagina">Error <?= e($http) ?></h1>
  <p><?= e($mensaje) ?></p>
  <a class="btn rc-btn-primario" href="<?= e($p->base) ?>/">Volver al inicio</a>
</section>
