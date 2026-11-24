// Estructura común: sidebar (hover-expand, fijar, hamburguesa híbrida), preferencias, sesión y contraseña.
import { Modal } from 'bootstrap';
import { $, $$, RC, api, prefs, toast, irALogin, limpiarErrores, mostrarErrores } from './core.js';

const esEscritorio = () => window.matchMedia('(min-width: 992px)').matches;
const MODOS_COLOR = ['normal', 'protanopia', 'deuteranopia', 'tritanopia', 'acromatopsia'];

export function aplicarPreferencias() {
  document.body.classList.toggle('rc-sidebar-fijo', prefs.leer('sidebar-fijo') === '1');
  document.documentElement.classList.toggle('rc-sin-animaciones', prefs.leer('animaciones', '1') !== '1');
  const modoAnterior = document.documentElement.dataset.daltonismo;
  const modoGuardado = prefs.leer('daltonismo', 'normal');
  const modo = MODOS_COLOR.includes(modoGuardado) ? modoGuardado : 'normal';
  const contrasteAnterior = document.documentElement.dataset.altoContraste === 'true';
  const altoContraste = prefs.leer('alto-contraste') === '1';
  document.documentElement.dataset.daltonismo = modo;
  document.documentElement.dataset.altoContraste = String(altoContraste);
  if (modoAnterior !== modo) {
    document.dispatchEvent(new CustomEvent('rc:daltonismo-cambiado', { detail: { modo } }));
  }
  if (contrasteAnterior !== altoContraste) {
    document.dispatchEvent(new CustomEvent('rc:alto-contraste-cambiado', { detail: { activo: altoContraste } }));
  }
}

function iniciarSidebar() {
  const sidebar = $('#rc-sidebar');
  const boton = $('#rc-hamburguesa');
  const fondo = $('#rc-sidebar-fondo');
  const fijar = $('#rc-fijar');
  if (!sidebar) return;

  const sincronizar = () => {
    const fijo = document.body.classList.contains('rc-sidebar-fijo');
    const abierto = document.body.classList.contains('rc-sidebar-abierto');
    boton.setAttribute('aria-expanded', String(esEscritorio() ? fijo : abierto));
    fijar.setAttribute('aria-pressed', String(fijo));
    fondo.hidden = !abierto;
  };
  const cerrarMovil = () => { document.body.classList.remove('rc-sidebar-abierto'); sincronizar(); };
  const alternarFijo = () => {
    const fijo = !document.body.classList.contains('rc-sidebar-fijo');
    document.body.classList.toggle('rc-sidebar-fijo', fijo);
    prefs.guardar('sidebar-fijo', fijo ? '1' : '0');
    sincronizar();
  };

  // Híbrido: en escritorio solo fija o suelta el menú; en pantallas pequeñas lo abre como cajón.
  boton.addEventListener('click', () => {
    if (esEscritorio()) { alternarFijo(); return; }
    document.body.classList.toggle('rc-sidebar-abierto');
    sincronizar();
    if (document.body.classList.contains('rc-sidebar-abierto')) $('a, button', sidebar)?.focus();
  });
  fijar.addEventListener('click', alternarFijo);
  fondo.addEventListener('click', cerrarMovil);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && document.body.classList.contains('rc-sidebar-abierto')) { cerrarMovil(); boton.focus(); } });
  window.addEventListener('resize', cerrarMovil);
  sincronizar();
}

