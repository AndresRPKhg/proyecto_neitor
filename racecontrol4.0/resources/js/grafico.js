// Gráficas Chart.js con paleta gótica. Nunca se usa solo el color: cada serie lleva patrón y hay tabla de datos alternativa.
import { Chart, BarController, BarElement, LineController, LineElement, PointElement, PieController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';
import { esc } from './core.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, PieController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend);
const estilosRaiz = getComputedStyle(document.documentElement);
Chart.defaults.color = estilosRaiz.getPropertyValue('--rc-texto').trim() || '#EDE6DA';
Chart.defaults.borderColor = estilosRaiz.getPropertyValue('--rc-borde').trim() || '#2A2430';
Chart.defaults.font.family = "'EB Garamond', Georgia, serif";
Chart.defaults.font.size = 15;
Chart.defaults.font.weight = document.documentElement.dataset.altoContraste === 'true' ? 'bold' : 'normal';

const PALETAS = {
  normal: ['#C9A227', '#B8334A', '#7FD99A', '#8FB4FF', '#E8A0BF', '#F0C987', '#9B8CFF', '#FF8A8A'],
  protanopia: ['#0072B2', '#E69F00', '#56B4E9', '#D55E00', '#F0E442', '#CC79A7', '#009E73', '#666666'],
  deuteranopia: ['#E69F00', '#0072B2', '#D55E00', '#56B4E9', '#CC79A7', '#F0E442', '#666666', '#009E73'],
  tritanopia: ['#D55E00', '#009E73', '#CC79A7', '#E69F00', '#0072B2', '#882255', '#44AA99', '#999933'],
  acromatopsia: ['#111111', '#333333', '#555555', '#777777', '#999999', '#BBBBBB', '#D3D3D3', '#EEEEEE'],
};
const FORMAS = ['rayas', 'puntos', 'cruz', 'solido', 'rayas-inv', 'rejilla', 'ondas', 'solido'];
const GRISES_OSCURO = ['#F4F4F4', '#D9D9D9', '#BFBFBF', '#A6A6A6', '#8C8C8C', '#737373', '#595959', '#404040'];
const GRISES_CLARO = ['#111111', '#292929', '#404040', '#595959', '#737373', '#8C8C8C', '#A6A6A6', '#BFBFBF'];

/** Crea un patrón de relleno (rayas, puntos...) para diferenciar series sin depender del color. */
function patron(forma, color) {
  const c = document.createElement('canvas');
  c.width = c.height = 12;
  const x = c.getContext('2d');
  x.fillStyle = color; x.fillRect(0, 0, 12, 12);
  x.strokeStyle = 'rgba(11,10,12,.75)'; x.fillStyle = 'rgba(11,10,12,.75)'; x.lineWidth = 2;
  if (forma === 'rayas') { x.beginPath(); x.moveTo(0, 12); x.lineTo(12, 0); x.stroke(); }
  else if (forma === 'rayas-inv') { x.beginPath(); x.moveTo(0, 0); x.lineTo(12, 12); x.stroke(); }
  else if (forma === 'puntos') { x.beginPath(); x.arc(6, 6, 2, 0, 7); x.fill(); }
  else if (forma === 'cruz') { x.beginPath(); x.moveTo(6, 1); x.lineTo(6, 11); x.moveTo(1, 6); x.lineTo(11, 6); x.stroke(); }
  else if (forma === 'rejilla') { x.strokeRect(0, 0, 12, 12); }
  else if (forma === 'ondas') { x.beginPath(); x.moveTo(0, 6); x.quadraticCurveTo(3, 0, 6, 6); x.quadraticCurveTo(9, 12, 12, 6); x.stroke(); }
  return x.createPattern(c, 'repeat');
}

const coloresGrafico = () => {
  const raiz = document.documentElement;
  if (raiz.dataset.daltonismo === 'acromatopsia') return raiz.dataset.tema === 'light' ? GRISES_CLARO : GRISES_OSCURO;
  return PALETAS[raiz.dataset.daltonismo] || PALETAS.normal;
};
const rellenos = (n) => {
  const colores = coloresGrafico();
  return Array.from({ length: n }, (_, i) => patron(FORMAS[i % FORMAS.length], colores[i % colores.length]));
};
const instancias = new Map();
function actualizarGraficos() {
  const estilos = getComputedStyle(document.documentElement);
  Chart.defaults.color = estilos.getPropertyValue('--rc-texto').trim();
  Chart.defaults.borderColor = estilos.getPropertyValue('--rc-borde').trim();
  Chart.defaults.font.weight = document.documentElement.dataset.altoContraste === 'true' ? 'bold' : 'normal';
  const colores = coloresGrafico();
  const bordeClaro = estilos.getPropertyValue('--rc-fondo').trim();
  const altoContraste = document.documentElement.dataset.altoContraste === 'true';
  instancias.forEach((grafico) => {
    grafico.data.datasets.forEach((dataset, indice) => {
      if (grafico.config.type === 'line') {
        dataset.borderColor = colores[indice % colores.length];
        dataset.backgroundColor = colores[indice % colores.length];
        dataset.borderWidth = altoContraste ? 4 : 2;
      } else {
        dataset.backgroundColor = rellenos(dataset.data.length);
        dataset.borderColor = grafico.config.type === 'pie' ? bordeClaro : estilos.getPropertyValue('--rc-dorado').trim();
        dataset.borderWidth = altoContraste ? 3 : grafico.config.type === 'pie' ? 2 : 1;
      }
    });
    grafico.update('none');
  });
}
document.addEventListener('rc:tema-cambiado', actualizarGraficos);
document.addEventListener('rc:daltonismo-cambiado', actualizarGraficos);
document.addEventListener('rc:alto-contraste-cambiado', actualizarGraficos);

function nuevo(idCanvas, config) {
  instancias.get(idCanvas)?.destroy();
  const canvas = document.getElementById(idCanvas);
  if (!canvas) return;
  instancias.set(idCanvas, new Chart(canvas, config));
}

const opcionesBase = { responsive: true, maintainAspectRatio: false };

export function barras(id, datos, etiquetaSerie) {
  const dorado = getComputedStyle(document.documentElement).getPropertyValue('--rc-dorado').trim();
  nuevo(id, { type: 'bar', data: { labels: datos.map((d) => d.etiqueta), datasets: [{ label: etiquetaSerie, data: datos.map((d) => Number(d.valor)),
    backgroundColor: rellenos(datos.length), borderColor: dorado, borderWidth: 1 }] },
    options: { ...opcionesBase, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
  tabla(`${id}-tabla`, ['Categoría', etiquetaSerie], datos.map((d) => [d.etiqueta, d.valor]));
}

export function pastel(id, datos, etiquetaSerie) {
  const fondo = getComputedStyle(document.documentElement).getPropertyValue('--rc-fondo').trim();
  nuevo(id, { type: 'pie', data: { labels: datos.map((d) => `${d.etiqueta} (${d.valor})`), datasets: [{ data: datos.map((d) => Number(d.valor)),
    backgroundColor: rellenos(datos.length), borderColor: fondo, borderWidth: 2 }] },
    options: { ...opcionesBase, plugins: { legend: { position: 'right' } } } });
  tabla(`${id}-tabla`, ['Categoría', etiquetaSerie], datos.map((d) => [d.etiqueta, d.valor]));
}

export function lineas(id, filas) {
  const etiquetas = filas.map((f) => f.carrera);
  const colores = coloresGrafico();
  nuevo(id, { type: 'line', data: { labels: etiquetas, datasets: [
    { label: 'Puntos', data: filas.map((f) => Number(f.puntos)), borderColor: colores[0], backgroundColor: colores[0], pointStyle: 'circle', pointRadius: 6, tension: 0.2, yAxisID: 'y' },
    { label: 'Tiempo total (min)', data: filas.map((f) => (f.tiempo_ms ? +(f.tiempo_ms / 60000).toFixed(2) : null)), borderColor: colores[1], backgroundColor: colores[1],
      borderDash: [8, 5], pointStyle: 'rectRot', pointRadius: 7, tension: 0.2, yAxisID: 'y2' }] },
    options: { ...opcionesBase, scales: { y: { beginAtZero: true, title: { display: true, text: 'Puntos' } },
      y2: { position: 'right', title: { display: true, text: 'Minutos' }, grid: { drawOnChartArea: false } } } } });
  tabla(`${id}-tabla`, ['Carrera', 'Fecha', 'Posición', 'Puntos', 'Tiempo (min)'],
    filas.map((f) => [f.carrera, f.fecha, f.posicion ?? '—', f.puntos, f.tiempo_ms ? (f.tiempo_ms / 60000).toFixed(2) : '—']));
}

/** Tabla accesible simple. */
export function tabla(idContenedor, cabeceras, filas, titulo = 'Datos') {
  const el = document.getElementById(idContenedor);
  if (!el) return;
  if (!filas.length) { el.innerHTML = '<p class="rc-texto-sec mb-0">Aún no hay datos para mostrar.</p>'; return; }
  el.innerHTML = `<table class="table rc-tabla"><caption class="visually-hidden">${esc(titulo)}</caption>
    <thead><tr>${cabeceras.map((c) => `<th scope="col">${esc(c)}</th>`).join('')}</tr></thead>
    <tbody>${filas.map((f) => `<tr>${f.map((v, i) => (i === 0 ? `<th scope="row">${esc(v)}</th>` : `<td>${esc(v)}</td>`)).join('')}</tr>`).join('')}</tbody></table>`;
}
