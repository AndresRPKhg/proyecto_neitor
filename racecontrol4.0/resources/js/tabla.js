// Componente genérico de catálogos: tabla (búsqueda, filtros, orden, paginación) y formulario modal con validación inline.
import { Modal } from 'bootstrap';
import { $, $$, RC, api, confirmar, debounce, esc, icono, limpiarErrores, mostrarErrores, prefs, puede, spinner, toast } from './core.js';

const ETIQUETAS_ENUM = {
  tipo_vehiculo: { auto: 'Auto', moto: 'Moto', mixto: 'Mixto (autos y motos)' },
  estado: { programada: 'Programada', finalizada: 'Finalizada' },
};

/** Texto de una celda según el tipo de dato. */
function celda(clave, valor) {
  if (valor === null || valor === undefined || valor === '') return '<span class="rc-texto-sec" aria-label="sin dato">—</span>';
  if (clave === 'activo') return Number(valor) ? 'Sí' : 'No';
  if (ETIQUETAS_ENUM[clave]) {
    const texto = ETIQUETAS_ENUM[clave][valor] || valor;
    return `<span class="rc-insignia rc-insignia-${esc(valor)}">${esc(texto)}</span>`;
  }
  return esc(valor);
}

const nombreDe = (f) => f.nombre_completo || f.nombre || [f.marca, f.modelo].filter(Boolean).join(' ') || f.usuario || `#${f.id}`;

const EXPLICACION_BORRADO = {
  campeonatos: 'Solo se pueden eliminar campeonatos sin carreras.',
  carreras: 'Se eliminarán también sus inscripciones y resultados, y se recalcularán las posiciones del campeonato.',
  usuarios: 'La cuenta se eliminará y esa persona ya no podrá iniciar sesión.',
  circuitos: 'No se puede eliminar un circuito que ya tiene carreras.',
};

/**
 * Monta una tabla de un recurso dentro de `contenedor`.
 * opciones: { recurso, filtrosFijos, valoresFijos, ocultarFiltros, botonNuevo }
 */
