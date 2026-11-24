// Detalle de campeonato: flujo lineal por pestañas (Información, Carreras, Inscritos, Resultados, Posiciones).
import { Tab } from 'bootstrap';
import { $, $$, RC, api, confirmar, esc, icono, limpiarErrores, mostrarErrores, puede, spinner, toast } from './core.js';
import { montarTabla } from './tabla.js';

const ESTADOS = { clasificado: 'Clasificado', dnf: 'No terminó (DNF)', dsq: 'Descalificado' };
const ETIQUETAS_TIPO = { auto: 'Auto', moto: 'Moto', mixto: 'Mixto (autos y motos)' };
let camp;

const vacio = (titulo, texto) => `<div class="rc-vacio">${icono('calavera', 'rc-vacio-icono')}<p class="rc-vacio-titulo">${esc(titulo)}</p><p class="rc-texto-sec">${esc(texto)}</p></div>`;

function info() {
  $('#camp-info').innerHTML = `<div class="rc-tarjeta"><dl class="rc-lista-def">
    <dt>Nombre</dt><dd>${esc(camp.nombre)}</dd><dt>Temporada</dt><dd>${esc(camp.temporada)}</dd>
    <dt>Tipo de vehículo</dt><dd>${camp.tipo_vehiculo === 'mixto' ? 'Mixto (autos y motos)' : camp.tipo_vehiculo === 'auto' ? 'Autos' : 'Motos'}</dd>
    <dt>Puntos por posición</dt><dd>${esc(camp.puntos.split(',').join(' · '))}</dd><dt>Carreras</dt><dd>${esc(camp.total_carreras)}</dd></dl>
    <p class="rc-texto-sec mb-0">Siguiente paso: crea o elige una carrera en la pestaña «Carreras».</p></div>`;
}

async function opcionesCarreras(select) {
  const r = await api('/carreras', { params: { campeonato_id: camp.id, por: 50, orden: 'fecha' } });
  select.innerHTML = r.datos.length ? r.datos.map((c) => `<option value="${c.id}" data-estado="${c.estado}" data-cupo="${c.cupo_maximo}" data-tipo="${c.tipo_vehiculo}">${esc(c.nombre)} · ${esc(c.fecha)} · ${esc(ETIQUETAS_TIPO[c.tipo_vehiculo])} · ${c.estado === 'finalizada' ? 'finalizada' : 'programada'}</option>`).join('')
    : '<option value="">Este campeonato aún no tiene carreras</option>';
  return r.datos;
}

