(function () {
  var raiz = document.documentElement;
  var tema = 'dark';
  var modoDaltonismo = 'normal';
  var altoContraste = false;
  try {
    var guardado = localStorage.getItem('rc-tema');
    if (guardado === 'light' || guardado === 'dark') tema = guardado;
    var modoGuardado = localStorage.getItem('rc:daltonismo');
    if (['normal', 'protanopia', 'deuteranopia', 'tritanopia', 'acromatopsia'].indexOf(modoGuardado) !== -1) {
      modoDaltonismo = modoGuardado;
    }
    altoContraste = localStorage.getItem('rc:alto-contraste') === '1';
  } catch (e) {}
  raiz.setAttribute('data-daltonismo', modoDaltonismo);
  raiz.setAttribute('data-alto-contraste', String(altoContraste));

  function aplicarTema(nuevoTema, guardar) {
    tema = nuevoTema;
    raiz.setAttribute('data-bs-theme', tema);
    raiz.setAttribute('data-tema', tema);
    if (guardar) {
      try { localStorage.setItem('rc-tema', tema); } catch (e) {}
    }
    document.querySelectorAll('#rc-tema-btn').forEach(function (boton) {
      var claro = tema === 'dark';
      var icono = boton.querySelector('.rc-tema-icono');
      var texto = boton.querySelector('.rc-tema-texto');
      if (icono) icono.textContent = claro ? '☀' : '☾';
      if (texto) texto.textContent = claro ? 'Modo claro' : 'Modo oscuro';
      boton.setAttribute('aria-label', 'Cambiar al modo ' + (claro ? 'claro' : 'oscuro'));
      boton.setAttribute('title', 'Cambiar al modo ' + (claro ? 'claro' : 'oscuro'));
    });
    document.dispatchEvent(new CustomEvent('rc:tema-cambiado', { detail: { tema: tema } }));
  }

  aplicarTema(tema, false);
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#rc-tema-btn').forEach(function (boton) {
      boton.addEventListener('click', function () {
        aplicarTema(tema === 'dark' ? 'light' : 'dark', true);
      });
    });
    aplicarTema(tema, false);
  }, { once: true });
})();
