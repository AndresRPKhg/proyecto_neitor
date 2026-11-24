// Punto de entrada: estilos, fuentes, Bootstrap y arranque según la página.
import 'bootstrap/dist/css/bootstrap.min.css';
import '@fontsource/cinzel/500.css';
import '@fontsource/cinzel/700.css';
import '@fontsource/unifrakturcook/700.css';
import '@fontsource/eb-garamond/400.css';
import '@fontsource/eb-garamond/400-italic.css';
import '@fontsource/eb-garamond/600.css';
import '../css/theme.css';
import { RC, $, toast } from './core.js';
import { iniciarShell, aplicarPreferencias } from './shell.js';
import { iniciarAuth } from './auth.js';
import { montarTabla } from './tabla.js';

async function arrancar() {
  aplicarPreferencias();
  const pagina = RC.pagina;
  if (['login', 'recuperar', 'restablecer'].includes(pagina)) { iniciarAuth(); return; }
  if (!RC.usuario) return;
  iniciarShell();
  try {
    if (pagina === 'dashboard') (await import('./dashboard.js')).iniciarDashboard();
    else if (pagina === 'modulo') montarTabla($('#rc-tabla'), { recurso: RC.recurso });
    else if (pagina === 'escuderia') (await import('./escuderia.js')).iniciarEscuderia();
    else if (pagina === 'campeonato') (await import('./campeonato.js')).iniciarCampeonato();
    else if (pagina === 'reportes') (await import('./reportes.js')).iniciarReportes();
    else if (pagina === 'configuracion') (await import('./configuracion.js')).iniciarConfiguracion();
  } catch (err) {
    toast('error', err.message || 'No se pudo cargar la página.');
  }
}

arrancar();
