<?php /* Formulario de registro. La contraseña se cifra en el servidor (nunca se guarda en texto plano). */ ?>
<h2 class="rc-titulo-auth">Crear cuenta</h2>
<form id="rc-form-registro" novalidate>
<?php foreach ([['nombre','Nombre completo','text','name'],['usuario','Usuario','text','username'],['email','Correo electrónico','email','email'],['clave','Contraseña','password','new-password'],['confirmar','Confirmar contraseña','password','new-password']] as [$n,$l,$t,$ac]): ?>
  <div class="mb-3">
    <label class="form-label" for="reg-<?= $n ?>"><?= $l ?></label>
    <input class="form-control" type="<?= $t ?>" id="reg-<?= $n ?>" name="<?= $n ?>" autocomplete="<?= $ac ?>" required aria-describedby="reg-<?= $n ?>-error">
    <div class="rc-error" id="reg-<?= $n ?>-error" role="alert"></div>
  </div>
<?php endforeach; ?>
  <p class="rc-texto-sec small">Mínimo 12 caracteres con mayúscula, minúscula, número y símbolo.</p>
  <div class="rc-error rc-error-general mb-3" id="reg-error" role="alert"></div>
  <button type="submit" class="btn rc-btn-primario w-100">Registrarme</button>
</form>
<p class="text-center mt-4 mb-0">¿Ya tienes cuenta? <a class="rc-enlace" href="<?= e($base) ?>/login">Inicia sesión</a></p>
