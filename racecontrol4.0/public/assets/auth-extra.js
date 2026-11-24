(function () {
  var claveLogin = document.getElementById('login-clave');
  var botonVerClave = document.getElementById('login-ver-clave');
  if (claveLogin && botonVerClave) {
    botonVerClave.addEventListener('click', function () {
      var mostrar = claveLogin.type === 'password';
      claveLogin.type = mostrar ? 'text' : 'password';
      botonVerClave.textContent = mostrar ? 'Ocultar' : 'Mostrar';
      botonVerClave.setAttribute('aria-label', (mostrar ? 'Ocultar' : 'Mostrar') + ' contraseña');
      botonVerClave.setAttribute('aria-pressed', String(mostrar));
    });
  }
  var info = document.getElementById('rc-info');
  function abrir() { info.hidden = false; document.getElementById('rc-info-cerrar').focus(); }
  function cerrar() { info.hidden = true; document.getElementById('rc-info-btn').focus(); }
  document.getElementById('rc-info-btn').addEventListener('click', abrir);
  document.getElementById('rc-info-cerrar').addEventListener('click', cerrar);
  info.addEventListener('click', function (e) { if (e.target === info) cerrar(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !info.hidden) cerrar(); });

  var form = document.getElementById('rc-form-registro');
  if (!form) return;
  var datos = JSON.parse(document.getElementById('rc-datos').textContent);
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    form.querySelectorAll('.rc-error').forEach(function (n) { n.textContent = ''; });
    var b = form.querySelector('[type=submit]');
    b.disabled = true;
    try {
      var res = await fetch(datos.base + '/api/auth/registro', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': datos.csrf },
        body: JSON.stringify(Object.fromEntries(new FormData(form))),
      });
      var r = await res.json().catch(function () { return {}; });
      if (res.ok) {
        document.getElementById('reg-error').textContent = '';
        window.location.href = r.destino + '?registrado=1';
        return;
      }
      var errs = r.errores || {};
      var primero = null;
      Object.keys(errs).forEach(function (k) {
        var caja = document.getElementById('reg-' + k + '-error');
        var campo = document.getElementById('reg-' + k);
        if (caja) {
          caja.textContent = errs[k];
          primero = primero || campo;
        }
      });
      if (primero) primero.focus();
      else document.getElementById('reg-error').textContent = r.error || 'No se pudo registrar.';
    } catch (err) {
      document.getElementById('reg-error').textContent = 'No hay conexión con el servidor.';
    } finally {
      b.disabled = false;
    }
  });
})();
