// Configuración del sistema: sistema de puntos por defecto.
import { $, api, limpiarErrores, mostrarErrores, toast } from './core.js';

export async function iniciarConfiguracion() {
  const form = $('#rc-form-config');
  $('#cfg-puntos').value = (await api('/configuracion')).puntos_por_defecto;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    limpiarErrores(form);
    try {
      const r = await api('/configuracion', { metodo: 'PUT', cuerpo: { puntos_por_defecto: $('#cfg-puntos').value } });
      $('#cfg-puntos').value = r.puntos_por_defecto;
      toast('exito', 'Sistema de puntos guardado.');
    } catch (err) {
      const primero = mostrarErrores(form, err.errores, 'cfg-');
      const caja = $('#cfg-puntos-error');
      if (err.errores?.puntos_por_defecto) { caja.textContent = err.errores.puntos_por_defecto; $('#cfg-puntos').focus(); } else if (!primero) toast('error', err.message);
    }
  });
}
