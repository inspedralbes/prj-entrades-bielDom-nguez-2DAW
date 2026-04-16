/**
 * Carrega l’API JavaScript de Google Maps una sola vegada (T048).
 */

/**
 * Amb `loading=async`, el `onload` del script pot arribar abans que `google.maps`
 * tingui enums com `ControlPosition` o `MapTypeId` (objecte parcial → errors al crear el mapa).
 */
function mapsJsApiReady () {
  const g = window.google;
  if (!g || !g.maps) {
    return false;
  }
  const m = g.maps;
  if (typeof m.Map !== 'function') {
    return false;
  }
  if (!m.ControlPosition) {
    return false;
  }
  if (m.ControlPosition.RIGHT_BOTTOM === undefined) {
    return false;
  }
  if (!m.MapTypeId) {
    return false;
  }
  if (m.MapTypeId.ROADMAP === undefined) {
    return false;
  }
  if (m.MapTypeId.HYBRID === undefined) {
    return false;
  }
  return true;
}

function waitUntilMapsJsApiReady (resolve, reject) {
  const started = Date.now();
  const maxMs = 20000;
  const step = function () {
    if (mapsJsApiReady()) {
      resolve();
      return;
    }
    if (Date.now() - started > maxMs) {
      reject(new Error('Timeout esperant Google Maps (API incompleta)'));
      return;
    }
    window.setTimeout(step, 50);
  };
  step();
}

export function useGoogleMapsLoader () {
  let loading;

  function load (apiKey) {
    if (!import.meta.client) {
      return Promise.reject(new Error('Maps només client'));
    }
    if (mapsJsApiReady()) {
      return Promise.resolve();
    }
    if (loading) {
      return loading;
    }
    const key = apiKey || '';
    if (!key) {
      return Promise.reject(new Error('Falta NUXT_PUBLIC_GOOGLE_MAPS_KEY'));
    }
    loading = new Promise((resolve, reject) => {
      const id = 'google-maps-js';
      if (document.getElementById(id)) {
        const started = Date.now();
        const maxMs = 20000;
        const t = setInterval(() => {
          if (mapsJsApiReady()) {
            clearInterval(t);
            resolve();
            return;
          }
          if (Date.now() - started > maxMs) {
            clearInterval(t);
            reject(new Error('Timeout esperant Google Maps (API incompleta)'));
          }
        }, 50);
        return;
      }
      const s = document.createElement('script');
      s.id = id;
      s.async = true;
      /* Paràmetre loading=async: patró recomanat per Google (evita avís «suboptimal performance»). */
      s.src =
        'https://maps.googleapis.com/maps/api/js?key=' +
        encodeURIComponent(key) +
        '&loading=async';
      s.onload = () => {
        waitUntilMapsJsApiReady(resolve, reject);
      };
      s.onerror = () => reject(new Error('No s’ha pogut carregar Google Maps'));
      document.head.appendChild(s);
    });

    return loading;
  }

  return { load };
}