function iniciarPanelControl() {
  const filas = $('#pref-filas');
  const fijo = $('#pref-sidebar');
  const anim = $('#pref-animaciones');
  const daltonismo = $$('input[name="pref-daltonismo"]');
  const altoContraste = $('#pref-alto-contraste');
  if (!filas) return;
  const cargar = () => {
    filas.value = prefs.leer('filas', '10');
    fijo.checked = prefs.leer('sidebar-fijo') === '1';
    anim.checked = prefs.leer('animaciones', '1') === '1';
    const modoGuardado = prefs.leer('daltonismo', 'normal');
    const modo = MODOS_COLOR.includes(modoGuardado) ? modoGuardado : 'normal';
    daltonismo.forEach((opcion) => { opcion.checked = opcion.value === modo; });
    altoContraste.checked = prefs.leer('alto-contraste') === '1';
  };
  $('#rc-modal-panel').addEventListener('show.bs.modal', cargar);
  filas.addEventListener('change', () => prefs.guardar('filas', filas.value));
  fijo.addEventListener('change', () => { prefs.guardar('sidebar-fijo', fijo.checked ? '1' : '0'); aplicarPreferencias(); $('#rc-hamburguesa').setAttribute('aria-expanded', String(fijo.checked)); });
  anim.addEventListener('change', () => { prefs.guardar('animaciones', anim.checked ? '1' : '0'); aplicarPreferencias(); });
  daltonismo.forEach((opcion) => opcion.addEventListener('change', () => {
    if (opcion.checked) { prefs.guardar('daltonismo', opcion.value); aplicarPreferencias(); }
  }));
  altoContraste.addEventListener('change', () => {
    prefs.guardar('alto-contraste', altoContraste.checked ? '1' : '0');
    aplicarPreferencias();
  });
  $('#pref-restablecer').addEventListener('click', () => { ['filas', 'sidebar-fijo', 'animaciones', 'daltonismo', 'alto-contraste'].forEach((k) => prefs.borrar(k)); aplicarPreferencias(); cargar(); toast('exito', 'Preferencias restablecidas.'); });
}

function iniciarSesionUI() {
  $('#rc-salir')?.addEventListener('click', async () => {
    try { const r = await api('/auth/logout', { metodo: 'POST', cuerpo: {} }); window.location.href = r.destino; } catch { irALogin(); }
  });

  // Cambio de contraseña
  const form = $('#rc-form-clave');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    limpiarErrores(form);
    const datos = Object.fromEntries(new FormData(form));
    try {
      const r = await api('/auth/cambiar-clave', { metodo: 'POST', cuerpo: datos });
      Modal.getInstance($('#rc-modal-clave')).hide();
      form.reset();
      toast('exito', r.mensaje);
    } catch (err) {
      const primero = mostrarErrores(form, err.errores, 'clave-');
      if (primero) primero.focus(); else toast('error', err.message);
    }
  });
}

/** Cierre por inactividad (15 min) con aviso un minuto antes; la actividad renueva la sesión del servidor. */
function iniciarGuardaSesion() {
  const LIMITE = (RC.inactividad || 900) * 1000;
  const AVISO = LIMITE - 60000;
  let ultima = Date.now();
  let ultimoPing = Date.now();
  let aviso = false;
  const modalEl = $('#rc-modal-sesion');
  if (!modalEl) return;
  const modal = Modal.getOrCreateInstance(modalEl);

  const marcar = () => { if (!aviso) ultima = Date.now(); };
  ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach((ev) => window.addEventListener(ev, marcar, { passive: true }));

  const ping = async () => { ultimoPing = Date.now(); try { await api('/auth/ping', { metodo: 'POST', cuerpo: {} }); } catch { /* el siguiente ciclo lo reintenta */ } };
  const seguir = async () => { aviso = false; ultima = Date.now(); modal.hide(); await ping(); };
  $('#rc-sesion-seguir').addEventListener('click', seguir);
  $('#rc-sesion-salir').addEventListener('click', () => $('#rc-salir').click());

  setInterval(() => {
    const inactivo = Date.now() - ultima;
    if (inactivo >= LIMITE) { irALogin(); }
    else if (inactivo >= AVISO && !aviso) { aviso = true; modal.show(); }
    else if (!aviso && inactivo < 120000 && Date.now() - ultimoPing > 120000) { ping(); }
  }, 5000);
}

export function iniciarShell() {
  aplicarPreferencias();
  iniciarSidebar();
  iniciarPanelControl();
  iniciarSesionUI();
  iniciarGuardaSesion();
}
