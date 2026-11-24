<h2 class="rc-titulo-auth">Recuperar acceso</h2>
<p class="rc-texto-sec">Escribe tu usuario o correo. Te enviaremos un enlace de un solo uso que vence en 30 minutos.</p>
<form id="rc-form-recuperar" novalidate>
  <div class="mb-3">
    <label class="form-label" for="rec-usuario">Usuario o correo</label>
    <input class="form-control" type="text" id="rec-usuario" name="usuario" autocomplete="username" autofocus required aria-describedby="rec-usuario-error">
    <div class="rc-error" id="rec-usuario-error" role="alert"></div>
  </div>
  <div class="rc-exito mb-3" id="rec-mensaje" role="status"></div>
  <button type="submit" class="btn rc-btn-primario w-100">Enviar enlace</button>
</form>
<p class="text-center mt-4 mb-0"><a class="rc-enlace" href="<?= e($base) ?>/login">Volver a iniciar sesión</a></p>