// ---------- Inscritos ----------
async function inscritos() {
  const sel = $('#ins-carrera');
  const carreras = await opcionesCarreras(sel);
  const zona = $('#ins-contenido');
  if (!carreras.length) { zona.innerHTML = vacio('Primero crea una carrera', 'Ve a la pestaña «Carreras» y registra la primera.'); return; }
  const dibujar = async () => {
    const carrera = carreras.find((c) => String(c.id) === sel.value);
    zona.innerHTML = spinner();
    const [lista, pilotos] = await Promise.all([
      api(`/carreras/${carrera.id}/inscripciones`),
      api('/pilotos/opciones', { params: { tipo_vehiculo: carrera.tipo_vehiculo } }),
    ]);
    const gestiona = puede('inscripciones.gestionar');
    const cerrada = carrera.estado === 'finalizada';
    const tipo = carrera.tipo_vehiculo === 'auto' ? 'autos' : carrera.tipo_vehiculo === 'moto' ? 'motos' : 'autos o motos';
    zona.innerHTML = `<p class="rc-texto-sec" role="status">Cupos: <strong>${lista.length} de ${carrera.cupo_maximo}</strong> ocupados.${cerrada ? ' La carrera ya finalizó.' : ''}</p>
      ${gestiona && !cerrada && pilotos.length ? `<form class="rc-tarjeta rc-form-linea mb-3" id="ins-form" novalidate>
        <div><label class="form-label" for="ins-piloto_id">Piloto (${tipo})</label><select class="form-select" id="ins-piloto_id" required><option value="">Elige un piloto</option>${pilotos.map((p) => `<option value="${p.id}">${esc(p.etiqueta)}</option>`).join('')}</select><div class="rc-error" id="ins-piloto_id-error" role="alert"></div>
          <div class="rc-texto-sec mt-2 mb-0" id="ins-vehiculo" role="status" aria-live="polite">Al elegir un piloto se usará automáticamente su vehículo asignado.</div><div class="rc-error" id="ins-vehiculo_id-error" role="alert"></div></div>
        <div><button class="btn rc-btn-primario" type="submit">${icono('mas')} Inscribir</button></div><div class="rc-error w-100" id="ins-carrera_id-error" role="alert"></div></form>` : ''}
      ${gestiona && !cerrada && !pilotos.length ? `<p class="rc-aviso" role="status">No hay pilotos con un vehículo de ${tipo} asignado. Asígnalo en <a class="rc-enlace" href="${RC.base}/vehiculos">Vehículos</a>.</p>` : ''}
      ${lista.length ? `<div class="rc-tarjeta rc-tarjeta-tabla"><div class="table-responsive"><table class="table rc-tabla"><caption class="visually-hidden">Pilotos inscritos</caption>
        <thead><tr><th scope="col">Piloto</th><th scope="col">Documento</th><th scope="col">Escudería</th><th scope="col">Vehículo</th><th scope="col"><span class="visually-hidden">Acciones</span></th></tr></thead>
        <tbody>${lista.map((i) => `<tr><td class="rc-celda-principal">${esc(i.piloto)}</td><td>${esc(i.documento)}</td><td>${esc(i.equipo || '—')}</td><td>${esc(ETIQUETAS_TIPO[i.tipo_vehiculo])} · ${esc(i.vehiculo)} (${esc(i.placa)})</td>
          <td class="rc-acciones">${gestiona && !cerrada ? `<button type="button" class="btn rc-btn-peligro-borde btn-sm" data-quitar="${i.id}" aria-label="Quitar a ${esc(i.piloto)}">${icono('basura')} Quitar</button>` : ''}</td></tr>`).join('')}</tbody></table></div></div>`
        : vacio('Aún no hay pilotos inscritos', gestiona && !cerrada ? 'Elige un piloto para inscribir al primero.' : 'Todavía no se inscribió a nadie.')}`;
    const form = $('#ins-form');
    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault(); limpiarErrores(form);
        try {
          const vehiculo = $('#ins-vehiculo_id', form);
          if (vehiculo && !vehiculo.value) {
            $('#ins-vehiculo_id-error', form).textContent = 'Elige el vehículo que usará este piloto.';
            vehiculo.focus();
            return;
          }
          await api(`/carreras/${carrera.id}/inscripciones`, { metodo: 'POST', cuerpo: { piloto_id: $('#ins-piloto_id', form).value, ...(vehiculo ? { vehiculo_id: vehiculo.value } : {}) } });
          toast('exito', 'Piloto inscrito con su vehículo asignado.'); dibujar();
        } catch (err) { const p = mostrarErrores(form, err.errores, 'ins-'); p ? p.focus() : toast('error', err.message); }
      });
      const selectorPiloto = $('#ins-piloto_id', form);
      const resumen = $('#ins-vehiculo', form);
      let solicitudVehiculo = 0;
      selectorPiloto.addEventListener('change', async () => {
        const solicitud = ++solicitudVehiculo;
        const pilotoId = selectorPiloto.value;
        if (!pilotoId) {
          resumen.textContent = 'Al elegir un piloto se usará automáticamente su vehículo asignado.';
          resumen.setAttribute('aria-busy', 'false');
          return;
        }
        resumen.textContent = 'Buscando su vehículo asignado…';
        resumen.setAttribute('aria-busy', 'true');
        try {
          const params = { piloto_id: pilotoId };
          if (carrera.tipo_vehiculo !== 'mixto') params.tipo_vehiculo = carrera.tipo_vehiculo;
          const vehiculos = await api('/vehiculos/opciones', { params });
          if (solicitudVehiculo !== solicitud || selectorPiloto.value !== pilotoId) return;
          if (vehiculos.length === 1) {
            resumen.textContent = `Se usará automáticamente: ${ETIQUETAS_TIPO[vehiculos[0].tipo_vehiculo] || ''} · ${vehiculos[0].etiqueta}.`;
          } else if (carrera.tipo_vehiculo === 'mixto' && vehiculos.length > 1) {
            resumen.innerHTML = `<label class="form-label" for="ins-vehiculo_id">Vehículo para esta carrera</label><select class="form-select" id="ins-vehiculo_id" required><option value="">Elige auto o moto</option>${vehiculos.map((v) => `<option value="${v.id}">${esc(ETIQUETAS_TIPO[v.tipo_vehiculo])} · ${esc(v.etiqueta)}</option>`).join('')}</select>`;
          } else {
            resumen.textContent = 'No se encontró un único vehículo asignado para esta carrera. Revisa el catálogo de Vehículos.';
          }
        } catch (err) {
          if (solicitudVehiculo === solicitud) {
            resumen.textContent = 'No se pudo comprobar el vehículo asignado.';
            toast('error', err.message);
          }
        } finally {
          if (solicitudVehiculo === solicitud) resumen.setAttribute('aria-busy', 'false');
        }
      });
    }
    $$('[data-quitar]', zona).forEach((b) => b.addEventListener('click', async () => {
      if (!(await confirmar({ titulo: 'Quitar inscripción', mensaje: 'Se quitará al piloto de esta carrera. Podrás inscribirlo de nuevo mientras haya cupo.', boton: 'Quitar' }))) return;
      try { await api(`/inscripciones/${b.dataset.quitar}`, { metodo: 'DELETE' }); toast('exito', 'Inscripción eliminada.'); dibujar(); } catch (err) { toast('error', err.message); }
    }));
  };
  sel.onchange = dibujar;
  await dibujar();
}

