//================================ NAMESPACES
import { computed } from 'vue';
import { useEventImage } from '~/composables/useEventImage';

//================================ FUNCIONS PÚBLIQUES

/**
 * Textos i enllaços de la targeta d’esdeveniment TR3 (reutilitzable des de EventCardTr3).
 */
export function useEventCardTr3 (props) {
  const { imageSrc, imageAlt } = useEventImage();

  const imgSrc = computed(() => {
    return imageSrc(props.event);
  });

  const imgAlt = computed(() => {
    return imageAlt(props.event);
  });

  const eventIdStr = computed(() => {
    return String(props.event.id);
  });

  function pathWithFromQuery (path) {
    const raw = props.linkFrom;
    if (raw === undefined || raw === null) {
      return path;
    }
    const s = String(raw).trim();
    if (s === '') {
      return path;
    }
    return `${path}?from=${encodeURIComponent(s)}`;
  }

  const detailHref = computed(() => {
    return pathWithFromQuery(`/events/${eventIdStr.value}`);
  });

  const seatsHref = computed(() => {
    return pathWithFromQuery(`/events/${eventIdStr.value}/seats`);
  });

  const kickerText = computed(() => {
    const ev = props.event;
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
    return `${cat} • ${place}`;
  });

  const priceText = computed(() => {
    const ev = props.event;
    const p = ev.price;
    if (p === null || p === undefined || p === '') {
      return '—';
    }
    const n = Number(p);
    if (Number.isNaN(n)) {
      return '—';
    }
    return `€${n.toFixed(2)}`;
  });

  const whenText = computed(() => {
    const iso = props.event.starts_at;
    if (!iso) {
      return '—';
    }
    try {
      const d = new Date(iso);
      const datePart = d.toLocaleDateString('ca-ES', { day: 'numeric', month: 'long' });
      const timePart = d.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' });
      return `${datePart} · ${timePart}`;
    } catch {
      return String(iso);
    }
  });

  const favAriaLabel = computed(() => {
    if (props.heartFilled) {
      return 'Treure dels guardats';
    }
    return 'Desar esdeveniment';
  });

  return {
    imgSrc,
    imgAlt,
    detailHref,
    seatsHref,
    kickerText,
    priceText,
    whenText,
    favAriaLabel,
  };
}
