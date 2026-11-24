<h2 class="rc-titulo-auth">Nueva contraseña</h2>
<form id="rc-form-restablecer" novalidate>
  <input type="hidden" name="token" id="rst-token" value="<?= e($p->query['token'] ?? '') ?>">
  <div class="mb-3">
    <label class="form-label" for="rst-clave">Contraseña nueva</label>
    <input class="form-control" type="password" id="rst-clave" name="clave" autocomplete="new-password" autofocus required aria-describedby="rst-clave-error">
    <div class="rc-error" id="rst-clave-error" role="alert"></div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="rst-confirmar">Repite la contraseña</label>
    <input class="form-control" type="password" id="rst-confirmar" name="confirmar" autocomplete="new-password" required aria-describedby="rst-confirmar-error">
    <div class="rc-error" id="rst-confirmar-error" role="alert"></div>
  </div>
  <p class="rc-texto-sec">Mínimo 12 caracteres con mayúscula, minúscula, número y símbolo.</p>
  <div class="rc-error rc-error-general mb-3" id="rst-error" role="alert"></div>
  <button type="submit" class="btn rc-btn-primario w-100">Guardar contraseña</button>
</form>
<p class="text-center mt-4 mb-0"><a class="rc-enlace" href="<?= e($base) ?>/login">Volver a iniciar sesión</a></p>
