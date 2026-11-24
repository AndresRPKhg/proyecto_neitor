<?php /* Formulario de inicio de sesión (validación inline + servidor). */ ?>
<h2 class="rc-titulo-auth">Iniciar sesión</h2>
<?php if (isset($_GET['registrado'])): ?><div class="rc-exito mb-3" role="status">Cuenta creada. Ya puedes iniciar sesión.</div><?php endif; ?>
<form id="rc-form-login" novalidate>
  <div class="mb-3">
    <label class="form-label" for="login-usuario">Usuario o correo</label>
    <input class="form-control" type="text" id="login-usuario" name="usuario" autocomplete="username" autofocus required aria-describedby="login-usuario-error">
    <div class="rc-error" id="login-usuario-error" role="alert"></div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="login-clave">Contraseña</label>
    <div class="rc-auth-clave d-flex gap-2">
      <input class="form-control flex-grow-1" type="password" id="login-clave" name="clave" autocomplete="current-password" required aria-describedby="login-clave-error">
      <button type="button" class="btn rc-btn-secundario" id="login-ver-clave" aria-label="Mostrar contraseña" aria-controls="login-clave" aria-pressed="false">Mostrar</button>
    </div>
    <div class="rc-error" id="login-clave-error" role="alert"></div>
  </div>
  <div class="rc-error rc-error-general mb-3" id="login-error" role="alert"></div>
  <button type="submit" class="btn rc-btn-primario w-100" id="login-enviar">Entrar</button>
</form>
<p class="text-center mt-4 mb-0"><a class="rc-enlace" href="<?= e($base) ?>/recuperar">¿Olvidaste tu contraseña?</a></p>
<p class="text-center mt-2 mb-0">¿No tienes cuenta? <a class="rc-enlace" href="<?= e($base) ?>/registro">Regístrate</a></p>
