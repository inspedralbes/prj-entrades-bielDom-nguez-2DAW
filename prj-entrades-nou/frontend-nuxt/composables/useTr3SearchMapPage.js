//================================ NAMESPACES
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useApi } from '~/composables/useApi';
import { useGoogleMapsLoader } from '~/composables/useGoogleMapsLoader';
import { useEventImage } from '~/composables/useEventImage';
import { buildTr3EventMarkerDataUrl } from '~/utils/tr3MapMarkerIcon';
import { buildTr3GoogleMapOptions } from '~/utils/tr3MapOptions';

//================================ FUNCIONS PÚBLIQUES

/**
 * Pàgina de cerca al mapa: query sync amb la ruta, càrrega d’esdeveniments,
 * Google Maps, markers i InfoWindow.
 */
export function useTr3SearchMapPage () {
  // A. Dependències Vue i API
  const route = useRoute();
  const config = useRuntimeConfig();
  const { fetchApi } = useApi();
  const { load } = useGoogleMapsLoader();
  const { imageSrc, imageAlt } = useEventImage();

  const mapEl = ref(null);

  const loading = ref(true);
  const mapReady = ref(false);
  const mapError = ref('');
  const events = ref([]);

  let map = null;
  const markers = [];
  let infoWindow = null;

  const listSearchHref = computed(() => {
    const params = new URLSearchParams();
    const rq = route.query;
    if (rq.q) {
      params.set('q', String(rq.q));
    }
    if (rq.category) {
      params.set('category', String(rq.category));
    }
    if (rq.lat) {
      params.set('lat', String(rq.lat));
    }
    if (rq.lng) {
      params.set('lng', String(rq.lng));
    }
    const qs = params.toString();
    if (qs === '') {
      return '/search';
    }
    return `/search?${qs}`;
  });

  const hasGeoEvents = computed(() => {
    const list = events.value;
    for (let i = 0; i < list.length; i++) {
      const ev = list[i];
      const rawLat = ev.map_lat;
      const rawLng = ev.map_lng;
      if (rawLat == null || rawLng == null) {
        continue;
      }
      const lat = Number(rawLat);
      const lng = Number(rawLng);
      if (Number.isNaN(lat) || Number.isNaN(lng)) {
        continue;
      }
      return true;
    }
    return false;
  });

  // B. Helpers de missatge d’error (sense ternari)
  function errorMessageFrom (e, fallback) {
    if (e !== null && e !== undefined && typeof e.message === 'string' && e.message !== '') {
      return e.message;
    }
    return fallback;
  }

  function buildSearchEventsPath () {
    const params = new URLSearchParams();
    const rq = route.query;
    if (rq.q) {
      params.set('q', String(rq.q));
    }
    if (rq.category) {
      params.set('category', String(rq.category));
    }
    if (rq.lat) {
      params.set('lat', String(rq.lat));
    }
    if (rq.lng) {
      params.set('lng', String(rq.lng));
    }
    const qs = params.toString();
    if (qs === '') {
      return '/api/search/events';
    }
    return `/api/search/events?${qs}`;
  }

  async function loadEvents () {
    const path = buildSearchEventsPath();
    const data = await fetchApi(path);
    events.value = data.events || [];
  }

  function escapeInfoHtml (raw) {
    if (typeof raw !== 'string') {
      return '';
    }
    let out = '';
    for (let i = 0; i < raw.length; i++) {
      const c = raw.charAt(i);
      if (c === '&') {
        out += '&amp;';
        continue;
      }
      if (c === '<') {
        out += '&lt;';
        continue;
      }
      if (c === '>') {
        out += '&gt;';
        continue;
      }
      if (c === '"') {
        out += '&quot;';
        continue;
      }
      out += c;
    }
    return out;
  }

  function formatPrice (ev) {
    const p = ev.price;
    if (p === null || p === undefined || p === '') {
      return '—';
    }
    const n = Number(p);
    if (Number.isNaN(n)) {
      return '—';
    }
    return `€${n.toFixed(2)}`;
  }

  function formatEventWhen (iso) {
    if (!iso) {
      return '—';
    }
    try {
      const d = new Date(iso);
      const datePart = d.toLocaleDateString('ca-ES', { dateStyle: 'medium' });
      const timePart = d.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' });
      return `${datePart} · ${timePart}`;
    } catch {
      return '—';
    }
  }

  function buildKickerEsc (ev) {
    let cat = 'ESDEVENIMENT';
    if (ev.category && String(ev.category).trim() !== '') {
      cat = String(ev.category).trim().toUpperCase();
    }
    let place = '—';
    if (ev.venue) {
      const city = ev.venue.city;
      if (city && String(city).trim() !== '') {
        place = String(city).trim().toUpperCase();
      } else if (ev.venue.name && String(ev.venue.name).trim() !== '') {
        place = String(ev.venue.name).trim().toUpperCase();
      }
    }
    const raw = `${cat} • ${place}`;
    return escapeInfoHtml(raw);
  }

  function buildInfoWindowHtml (ev) {
    const nameEsc = escapeInfoHtml(ev.name);
    const kickerEsc = buildKickerEsc(ev);
    const priceEsc = escapeInfoHtml(formatPrice(ev));
    const whenEsc = escapeInfoHtml(formatEventWhen(ev.starts_at));
    const imgUrl = imageSrc(ev);
    const closeBtn =
      `<button type="button" class="map-tr3-iw__close" title="Tancar" aria-label="Tancar">` +
      `<span class="material-symbols-outlined map-tr3-iw__close-ico" aria-hidden="true">close</span>` +
      `</button>`;
    let mediaInner = '';
    if (imgUrl) {
      const srcEsc = escapeInfoHtml(imgUrl);
      const altEsc = escapeInfoHtml(imageAlt(ev));
      mediaInner =
        `${closeBtn}` +
        `<img class="map-tr3-iw__img" src="${srcEsc}" alt="${altEsc}" loading="lazy" decoding="async" width="176" height="120"/>`;
    } else {
      mediaInner = `${closeBtn}<div class="map-tr3-iw__ph" aria-hidden="true">Sense imatge</div>`;
    }
    return (
      `<div class="map-tr3-iw">` +
      `<div class="map-tr3-iw__media-wrap">` +
      mediaInner +
      `</div>` +
      `<div class="map-tr3-iw__body">` +
      `<div class="map-tr3-iw__row-kicker">` +
      `<span class="map-tr3-iw__kicker">${kickerEsc}</span>` +
      `<span class="map-tr3-iw__price">${priceEsc}</span>` +
      `</div>` +
      `<h3 class="map-tr3-iw__title">${nameEsc}</h3>` +
      `<p class="map-tr3-iw__when">${whenEsc}</p>` +
      `<div class="map-tr3-iw__action-wrap">` +
      `<a class="map-tr3-iw__a" href="/events/${ev.id}?from=search">Veure detall</a>` +
      `</div>` +
      `</div></div>`
    );
  }

  function bindInfoWindowCloseOnce () {
    if (!infoWindow || !window.google || !window.google.maps) {
      return;
    }
    window.google.maps.event.addListenerOnce(infoWindow, 'domready', () => {
      const btn = document.querySelector('.map-tr3-iw__close');
      if (!btn) {
        return;
      }
      btn.addEventListener('click', (evt) => {
        evt.preventDefault();
        evt.stopPropagation();
        infoWindow.close();
      });
    });
  }

  function openInfoForEvent (ev, pos) {
    infoWindow.setContent(buildInfoWindowHtml(ev));
    infoWindow.setPosition(pos);
    infoWindow.open(map);
    bindInfoWindowCloseOnce();
  }

  function clearMarkers () {
    for (let i = 0; i < markers.length; i++) {
      markers[i].setMap(null);
    }
    markers.length = 0;
  }

  function fitMapToMarkers () {
    if (!map) {
      return;
    }
    if (markers.length === 0) {
      return;
    }

    const latQ = route.query.lat;
    const lngQ = route.query.lng;
    if (latQ && lngQ) {
      const la = parseFloat(String(latQ));
      const ln = parseFloat(String(lngQ));
      if (!isNaN(la) && !isNaN(ln)) {
        map.setCenter({ lat: la, lng: ln });
        map.setZoom(12);
        return;
      }
    }

    const bounds = new window.google.maps.LatLngBounds();
    for (let i = 0; i < markers.length; i++) {
      bounds.extend(markers[i].getPosition());
    }
    if (bounds.isEmpty()) {
      return;
    }
    map.fitBounds(bounds, 48);
  }

  async function renderMarkers () {
    clearMarkers();
    if (infoWindow) {
      infoWindow.close();
    }

    const g = window.google;
    for (let i = 0; i < events.value.length; i++) {
      const ev = events.value[i];
      const rawLat = ev.map_lat;
      const rawLng = ev.map_lng;
      if (rawLat == null || rawLng == null) {
        continue;
      }
      const lat = Number(rawLat);
      const lng = Number(rawLng);
      if (Number.isNaN(lat) || Number.isNaN(lng)) {
        continue;
      }
      const pos = { lat, lng };
      const url = buildTr3EventMarkerDataUrl(ev.name);
      const marker = new g.maps.Marker({
        position: pos,
        map,
        title: ev.name,
        icon: {
          url,
          scaledSize: new g.maps.Size(112, 52),
          anchor: new g.maps.Point(56, 50),
        },
      });
      marker.addListener('click', () => {
        openInfoForEvent(ev, pos);
        map.panTo(pos);
      });
      markers.push(marker);
    }

    fitMapToMarkers();
  }

  async function initMap () {
    const key = config.public.googleMapsKey;
    await load(key);

    const latParam = parseFloat(String(route.query.lat || ''));
    const lngParam = parseFloat(String(route.query.lng || ''));
    let center = { lat: 40.4168, lng: -3.7038 };
    let zoom = 6;
    if (!isNaN(latParam) && !isNaN(lngParam)) {
      center = { lat: latParam, lng: lngParam };
      zoom = 12;
    }

    const opts = buildTr3GoogleMapOptions(center, zoom, { variant: 'searchMonochrome' });
    map = new window.google.maps.Map(mapEl.value, opts);

    infoWindow = new window.google.maps.InfoWindow();

    await renderMarkers();
    mapReady.value = true;
  }

  watch(
    () => route.query,
    async () => {
      loading.value = true;
      mapError.value = '';
      try {
        await loadEvents();
        if (map) {
          await renderMarkers();
          await nextTick();
          if (window.google && window.google.maps) {
            window.google.maps.event.trigger(map, 'resize');
          }
        }
      } catch (e) {
        mapError.value = errorMessageFrom(e, 'Error en recarregar esdeveniments.');
        console.error(e);
      } finally {
        loading.value = false;
      }
    },
    { deep: true },
  );

  function lockBodyScroll () {
    if (typeof document === 'undefined') {
      return;
    }
    document.documentElement.classList.add('tr3-map-page-scroll-lock');
  }

  function unlockBodyScroll () {
    if (typeof document === 'undefined') {
      return;
    }
    document.documentElement.classList.remove('tr3-map-page-scroll-lock');
  }

  onMounted(async () => {
    lockBodyScroll();
    loading.value = true;
    mapError.value = '';
    try {
      await loadEvents();
      await initMap();
      await nextTick();
      if (map && window.google && window.google.maps) {
        window.google.maps.event.trigger(map, 'resize');
      }
    } catch (e) {
      mapError.value = errorMessageFrom(e, 'No s\'ha pogut inicialitzar el mapa (revisa la clau Maps).');
      console.error(e);
    } finally {
      loading.value = false;
    }
  });

  onUnmounted(() => {
    unlockBodyScroll();
    clearMarkers();
    if (infoWindow) {
      infoWindow.close();
    }
    map = null;
    mapReady.value = false;
  });

  return {
    mapEl,
    loading,
    mapReady,
    mapError,
    events,
    listSearchHref,
    hasGeoEvents,
  };
}