export async function montarTabla(contenedor, opciones) {
  const { recurso, filtrosFijos = {}, valoresFijos = {}, ocultarFiltros = [], botonNuevo = null } = opciones;
  contenedor.innerHTML = spinner();
  const meta = await api(`/${recurso}/meta`);
  const estado = { pagina: 1, por: Number(prefs.leer('filas', '10')), q: '', orden: meta.orden_defecto, dir: meta.orden_dir_defecto, filtros: {} };
  const puedeCrear = meta.permisos.crear === null || puede(meta.permisos.crear);
  const puedeEditar = meta.permisos.editar === null || puede(meta.permisos.editar);
  const puedeBorrar = meta.permisos.borrar === null || puede(meta.permisos.borrar);

  // ----- Barra de herramientas -----
  const filtrosVisibles = meta.filtros.filter((f) => !ocultarFiltros.includes(f) && !(f in filtrosFijos) && f !== 'temporada');
  const controlesFiltro = await Promise.all(filtrosVisibles.map(async (f) => {
    const campo = meta.campos[f];
    let opts = [];
    if (campo?.tipo === 'enum') opts = Object.entries(campo.opciones).map(([v, t]) => [v, t]);
    else if (campo?.tipo === 'ref') opts = (await api(`/${campo.ref}/opciones`)).map((o) => [o.id, o.etiqueta]);
    else if (ETIQUETAS_ENUM[f]) opts = Object.entries(ETIQUETAS_ENUM[f]);
    else return '';
    const etiqueta = campo?.etiqueta || (f === 'estado' ? 'Estado' : f);
    return `<div class="rc-filtro"><label class="form-label" for="flt-${f}">${esc(etiqueta)}</label>
      <select class="form-select" id="flt-${f}" data-filtro="${f}"><option value="">Todos</option>${opts.map(([v, t]) => `<option value="${esc(v)}">${esc(t)}</option>`).join('')}</select></div>`;
  }));

  contenedor.innerHTML = `
    <div class="rc-barra-tabla">
      <div class="rc-filtro rc-filtro-busqueda"><label class="form-label" for="tbl-buscar">Buscar</label>
        <div class="rc-busqueda">${icono('buscar')}<input class="form-control" type="search" id="tbl-buscar" placeholder="Escribe para buscar" autocomplete="off"></div></div>
      ${controlesFiltro.join('')}
      <div class="rc-filtro"><label class="form-label" for="tbl-por">Filas</label>
        <select class="form-select" id="tbl-por">${[10, 25, 50].map((n) => `<option ${n === estado.por ? 'selected' : ''}>${n}</option>`).join('')}</select></div>
    </div>
    <div class="rc-tarjeta rc-tarjeta-tabla"><div class="table-responsive" id="tbl-zona" aria-live="polite" aria-busy="true"></div>
      <nav class="rc-paginacion" aria-label="Paginación de ${esc(meta.etiqueta)}" id="tbl-paginacion"></nav></div>`;

  const zona = $('#tbl-zona', contenedor);
  const pag = $('#tbl-paginacion', contenedor);

  async function cargar() {
    zona.setAttribute('aria-busy', 'true');
    zona.innerHTML = spinner();
    try {
      const r = await api(`/${recurso}`, { params: { pagina: estado.pagina, por: estado.por, q: estado.q, orden: estado.orden, dir: estado.dir, ...filtrosFijos, ...estado.filtros } });
      estado.pagina = r.pagina;
      dibujar(r);
    } catch (err) {
      zona.innerHTML = `<p class="rc-error-bloque" role="alert">${esc(err.message)}</p>`;
    } finally {
      zona.setAttribute('aria-busy', 'false');
    }
  }

  function dibujar(r) {
    if (!r.datos.length) {
      const filtrando = estado.q || Object.values(estado.filtros).some(Boolean);
      zona.innerHTML = `<div class="rc-vacio">${icono('calavera', 'rc-vacio-icono')}
        <p class="rc-vacio-titulo">${filtrando ? 'No encontramos resultados' : `Aún no hay ${esc(meta.etiqueta.toLowerCase())}`}</p>
        <p class="rc-texto-sec">${filtrando ? 'Prueba con otra búsqueda o quita los filtros.' : `Registra el primero para empezar.`}</p>
        ${!filtrando && puedeCrear ? `<button type="button" class="btn rc-btn-primario" data-accion="nuevo">${icono('mas')} Registrar ${esc(meta.singular)}</button>` : ''}</div>`;
      pag.innerHTML = '';
      return;
    }
    const ordenable = (k) => meta.columnas.some((c) => c[0] === k);
    const cabeceras = meta.columnas.map(([k, t]) => {
      const activo = estado.orden === k;
      const aria = activo ? (estado.dir === 'asc' ? 'ascending' : 'descending') : 'none';
      return `<th scope="col" aria-sort="${aria}"><button type="button" class="rc-orden" data-orden="${k}">${esc(t)}<span class="rc-orden-flecha" aria-hidden="true">${activo ? (estado.dir === 'asc' ? '▲' : '▼') : '↕'}</span></button></th>`;
    }).join('');
    const filas = r.datos.map((f) => `<tr>${meta.columnas.map(([k], i) => `<td${i === 0 ? ' class="rc-celda-principal"' : ''}>${celda(k, f[k])}</td>`).join('')}
      <td class="rc-acciones">
        ${meta.enlace_detalle ? `<a class="btn rc-btn-secundario btn-sm" href="${RC.base}${meta.enlace_detalle}${f.id}" aria-label="Abrir ${esc(nombreDe(f))}">${icono('ojo')} Abrir</a>` : ''}
        ${puedeEditar ? `<button type="button" class="btn rc-btn-secundario btn-sm" data-accion="editar" data-id="${f.id}" aria-label="Editar ${esc(nombreDe(f))}">${icono('editar')} Editar</button>` : ''}
        ${puedeBorrar ? `<button type="button" class="btn rc-btn-peligro-borde btn-sm" data-accion="borrar" data-id="${f.id}" aria-label="Eliminar ${esc(nombreDe(f))}">${icono('basura')} Eliminar</button>` : ''}
      </td></tr>`).join('');
    zona.innerHTML = `<table class="table rc-tabla"><caption class="visually-hidden">${esc(meta.etiqueta)}</caption>
      <thead><tr>${cabeceras}<th scope="col"><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>${filas}</tbody></table>`;
    zona._datos = r.datos;
    dibujarPaginacion(r);
  }

  function dibujarPaginacion(r) {
    const total = Math.max(1, Math.ceil(r.total / r.por_pagina));
    const desde = (r.pagina - 1) * r.por_pagina + 1;
    const hasta = Math.min(r.total, r.pagina * r.por_pagina);
    const paginas = [];
    for (let i = Math.max(1, r.pagina - 2); i <= Math.min(total, r.pagina + 2); i++) paginas.push(i);
    pag.innerHTML = `<p class="rc-texto-sec mb-0">Mostrando ${desde}–${hasta} de ${r.total}</p>
      <ul class="pagination mb-0">
        <li class="page-item ${r.pagina <= 1 ? 'disabled' : ''}"><button class="page-link" data-pagina="${r.pagina - 1}" ${r.pagina <= 1 ? 'disabled' : ''}>Anterior</button></li>
        ${paginas.map((n) => `<li class="page-item ${n === r.pagina ? 'active' : ''}"><button class="page-link" data-pagina="${n}" ${n === r.pagina ? 'aria-current="page"' : ''} aria-label="Página ${n}">${n}</button></li>`).join('')}
        <li class="page-item ${r.pagina >= total ? 'disabled' : ''}"><button class="page-link" data-pagina="${r.pagina + 1}" ${r.pagina >= total ? 'disabled' : ''}>Siguiente</button></li>
      </ul>`;
  }

  async function nuevo() {
    abrirFormulario({ meta, valoresFijos, alGuardar: () => { estado.pagina = 1; cargar(); opciones.alCambiar?.(); } });
  }

  // ----- Eventos -----
  $('#tbl-buscar', contenedor).addEventListener('input', debounce((e) => { estado.q = e.target.value; estado.pagina = 1; cargar(); }));
  $('#tbl-por', contenedor).addEventListener('change', (e) => { estado.por = Number(e.target.value); estado.pagina = 1; cargar(); });
  $$('[data-filtro]', contenedor).forEach((s) => s.addEventListener('change', () => { estado.filtros[s.dataset.filtro] = s.value; estado.pagina = 1; cargar(); }));
  contenedor.addEventListener('click', async (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.dataset.pagina) { estado.pagina = Number(b.dataset.pagina); cargar(); }
    else if (b.dataset.orden) {
      estado.dir = estado.orden === b.dataset.orden && estado.dir === 'asc' ? 'desc' : 'asc';
      estado.orden = b.dataset.orden; estado.pagina = 1; cargar();
    } else if (b.dataset.accion === 'nuevo') nuevo();
    else if (b.dataset.accion === 'editar') {
      const fila = zona._datos.find((f) => String(f.id) === b.dataset.id);
      abrirFormulario({ meta, registro: fila, valoresFijos, alGuardar: () => { cargar(); opciones.alCambiar?.(); } });
    } else if (b.dataset.accion === 'borrar') {
      const fila = zona._datos.find((f) => String(f.id) === b.dataset.id);
      const borrado = meta.nombre && ['pilotos', 'equipos', 'vehiculos'].includes(recurso);
      const detalle = EXPLICACION_BORRADO[recurso] || (borrado ? 'Se ocultará del sistema y se conservará su historial de carreras.' : 'Esta acción no se puede deshacer.');
      const ok = await confirmar({ titulo: `Eliminar ${meta.singular}`, mensaje: `Vas a eliminar <strong>${esc(nombreDe(fila))}</strong>.<br><span class="rc-texto-sec">${esc(detalle)}</span>` });
      if (!ok) return;
      try { await api(`/${recurso}/${fila.id}`, { metodo: 'DELETE' }); toast('exito', `${nombreDe(fila)} fue eliminado.`); cargar(); opciones.alCambiar?.(); } catch (err) { toast('error', err.message); }
    }
  });

  const botonExterno = botonNuevo ? $(botonNuevo) : $('#rc-nuevo');
  if (botonExterno && puedeCrear) { botonExterno.hidden = false; botonExterno.addEventListener('click', nuevo); }
  await cargar();
}

