// Pantallas públicas: inicio de sesión, recuperación y restablecimiento de contraseña.
import { $, RC, api, limpiarErrores, mostrarErrores, toast } from './core.js';

function enviarFormulario(form, ruta, prefijo, alExito, errorGeneral) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    limpiarErrores(form);
    const boton = form.querySelector('[type=submit]');
    boton.disabled = true;
    try {
      alExito(await api(ruta, { metodo: 'POST', cuerpo: Object.fromEntries(new FormData(form)) }));
    } catch (err) {
      const primero = mostrarErrores(form, err.errores, prefijo);
      if (primero) primero.focus();
      else if (errorGeneral) { errorGeneral.textContent = err.message; } else toast('error', err.message);
    } finally {
      boton.disabled = false;
    }
  });
}

export function iniciarAuth() {
  const login = $('#rc-form-login');
  if (login) {
    enviarFormulario(login, '/auth/login', 'login-', (r) => { window.location.href = r.destino; }, $('#login-error'));
  }
  const recuperar = $('#rc-form-recuperar');
  if (recuperar) {
    enviarFormulario(recuperar, '/auth/recuperar', 'rec-', (r) => { $('#rec-mensaje').textContent = r.mensaje; }, null);
  }
  const restablecer = $('#rc-form-restablecer');
  if (restablecer) {
    enviarFormulario(restablecer, '/auth/restablecer', 'rst-', (r) => {
      toast('exito', r.mensaje);
      setTimeout(() => { window.location.href = r.destino; }, 1500);
    }, $('#rst-error'));
  }
}
