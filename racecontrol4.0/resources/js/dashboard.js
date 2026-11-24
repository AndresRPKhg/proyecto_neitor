// Panel principal: KPI, gráficas, estadística descriptiva, outliers y calidad de datos.
import { $, api, esc, toast } from './core.js';
import { barras, lineas, pastel, tabla } from './grafico.js';

const MODULOS = { pilotos: 'Pilotos', equipos: 'Escuderías', vehiculos: 'Vehículos', circuitos: 'Circuitos', campeonatos: 'Campeonatos', usuarios: 'Usuarios',
  dashboards: 'Panel', reportes: 'Reportes', configuracion: 'Configuración', competencias: 'Competencias', catalogos: 'Catálogos' };

function tablaReporte(id, rep) {
  tabla(id, Object.values(rep.columnas), rep.filas.map((f) => Object.keys(rep.columnas).map((k) => f[k] ?? '')), rep.titulo);
}

async function cargar() {
  const params = { campeonato_id: $('#dash-campeonato').value, piloto_id: $('#dash-piloto').value };
  const d = await api('/dashboard', { params });
  const k = d.kpis;
  $('#kpi-pilotos').textContent = k.pilotos_registrados;
  $('#kpi-errores').textContent = `${k.errores_pct} %`;
  $('#kpi-activo').textContent = `${k.tiempo_activo_horas} h`;
  $('#kpi-uso').textContent = k.uso_por_modulo.reduce((s, m) => s + Number(m.usos), 0);
  $('#dash-kpis').setAttribute('aria-busy', 'false');

  const uso = k.uso_por_modulo.map((m) => ({ etiqueta: MODULOS[m.modulo] || m.modulo, valor: m.usos }));
  barras('g-uso', uso, 'Aperturas');
  barras('g-equipos', d.graficos.puntos_por_equipo, 'Puntos');
  pastel('g-pastel', d.graficos.pilotos_por_equipo, 'Pilotos');
  pastel('g-tipos', d.graficos.vehiculos_por_tipo, 'Vehículos');
  const caja = $('#g-tendencia-t').closest('article').querySelector('.rc-grafico');
  if (d.graficos.tendencia.length) {
    if (!caja.querySelector('canvas')) caja.innerHTML = '<canvas id="g-tendencia" role="img" aria-label="Tendencia de puntos del piloto"></canvas>';
    lineas('g-tendencia', d.graficos.tendencia);
  } else {
    caja.innerHTML = '<p class="rc-texto-sec">Elige un piloto con resultados para ver su tendencia.</p>';
    $('#g-tendencia-tabla').innerHTML = '';
  }
  tablaReporte('dash-estadisticas', d.estadisticas);
  tablaReporte('dash-outliers', d.outliers);
  tablaReporte('dash-calidad', d.calidad);
}

export async function iniciarDashboard() {
  const [camps, pilotos] = await Promise.all([api('/campeonatos/opciones'), api('/pilotos/opciones')]);
  $('#dash-campeonato').insertAdjacentHTML('beforeend', camps.map((c) => `<option value="${c.id}">${esc(c.etiqueta)}</option>`).join(''));
  $('#dash-piloto').insertAdjacentHTML('beforeend', pilotos.map((p) => `<option value="${p.id}">${esc(p.etiqueta)}</option>`).join(''));
  const recargar = () => cargar().catch((e) => toast('error', e.message));
  $('#dash-campeonato').addEventListener('change', recargar);
  $('#dash-piloto').addEventListener('change', recargar);
  await cargar();
}