// ---------- Formulario modal (crear / editar) ----------
export async function abrirFormulario({ meta, registro = null, valoresFijos = {}, alGuardar }) {
  const el = $('#rc-modal-form');
  const form = $('#rc-form');
  const cuerpo = $('#rc-form-cuerpo');
  const editando = !!registro;
  $('#rc-modal-form-titulo').textContent = `${editando ? 'Editar' : 'Nuevo'} ${meta.singular}`;
  cuerpo.innerHTML = spinner();
  const modal = Modal.getOrCreateInstance(el);
  modal.show();

  // Opciones de las listas desplegables y valores por defecto
  let puntosDefecto = null;
  if (!editando && meta.campos.puntos) puntosDefecto = (await api('/configuracion')).puntos_por_defecto;
  const campos = Object.entries(meta.campos).filter(([k]) => !(k in valoresFijos));
  const refs = {};
  await Promise.all(campos.filter(([, c]) => c.tipo === 'ref').map(async ([k, c]) => {
    const dependencia = c.filtro_por ? registro?.[c.filtro_por] ?? valoresFijos[c.filtro_por] : null;
    if (c.filtro_por && !dependencia) { refs[k] = []; return; }
    refs[k] = await api(`/${c.ref}/opciones`, { params: c.filtro_por ? { [c.filtro_por]: dependencia } : undefined });
  }));

  const borradorClave = `borrador:${meta.nombre}`;
  const html = campos.map(([k, c]) => {
    const id = `f-${k}`;
    const valor = registro ? (k === 'contrasena' ? '' : registro[k]) : (k === 'puntos' ? puntosDefecto : c.defecto ?? '');
    const req = c.requerido && c.tipo !== 'password' || (c.tipo === 'password' && !editando);
    const desc = `${id}-error${c.ayuda ? ` ${id}-ayuda` : ''}`;
    let control;
    if (c.tipo === 'enum') control = `<select class="form-select" id="${id}" name="${k}" ${req ? 'required' : ''} aria-describedby="${desc}"><option value="">Elige una opción</option>${Object.entries(c.opciones).map(([v, t]) => `<option value="${esc(v)}" ${String(valor) === v ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select>`;
    else if (c.tipo === 'ref') control = `<select class="form-select" id="${id}" name="${k}" ${req ? 'required' : ''} aria-describedby="${desc}"><option value="">${c.requerido ? 'Elige una opción' : 'Sin asignar'}</option>${refs[k].map((o) => `<option value="${o.id}" ${String(valor) === String(o.id) ? 'selected' : ''}>${esc(o.etiqueta)}</option>`).join('')}</select>`;
    else if (c.tipo === 'booleano') return `<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="${id}" name="${k}" ${Number(valor) ? 'checked' : ''}><label class="form-check-label" for="${id}">${esc(c.etiqueta)}</label></div>`;
    else {
      const tipo = { entero: 'number', decimal: 'number', fecha: 'date', email: 'email', password: 'password' }[c.tipo] || 'text';
      const extra = c.tipo === 'decimal' ? 'step="any"' : c.tipo === 'entero' ? 'step="1"' : '';
      const auto = c.tipo === 'password' ? 'autocomplete="new-password"' : 'autocomplete="off"';
      control = `<input class="form-control" type="${tipo}" id="${id}" name="${k}" value="${esc(valor ?? '')}" ${extra} ${auto} ${c.max && tipo === 'text' ? `maxlength="${c.max}"` : ''} ${req ? 'required' : ''} aria-describedby="${desc}">`;
    }
    return `<div class="mb-3"><label class="form-label" for="${id}">${esc(c.etiqueta)}${req ? ' <span class="rc-requerido" aria-hidden="true">*</span>' : ''}</label>${control}
      ${c.ayuda ? `<div class="form-text rc-texto-sec" id="${id}-ayuda">${esc(c.ayuda)}</div>` : ''}<div class="rc-error" id="${id}-error" role="alert"></div></div>`;
  }).join('');
  cuerpo.innerHTML = `<div id="f-borrador"></div>${html}<p class="rc-texto-sec mb-0"><span class="rc-requerido" aria-hidden="true">*</span> Campo obligatorio</p>`;

  if (meta.nombre === 'carreras') {
    const campeonato = $('#f-campeonato_id', form);
    const tipo = $('#f-tipo_vehiculo', form);
    let solicitudTipo = 0;
    const actualizarTiposCarrera = async () => {
      const campeonatoId = valoresFijos.campeonato_id || campeonato?.value;
      const solicitud = ++solicitudTipo;
      if (!campeonatoId) {
        tipo.disabled = true;
        tipo.innerHTML = '<option value="">Elige primero un campeonato</option>';
        return;
      }
      tipo.disabled = true;
      try {
        const datos = await api(`/campeonatos/${campeonatoId}`);
        if (solicitud !== solicitudTipo) return;
        const opciones = datos.tipo_vehiculo === 'mixto'
          ? [['auto', 'Autos'], ['moto', 'Motos'], ['mixto', 'Mixto (autos y motos)']]
          : [[datos.tipo_vehiculo, datos.tipo_vehiculo === 'auto' ? 'Autos' : 'Motos'], ['mixto', 'Mixto (autos y motos)']];
        const valor = tipo.value;
        tipo.innerHTML = `<option value="">Elige una opción</option>${opciones.map(([v, texto]) => `<option value="${v}">${esc(texto)}</option>`).join('')}`;
        tipo.value = opciones.some(([v]) => v === valor) ? valor : (datos.tipo_vehiculo === 'mixto' ? '' : datos.tipo_vehiculo);
        tipo.disabled = false;
      } catch (err) {
        if (solicitud === solicitudTipo) toast('error', err.message);
      }
    };
    campeonato?.addEventListener('change', actualizarTiposCarrera);
    actualizarTiposCarrera();
  }

  campos.filter(([, c]) => c.tipo === 'ref' && c.filtro_por).forEach(([k, c]) => {
    const dependencia = $(`#f-${c.filtro_por}`, form);
    const destino = $(`#f-${k}`, form);
    if (!dependencia || !destino) return;
    let solicitud = 0;
    dependencia.addEventListener('change', async () => {
      const actual = ++solicitud;
      const valor = dependencia.value;
      destino.value = '';
      destino.innerHTML = `<option value="">${c.requerido ? 'Elige una opción' : 'Sin asignar'}</option>`;
      if (!valor) return;
      try {
        const opciones = await api(`/${c.ref}/opciones`, { params: { [c.filtro_por]: valor } });
        if (actual !== solicitud || dependencia.value !== valor) return;
        destino.insertAdjacentHTML('beforeend', opciones.map((o) => `<option value="${o.id}">${esc(o.etiqueta)}</option>`).join(''));
      } catch (err) {
        toast('error', err.message);
      }
    });
  });

  // Borrador local de formularios nuevos (nunca se guardan contraseñas)
  const leerForm = () => { const d = {}; campos.forEach(([k, c]) => { const i = $(`#f-${k}`, form); if (!i) return; d[k] = c.tipo === 'booleano' ? (i.checked ? 1 : 0) : i.value; }); return d; };
  if (!editando) {
    const guardado = prefs.leer(borradorClave);
    if (guardado) {
      $('#f-borrador', cuerpo).innerHTML = `<div class="rc-aviso" role="status">Tienes un borrador sin guardar.
        <button type="button" class="btn rc-btn-secundario btn-sm" id="f-restaurar">Restaurar</button>
        <button type="button" class="btn rc-btn-secundario btn-sm" id="f-descartar">Descartar</button></div>`;
      $('#f-restaurar', cuerpo).onclick = () => {
        const d = JSON.parse(guardado);
        Object.entries(d).forEach(([k, v]) => { const i = $(`#f-${k}`, form); if (i) { i.type === 'checkbox' ? (i.checked = !!Number(v)) : (i.value = v); } });
        if (meta.nombre === 'carreras') $('#f-campeonato_id', form)?.dispatchEvent(new Event('change'));
        $('#f-borrador', cuerpo).innerHTML = '';
      };
      $('#f-descartar', cuerpo).onclick = () => { prefs.borrar(borradorClave); $('#f-borrador', cuerpo).innerHTML = ''; };
    }
    form.oninput = debounce(() => { const d = leerForm(); delete d.contrasena; prefs.guardar(borradorClave, JSON.stringify(d)); }, 500);
  } else form.oninput = null;

  form.onsubmit = async (ev) => {
    ev.preventDefault();
    limpiarErrores(form);
    const datos = { ...leerForm(), ...valoresFijos };
    const errores = validarCliente(meta, campos, datos, editando);
    if (Object.keys(errores).length) { mostrarErrores(form, errores, 'f-')?.focus(); return; }
    const boton = $('#rc-form-guardar');
    boton.disabled = true;
    try {
      await api(editando ? `/${meta.nombre}/${registro.id}` : `/${meta.nombre}`, { metodo: editando ? 'PUT' : 'POST', cuerpo: datos });
      prefs.borrar(borradorClave);
      modal.hide();
      toast('exito', `${editando ? 'Cambios guardados' : 'Registro creado'} correctamente.`);
      alGuardar?.();
    } catch (err) {
      const primero = mostrarErrores(form, err.errores, 'f-');
      if (primero) primero.focus(); else toast('error', err.message);
    } finally { boton.disabled = false; }
  };
  setTimeout(() => cuerpo.querySelector('input:not([type=hidden]), select')?.focus(), 250);
}

/** Validación en el cliente (el servidor vuelve a validar todo). */
function validarCliente(meta, campos, datos, editando) {
  const e = {};
  campos.forEach(([k, c]) => {
    const v = String(datos[k] ?? '').trim();
    if (c.tipo === 'booleano') return;
    if (!v) { if ((c.requerido && c.tipo !== 'password') || (c.tipo === 'password' && !editando)) e[k] = 'Este campo es obligatorio.'; return; }
    if (c.max && c.tipo === 'texto' && v.length > c.max) e[k] = `Máximo ${c.max} caracteres.`;
    if ((c.tipo === 'entero' || c.tipo === 'decimal') && (Number.isNaN(Number(v)) || (c.min !== undefined && Number(v) < c.min) || (c.max !== undefined && Number(v) > c.max))) e[k] = `Escribe un número entre ${c.min} y ${c.max}.`;
    if (c.tipo === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) e[k] = 'Escribe un correo válido.';
    if (c.tipo === 'puntos' && !/^\d+(\s*,\s*\d+)*$/.test(v)) e[k] = 'Usa números separados por coma. Ejemplo: 25,18,15.';
    if (c.tipo === 'password' && v.length < 12) e[k] = 'Debe tener al menos 12 caracteres.';
  });
  return e;
}