// ---------- Resultados ----------
async function resultados() {
  const sel = $('#res-carrera');
  const carreras = await opcionesCarreras(sel);
  const zona = $('#res-contenido');
  if (!carreras.length) { zona.innerHTML = vacio('Primero crea una carrera', 'Los resultados se registran sobre una carrera con pilotos inscritos.'); return; }
  const dibujar = async () => {
    const carrera = carreras.find((c) => String(c.id) === sel.value);
    zona.innerHTML = spinner();
    const filas = await api(`/carreras/${carrera.id}/resultados`);
    const gestiona = puede('resultados.gestionar');
    if (!filas.length) { zona.innerHTML = vacio('No hay pilotos inscritos', 'Inscribe pilotos en la pestaña «Inscritos» antes de registrar resultados.'); return; }
    zona.innerHTML = `<form id="res-form" novalidate><div class="rc-tarjeta rc-tarjeta-tabla"><div class="table-responsive"><table class="table rc-tabla"><caption class="visually-hidden">Resultados de la carrera</caption>
      <thead><tr><th scope="col">Pos.</th><th scope="col">Piloto</th><th scope="col">Vehículo</th><th scope="col">Estado</th><th scope="col">Tiempo total (mm:ss.mmm)</th><th scope="col">Mejor vuelta (mm:ss.mmm)</th><th scope="col">Puntos</th></tr></thead>
      <tbody>${filas.map((f) => `<tr data-ins="${f.inscripcion_id}"><td>${f.posicion ?? '—'}</td><th scope="row" class="rc-celda-principal">${esc(f.piloto)}<div class="rc-error" data-campo="fila_${f.inscripcion_id}" id="res-fila_${f.inscripcion_id}-error" role="alert"></div></th><td>${esc(ETIQUETAS_TIPO[f.tipo_vehiculo])} · ${esc(f.vehiculo)}</td>
        <td><label class="visually-hidden" for="est-${f.inscripcion_id}">Estado de ${esc(f.piloto)}</label><select class="form-select" id="est-${f.inscripcion_id}" ${gestiona ? '' : 'disabled'}>${Object.entries(ESTADOS).map(([v, t]) => `<option value="${v}" ${f.estado === v ? 'selected' : ''}>${t}</option>`).join('')}</select></td>
        <td><label class="visually-hidden" for="tie-${f.inscripcion_id}">Tiempo de ${esc(f.piloto)}</label><input class="form-control" id="tie-${f.inscripcion_id}" value="${esc(f.tiempo)}" placeholder="45:12.345" inputmode="numeric" ${gestiona ? '' : 'disabled'}></td>
        <td><label class="visually-hidden" for="vue-${f.inscripcion_id}">Mejor vuelta de ${esc(f.piloto)}</label><input class="form-control" id="vue-${f.inscripcion_id}" value="${esc(f.mejor_vuelta)}" placeholder="01:32.456" inputmode="numeric" ${gestiona ? '' : 'disabled'}></td>
        <td>${esc(f.puntos ?? 0)}</td></tr>`).join('')}</tbody></table></div></div>
      ${gestiona ? `<div class="d-flex flex-wrap gap-2 mt-3"><button class="btn rc-btn-primario" type="submit">Guardar resultados</button>
        <label class="btn rc-btn-secundario mb-0" for="res-csv">${icono('subir')} Importar CSV</label><input type="file" id="res-csv" accept=".csv,text/csv" class="visually-hidden"></div>
        <p class="rc-texto-sec mt-2">Las posiciones y los puntos se calculan solos según el tiempo. CSV: columnas <code>documento,tiempo,mejor_vuelta,estado</code>.</p>` : ''}</form>`;
    const form = $('#res-form');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      $$('.rc-error', form).forEach((x) => { x.textContent = ''; });
      const resultados = $$('tr[data-ins]', form).map((tr) => ({ inscripcion_id: tr.dataset.ins, estado: $(`#est-${tr.dataset.ins}`).value, tiempo: $(`#tie-${tr.dataset.ins}`).value, mejor_vuelta: $(`#vue-${tr.dataset.ins}`).value }));
      try { await api(`/carreras/${carrera.id}/resultados`, { metodo: 'POST', cuerpo: { resultados } }); toast('exito', 'Resultados guardados y posiciones recalculadas.'); carrera.estado = 'finalizada'; dibujar(); }
      catch (err) { const p = mostrarErrores(form, err.errores, 'res-'); toast('error', err.message); p?.focus(); }
    });
    $('#res-csv')?.addEventListener('change', async (e) => {
      const archivo = e.target.files[0]; if (!archivo) return;
      try { await api(`/carreras/${carrera.id}/resultados/importar`, { metodo: 'POST', cuerpo: { csv: await archivo.text() } }); toast('exito', 'CSV importado y posiciones recalculadas.'); dibujar(); }
      catch (err) { toast('error', [err.message, ...Object.values(err.errores || {})].join(' ')); }
    });
  };
  sel.onchange = dibujar;
  await dibujar();
}

