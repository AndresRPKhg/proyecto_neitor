// Utilidades compartidas: datos del servidor, cliente de la API REST, toasts, confirmaciones y preferencias.
import { Modal, Toast } from 'bootstrap';

const nodoDatos = document.getElementById('rc-datos');
export const RC = nodoDatos ? JSON.parse(nodoDatos.textContent) : { base: '', csrf: '', usuario: null };

/** Escapa texto para insertarlo en innerHTML (protección contra XSS). */
export function esc(valor) {
  return String(valor ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export const $ = (selector, raiz = document) => raiz.querySelector(selector);
export const $$ = (selector, raiz = document) => [...raiz.querySelectorAll(selector)];

export function debounce(fn, ms = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

export function icono(nombre, clase = '') {
  return `<svg class="rc-icono ${clase}" aria-hidden="true" focusable="false"><use href="#i-${nombre}"></use></svg>`;
}

export const spinner = (texto = 'Cargando…') =>
  `<div class="rc-cargando" role="status"><span class="rc-spinner" aria-hidden="true"></span><span>${esc(texto)}</span></div>`;

// ---------- Preferencias de interfaz (localStorage: solo preferencias y borradores, nunca tokens) ----------
export const prefs = {
  leer(clave, defecto = null) {
    try { const v = localStorage.getItem('rc:' + clave); return v === null ? defecto : v; } catch { return defecto; }
  },
  guardar(clave, valor) {
    try { localStorage.setItem('rc:' + clave, String(valor)); } catch { /* almacenamiento no disponible */ }
  },
  borrar(clave) {
    try { localStorage.removeItem('rc:' + clave); } catch { /* nada */ }
  },
};

// ---------- Cliente de la API ----------
export class ErrorApi extends Error {
  constructor(mensaje, http, errores = {}) { super(mensaje); this.http = http; this.errores = errores; }
}

export async function api(ruta, { metodo = 'GET', cuerpo, params } = {}) {
  let url = `${RC.base}/api${ruta}`;
  if (params) {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== '') q.set(k, v); });
    if ([...q].length) url += `?${q}`;
  }
  const cabeceras = { Accept: 'application/json', 'X-CSRF-Token': RC.csrf };
  if (cuerpo !== undefined) cabeceras['Content-Type'] = 'application/json';
  let res;
  try {
    res = await fetch(url, { method: metodo, headers: cabeceras, credentials: 'same-origin', body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined });
  } catch {
    throw new ErrorApi('No hay conexión con el servidor. Revisa tu red e intenta de nuevo.', 0);
  }
  const datos = await res.json().catch(() => ({}));
  if (res.status === 401 && RC.usuario) {
    window.location.href = `${RC.base}/login`;
    throw new ErrorApi(datos.error || 'Sesión terminada.', 401);
  }
  if (!res.ok) throw new ErrorApi(datos.error || 'Ocurrió un error inesperado.', res.status, datos.errores || {});
  return datos;
}

export const puede = (permiso) => !!RC.usuario && RC.usuario.permisos.includes(permiso);

// ---------- Toasts (avisos rápidos, con aria-live) ----------
const TOAST = { exito: ['ok', 'Listo'], error: ['alerta', 'Error'], aviso: ['alerta', 'Aviso'] };

export function toast(tipo, mensaje) {
  const contenedor = $('#rc-toasts');
  if (!contenedor) return;
  const [ico, etiqueta] = TOAST[tipo] || TOAST.aviso;
  const el = document.createElement('div');
  el.className = `toast rc-toast rc-toast-${tipo}`;
  el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
  el.innerHTML = `<div class="toast-body">${icono(ico)}<div><strong>${esc(etiqueta)}.</strong> ${esc(mensaje)}</div>
    <button type="button" class="rc-btn-icono" data-bs-dismiss="toast" aria-label="Cerrar aviso">${icono('cerrar')}</button></div>`;
  contenedor.appendChild(el);
  const t = new Toast(el, { delay: tipo === 'error' ? 8000 : 5000 });
  el.addEventListener('hidden.bs.toast', () => el.remove());
  t.show();
}

/** Muestra errores de validación junto a cada campo. Devuelve el primer campo con error. */
export function mostrarErrores(raiz, errores, prefijo) {
  $$('.rc-error[data-campo]', raiz).forEach((e) => { e.textContent = ''; });
  let primero = null;
  Object.entries(errores || {}).forEach(([campo, msg]) => {
    const caja = raiz.querySelector(`#${prefijo}${campo}-error`) || raiz.querySelector(`.rc-error[data-campo="${campo}"]`);
    const input = raiz.querySelector(`#${prefijo}${campo}`);
    if (caja) caja.textContent = msg;
    if (input) { input.setAttribute('aria-invalid', 'true'); primero ??= input; }
  });
  return primero;
}

export function limpiarErrores(raiz) {
  $$('.rc-error', raiz).forEach((e) => { e.textContent = ''; });
  $$('[aria-invalid]', raiz).forEach((i) => i.removeAttribute('aria-invalid'));
}

// ---------- Confirmación de acciones destructivas ----------
export function confirmar({ titulo = 'Confirmar', mensaje, boton = 'Eliminar' }) {
  return new Promise((resolver) => {
    const el = $('#rc-modal-confirmar');
    const modal = Modal.getOrCreateInstance(el);
    $('#rc-confirmar-titulo').textContent = titulo;
    $('#rc-confirmar-mensaje').innerHTML = mensaje;
    const ok = $('#rc-confirmar-ok');
    ok.textContent = boton;
    let decidido = false;
    const alOk = () => { decidido = true; modal.hide(); resolver(true); };
    ok.addEventListener('click', alOk, { once: true });
    el.addEventListener('hidden.bs.modal', () => { ok.removeEventListener('click', alOk); if (!decidido) resolver(false); }, { once: true });
    modal.show();
  });
}

export function irALogin() { window.location.href = `${RC.base}/login`; }
