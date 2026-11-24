(function () {
  var datos = JSON.parse(document.getElementById('rc-datos').textContent);
  var puedeEditar = ((datos.usuario || {}).permisos || []).indexOf('catalogo.editar') !== -1;
  var tabla = document.getElementById('rc-tabla');
  if (!tabla) return;

  function inyectar() {
    tabla.querySelectorAll('button[data-accion="editar"],button[data-accion="borrar"]').forEach(function (b) { b.dataset.rcx = '1'; });
    tabla.querySelectorAll('td.rc-acciones').forEach(function (td) {
      if (td.querySelector('[data-mapa]')) return;
      var any = td.querySelector('[data-id]');
      var fila = td.closest('tr');
      var id = any ? any.dataset.id : (fila && fila.dataset.id);
      if (!id) return;
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'btn rc-btn-secundario btn-sm'; b.dataset.mapa = id;
      b.textContent = '🗺 Mapa';
      td.insertBefore(b, td.firstChild);
    });
  }
  new MutationObserver(inyectar).observe(tabla, { childList: true, subtree: true });
  inyectar();

  tabla.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mapa]');
    if (b) abrir(b.dataset.mapa, b.closest('tr'));
  });

  function cab(extra) { var h = { 'X-CSRF-Token': datos.csrf, Accept: 'application/json' }; return Object.assign(h, extra || {}); }

  function abrir(id, fila) {
    var nombre = fila ? (fila.querySelector('.rc-celda-principal') || {}).textContent || 'Circuito' : 'Circuito';
    var km = fila && fila.children[3] ? fila.children[3].textContent : '';
    var fondo = document.createElement('div');
    fondo.className = 'rc-mapa-fondo'; fondo.setAttribute('role', 'dialog'); fondo.setAttribute('aria-modal', 'true');
    var caja = document.createElement('div'); caja.className = 'rc-mapa-caja'; fondo.appendChild(caja);
    var h = document.createElement('h2'); h.textContent = 'Mapa · ' + nombre; h.style.fontSize = '1.3rem'; caja.appendChild(h);
    var sub = document.createElement('p'); sub.className = 'rc-texto-sec'; sub.textContent = km ? 'Longitud: ' + km + ' km' : ''; caja.appendChild(sub);
    var zona = document.createElement('div'); caja.appendChild(zona);
    var acc = document.createElement('div'); acc.className = 'rc-mapa-acc'; caja.appendChild(acc);
    var msg = document.createElement('div'); msg.className = 'rc-error'; msg.setAttribute('role', 'alert'); caja.appendChild(msg);

    function cerrar() { fondo.remove(); document.removeEventListener('keydown', esc); }
    function esc(ev) { if (ev.key === 'Escape') cerrar(); }
    document.addEventListener('keydown', esc);
    fondo.addEventListener('click', function (ev) { if (ev.target === fondo) cerrar(); });

    function btn(txt, cls, fn) { var x = document.createElement('button'); x.type = 'button'; x.className = 'btn ' + cls; x.textContent = txt; x.addEventListener('click', fn); return x; }

    function cargar() {
      zona.textContent = 'Cargando…'; acc.textContent = ''; msg.textContent = '';
      var img = new Image();
      img.alt = 'Mapa del circuito ' + nombre; img.className = 'rc-mapa-img';
      img.onload = function () { zona.textContent = ''; zona.appendChild(img); botones(true); };
      img.onerror = function () {
        zona.innerHTML = ''; var v = document.createElement('div'); v.className = 'rc-mapa-vacio';
        v.textContent = puedeEditar ? 'Este circuito aún no tiene mapa. Sube una imagen PNG, JPG o WEBP.' : 'Este circuito aún no tiene mapa.';
        zona.appendChild(v); botones(false);
      };
      img.src = datos.base + '/api/circuitos/' + id + '/mapa?v=' + Date.now();
    }

    function botones(tiene) {
      acc.textContent = '';
      if (puedeEditar) {
        var f = document.createElement('input'); f.type = 'file'; f.accept = 'image/png,image/jpeg,image/webp'; f.className = 'form-control'; f.style.maxWidth = '280px';
        f.setAttribute('aria-label', 'Archivo de imagen del mapa');
        acc.appendChild(f);
        acc.appendChild(btn(tiene ? 'Reemplazar mapa' : 'Subir mapa', 'rc-btn-primario', async function () {
          msg.textContent = '';
          if (!f.files[0]) { msg.textContent = 'Selecciona una imagen.'; return; }
          var fd = new FormData(); fd.append('mapa', f.files[0]);
          var r = await fetch(datos.base + '/api/circuitos/' + id + '/mapa', { method: 'POST', credentials: 'same-origin', headers: cab(), body: fd });
          var j = await r.json().catch(function () { return {}; });
          if (r.ok) cargar(); else msg.textContent = (j.errores && j.errores.mapa) || j.error || 'No se pudo subir.';
        }));
        if (tiene) acc.appendChild(btn('Quitar mapa', 'rc-btn-peligro-borde', async function () {
          if (!confirm('¿Quitar el mapa de este circuito?')) return;
          var r = await fetch(datos.base + '/api/circuitos/' + id + '/mapa', { method: 'DELETE', credentials: 'same-origin', headers: cab() });
          if (r.ok) cargar(); else msg.textContent = 'No se pudo quitar.';
        }));
      }
      acc.appendChild(btn('Cerrar', 'rc-btn-secundario ms-auto', cerrar));
    }
    document.body.appendChild(fondo);
    cargar();
  }
})();
