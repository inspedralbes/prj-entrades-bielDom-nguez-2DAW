<template>
  <article class="ec3">
    <div class="ec3__inner">
      <div class="ec3__media">
        <div
          class="ec3__img-wrap"
          :class="{ 'ec3__img-wrap--empty': !imgSrc }"
        >
          <img
            v-if="imgSrc"
            class="ec3__img"
            :src="imgSrc"
            :alt="imgAlt"
            loading="lazy"
            decoding="async"
            width="384"
            height="384"
          >
        </div>
        <button
          v-if="showHeart"
          type="button"
          class="ec3__fav"
          :aria-pressed="heartFilled"
          :aria-label="heartFilled ? 'Treure dels guardats' : 'Desar esdeveniment'"
          @click.prevent="onFavClick"
        >
          <span
            class="material-symbols-outlined ec3__fav-ico"
            :class="{ 'ec3__fav-ico--fill': heartFilled }"
            aria-hidden="true"
          >favorite</span>
        </button>
      </div>

      <div class="ec3__body">
        <div class="ec3__top">
          <div class="ec3__row-kicker">
            <span class="ec3__kicker">{{ kickerText }}</span>
            <span class="ec3__price">{{ priceText }}</span>
          </div>
          <h3 class="ec3__title">
            {{ event.name }}
          </h3>
          <div class="ec3__when">
            <span class="material-symbols-outlined ec3__when-ico" aria-hidden="true">calendar_today</span>
            <span class="ec3__when-txt">{{ whenText }}</span>
          </div>
        </div>
        <div class="ec3__actions">
          <NuxtLink
            :to="detailHref"
            class="ec3__btn ec3__btn--ghost"
          >
            Detalls
          </NuxtLink>
          <NuxtLink
            :to="seatsHref"
            class="ec3__btn ec3__btn--primary"
          >
            Comprar
          </NuxtLink>
        </div>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { useEventImage } from '~/composables/useEventImage';

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  showHeart: {
    type: Boolean,
    default: false,
  },
  heartFilled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['favorite-click']);

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

const detailHref = computed(() => {
  return `/events/${eventIdStr.value}`;
});

const seatsHref = computed(() => {
  return `/events/${eventIdStr.value}/seats`;
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

function onFavClick () {
  emit('favorite-click');
}
</script>

<style scoped>
/* Targeta esdeveniment TR3 (mock: horitzontal imatge + cos, vores suaus, botons Detalls / Comprar) */
.ec3 {
  position: relative;
  box-sizing: border-box;
  overflow: hidden;
  border-radius: 1rem;
  border: 1px solid rgba(74, 71, 51, 0.1);
  background: #1c1b1b;
  transition:
    border-color 0.3s ease,
    box-shadow 0.3s ease;
}

.ec3:hover {
  border-color: rgba(247, 230, 40, 0.3);
}

.ec3__inner {
  display: flex;
  flex-direction: column;
}

@media (min-width: 768px) {
  .ec3__inner {
    flex-direction: row;
  }
}

.ec3__media {
  position: relative;
  flex-shrink: 0;
  width: 100%;
  height: 12rem;
  overflow: hidden;
}

@media (min-width: 768px) {
  .ec3__media {
    width: 12rem;
    height: 12rem;
  }
}

.ec3__img-wrap {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: #222;
}

.ec3__img-wrap--empty {
  background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
}

.ec3__img-wrap--empty::after {
  content: 'Sense imatge';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  color: #666;
}

.ec3__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.5s ease;
}

.ec3:hover .ec3__img {
  transform: scale(1.05);
}

.ec3__fav {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 9999px;
  background: rgba(19, 19, 19, 0.6);
  backdrop-filter: blur(8px);
  cursor: pointer;
  color: #ccc7ac;
  transition: color 0.2s ease, background 0.2s ease;
}

.ec3__fav:hover {
  background: rgba(19, 19, 19, 0.85);
  color: #ff2d55;
}

.ec3__fav[aria-pressed='true'] {
  color: #ff2d55;
}

.ec3__fav-ico {
  font-size: 1.35rem;
  line-height: 1;
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.ec3__fav-ico--fill {
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.ec3__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 1.5rem;
  min-width: 0;
}

.ec3__row-kicker {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.ec3__kicker {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(247, 230, 40, 0.8);
}

.ec3__price {
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  font-size: 1.125rem;
  color: #f7e628;
  flex-shrink: 0;
}

.ec3__title {
  margin: 0 0 0.5rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.02em;
  color: #fff;
  word-break: break-word;
}

.ec3__when {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #ccc7ac;
}

.ec3__when-ico {
  font-size: 1rem;
  line-height: 1;
}

.ec3__when-txt {
  font-size: 0.875rem;
  font-weight: 500;
}

.ec3__actions {
  display: flex;
  flex-direction: row;
  gap: 1rem;
  margin-top: 1rem;
}

.ec3__btn {
  flex: 1;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  padding: 0.625rem 1rem;
  border-radius: 9999px;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-decoration: none;
  text-align: center;
  cursor: pointer;
  transition:
    background 0.2s ease,
    opacity 0.2s ease,
    border-color 0.2s ease;
}

.ec3__btn--ghost {
  background: #353534;
  color: #fff;
  border: 1px solid rgba(74, 71, 51, 0.2);
}

.ec3__btn--ghost:hover {
  background: #3a3939;
}

.ec3__btn--primary {
  background: #f7e628;
  color: #000;
  border: none;
}

.ec3__btn--primary:hover {
  opacity: 0.92;
}
</style>