// ---------- Posiciones ----------
async function posiciones() {
  const filas = await api(`/campeonatos/${camp.id}/posiciones`);
  $('#pos-exportar').href = `${RC.base}/api/reportes/posiciones?campeonato_id=${camp.id}&formato=csv`;
  $('#pos-contenido').innerHTML = filas.length ? `<div class="rc-tarjeta rc-tarjeta-tabla"><div class="table-responsive"><table class="table rc-tabla"><caption class="visually-hidden">Tabla de posiciones</caption>
    <thead><tr><th scope="col">Pos.</th><th scope="col">Piloto</th><th scope="col">Escudería</th><th scope="col">Puntos</th><th scope="col">Victorias</th><th scope="col">Podios</th><th scope="col">Carreras</th></tr></thead>
    <tbody>${filas.map((f) => `<tr><td>${f.posicion}</td><th scope="row" class="rc-celda-principal">${esc(f.piloto)}</th><td>${esc(f.equipo || '—')}</td><td><strong>${esc(f.puntos)}</strong></td><td>${f.victorias}</td><td>${f.podios}</td><td>${f.carreras}</td></tr>`).join('')}</tbody></table></div></div>`
    : vacio('Aún no hay posiciones', 'Registra los resultados de una carrera y la tabla se calcula sola.');
}

export async function iniciarCampeonato() {
  camp = await api(`/campeonatos/${RC.id}`);
  $('#camp-titulo').textContent = `${camp.nombre} ${camp.temporada}`;
  $('#camp-migas').textContent = camp.nombre;
  document.title = `${camp.nombre} · RaceControl`;
  info();
  const cargadas = {};
  const carreras = () => {
    const filtrosFijos = { campeonato_id: camp.id };
    const valoresFijos = { campeonato_id: camp.id };
    const ocultarFiltros = ['campeonato_id'];
    return montarTabla($('#camp-carreras'), { recurso: 'carreras', filtrosFijos, valoresFijos, ocultarFiltros, botonNuevo: '#camp-nueva-carrera', alCambiar: () => { camp.total_carreras = '…'; } });
  };
  const perezoso = { carreras,
    inscritos, resultados, posiciones };
  $$('#camp-tabs [data-bs-toggle=tab]').forEach((b) => b.addEventListener('shown.bs.tab', async () => {
    const id = b.id.replace('tab-', '');
    if (id === 'carreras' && cargadas[id]) return;
    cargadas[id] = true;
    try { await perezoso[id]?.(); } catch (err) { toast('error', err.message); }
  }));
  if (location.hash && $(`#tab-${location.hash.slice(1)}`)) Tab.getOrCreateInstance($(`#tab-${location.hash.slice(1)}`)).show();
}
