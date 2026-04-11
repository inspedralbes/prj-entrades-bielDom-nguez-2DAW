/**
 * Carrega l’API JavaScript de Google Maps una sola vegada (T048).
 */
export function useGoogleMapsLoader () {
  let loading;

  function load (apiKey) {
    if (!import.meta.client) {
      return Promise.reject(new Error('Maps només client'));
    }
    if (window.google?.maps) {
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
        const t = setInterval(() => {
          if (window.google?.maps) {
            clearInterval(t);
            resolve();
          }
        }, 50);
        return;
      }
      const s = document.createElement('script');
      s.id = id;
      s.async = true;
      s.defer = true;
      s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('No s’ha pogut carregar Google Maps'));
      document.head.appendChild(s);
    });

    return loading;
  }

  return { load };
}
