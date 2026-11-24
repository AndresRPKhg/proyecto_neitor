import { $, RC, api, esc, spinner } from './core.js';

export async function iniciarEscuderia() {
  const datos = $('#escuderia-datos');
  const zonaPilotos = $('#escuderia-pilotos');
  const id = Number(RC.id);

  if (!Number.isSafeInteger(id) || id < 1) {
    zonaPilotos.innerHTML = '<p class="rc-error-bloque" role="alert">La escudería solicitada no es válida.</p>';
    zonaPilotos.setAttribute('aria-busy', 'false');
    return;
  }

  datos.innerHTML = `<dt>Nombre</dt><dd>${spinner()}</dd>`;
  zonaPilotos.innerHTML = spinner('Cargando pilotos…');

  try {
    const [escuderia, primera] = await Promise.all([
      api(`/equipos/${id}`),
      api('/pilotos', { params: { equipo_id: id, pagina: 1, por: 50, orden: 'nombre_completo' } }),
    ]);
    const paginas = Math.ceil(primera.total / primera.por_pagina);
    const adicionales = await Promise.all(Array.from({ length: paginas - 1 }, (_, indice) =>
      api('/pilotos', { params: { equipo_id: id, pagina: indice + 2, por: 50, orden: 'nombre_completo' } })));
    const pilotos = [...primera.datos, ...adicionales.flatMap((pagina) => pagina.datos)];

    $('#escuderia-titulo').textContent = escuderia.nombre;
    document.title = `${escuderia.nombre} · RaceControl`;
    datos.innerHTML = `<dt>Nombre</dt><dd>${esc(escuderia.nombre)}</dd>
      <dt>País</dt><dd>${esc(escuderia.pais)}</dd>
      <dt>Fundación</dt><dd>${esc(escuderia.anio_fundacion || '—')}</dd>`;
    datos.setAttribute('aria-busy', 'false');

    zonaPilotos.innerHTML = pilotos.length
      ? `<div class="table-responsive"><table class="table rc-tabla">
          <caption class="visually-hidden">Pilotos de ${esc(escuderia.nombre)}</caption>
          <thead><tr><th scope="col">Piloto</th><th scope="col">Documento</th><th scope="col">Nacionalidad</th></tr></thead>
          <tbody>${pilotos.map((piloto) => `<tr><th scope="row" class="rc-celda-principal">${esc(piloto.nombre_completo)}</th>
            <td>${esc(piloto.documento)}</td><td>${esc(piloto.nacionalidad)}</td></tr>`).join('')}</tbody>
        </table></div>`
      : '<p class="rc-texto-sec mb-0">Aún no hay pilotos asignados a esta escudería.</p>';
  } catch (err) {
    datos.innerHTML = '';
    zonaPilotos.innerHTML = `<p class="rc-error-bloque" role="alert">${esc(err.message)}</p>`;
  } finally {
    datos.setAttribute('aria-busy', 'false');
    zonaPilotos.setAttribute('aria-busy', 'false');
  }
}
