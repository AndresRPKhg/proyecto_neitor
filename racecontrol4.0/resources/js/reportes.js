// Reportes: vista en pantalla y exportación a CSV.
import { $, RC, api, esc, spinner, toast } from './core.js';
import { tabla } from './grafico.js';

export async function iniciarReportes() {
  const [tipos, camps, carreras] = await Promise.all([api('/reportes'), api('/campeonatos/opciones'), api('/carreras/opciones')]);
  $('#rep-tipo').innerHTML = Object.entries(tipos).map(([k, t]) => `<option value="${k}">${esc(t)}</option>`).join('');
  $('#rep-campeonato').innerHTML = camps.map((c) => `<option value="${c.id}">${esc(c.etiqueta)}</option>`).join('');
  $('#rep-carrera').innerHTML = carreras.map((c) => `<option value="${c.id}">${esc(c.etiqueta)}</option>`).join('');

  const params = () => {
    const tipo = $('#rep-tipo').value;
    const p = new URLSearchParams();
    if (tipo === 'posiciones') p.set('campeonato_id', $('#rep-campeonato').value);
    if (tipo === 'resultados') p.set('carrera_id', $('#rep-carrera').value);
    return { tipo, p };
  };
  const actualizar = () => {
    const { tipo, p } = params();
    $('#rep-param-camp').hidden = tipo !== 'posiciones';
    $('#rep-param-carrera').hidden = tipo !== 'resultados';
    p.set('formato', 'csv');
    $('#rep-csv').href = `${RC.base}/api/reportes/${tipo}?${p}`;
  };
  ['#rep-tipo', '#rep-campeonato', '#rep-carrera'].forEach((s) => $(s).addEventListener('change', actualizar));
  actualizar();

  $('#rep-ver').addEventListener('click', async () => {
    const { tipo, p } = params();
    const zona = $('#rep-resultado');
    zona.innerHTML = spinner();
    try {
      const r = await api(`/reportes/${tipo}?${p}`);
      zona.innerHTML = `<h2 class="rc-titulo-tarjeta">${esc(r.titulo)}</h2><div class="table-responsive" id="rep-tabla"></div>`;
      tabla('rep-tabla', Object.values(r.columnas), r.filas.map((f) => Object.keys(r.columnas).map((k) => f[k] ?? '')), r.titulo);
    } catch (err) {
      zona.innerHTML = `<p class="rc-error-bloque" role="alert">${esc(err.message)}</p>`;
      toast('error', err.message);
    }
  });
}
