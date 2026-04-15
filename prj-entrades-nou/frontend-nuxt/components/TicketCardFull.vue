<template>
  <div class="tk-ticket-card">
    <div class="tk-card">
      <div class="tk-card__hero">
        <img
          v-if="heroImageUrl"
          class="tk-card__hero-img"
          :src="heroImageUrl"
          :alt="heroImageAlt"
          loading="lazy"
        >
        <div v-else class="tk-card__hero-placeholder" aria-hidden="true" />
        <div class="tk-card__hero-grad" />
        <div class="tk-card__hero-text">
          <span class="tk-pill">{{ eventBadge }}</span>
          <component
            :is="headingIsH1 ? 'h1' : 'h2'"
            class="tk-card__title"
          >
            {{ ticket.event?.name || 'Esdeveniment' }}
          </component>
        </div>
      </div>

      <div class="tk-card__body">
        <div class="tk-grid2">
          <div class="tk-field">
            <p class="tk-field__label">Venue</p>
            <p class="tk-field__val">{{ venueLine }}</p>
          </div>
          <div class="tk-field">
            <p class="tk-field__label">Data i hora</p>
            <p class="tk-field__val">{{ heroDateLine }}</p>
          </div>
          <div class="tk-field tk-field--wide">
            <p class="tk-field__label">La teva assignació</p>
            <p class="tk-assign">{{ seatLine }}</p>
          </div>
        </div>

        <div class="tk-tear" aria-hidden="true">
          <span class="tk-tear__hole tk-tear__hole--l" />
          <span class="tk-tear__hole tk-tear__hole--r" />
          <div class="tk-tear__line" />
        </div>

        <div class="tk-qr-block">
          <div v-if="displayStatusNorm === 'venuda' && qrSvg" class="tk-qr-wrap" v-html="qrSvg" />
          <p v-else-if="displayStatusNorm === 'venuda' && qrError" class="tk-qr-err">{{ qrError }}</p>
          <div v-else-if="displayStatusNorm === 'utilitzada'" class="tk-used">
            <span class="tk-used__ico material-symbols-outlined" aria-hidden="true">cancel</span>
            <p class="tk-used__txt">Aquesta entrada ja s’ha utilitzat; el QR no és vàlid.</p>
          </div>

          <div v-if="displayStatusNorm === 'venuda'" class="tk-valid">
            <span class="material-symbols-outlined tk-valid__ico" aria-hidden="true">check_circle</span>
            <span class="tk-valid__txt">Vàlida per a l’entrada</span>
          </div>
          <p class="tk-idline">ID: {{ publicTicketId }}</p>
        </div>
      </div>

      <div class="tk-card__actions">
        <button
          v-if="displayStatusNorm === 'venuda'"
          type="button"
          class="tk-btn tk-btn--primary"
          @click="onTransferClick"
        >
          <span class="material-symbols-outlined" aria-hidden="true">send</span>
          Enviar a un amic
        </button>
        <NuxtLink
          v-if="eventLinkTo !== ''"
          :to="eventLinkTo"
          class="tk-btn tk-btn--ghost"
        >
          <span class="material-symbols-outlined" aria-hidden="true">event</span>
          Veure esdeveniment
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useEventImage } from '~/composables/useEventImage';

const props = defineProps({
  ticket: {
    type: Object,
    required: true,
  },
  qrSvg: {
    type: String,
    default: '',
  },
  qrError: {
    type: String,
    default: '',
  },
  displayStatus: {
    type: String,
    required: true,
  },
  headingIsH1: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['transfer']);

const { imageSrc, imageAlt } = useEventImage();

/** Estat segur per a la UI (l’API pot enviar valors inesperats o Pinia pot deixar buit). */
const displayStatusNorm = computed(() => {
  const s = props.displayStatus;
  if (s === 'utilitzada') {
    return 'utilitzada';
  }
  return 'venuda';
});

const heroImageUrl = computed(() => {
  return imageSrc(props.ticket?.event);
});

const heroImageAlt = computed(() => {
  return imageAlt(props.ticket?.event);
});

const venueLine = computed(() => {
  const n = props.ticket?.event?.venue?.name;
  if (typeof n === 'string' && n.trim() !== '') {
    return n.trim().toUpperCase();
  }
  return '—';
});

const heroDateLine = computed(() => {
  const iso = props.ticket?.event?.starts_at;
  if (!iso) {
    return '—';
  }
  try {
    const d = new Date(iso);
    const day = d.getDate();
    const mon = d.toLocaleString('ca-ES', { month: 'short' });
    const monUp = mon.charAt(0).toUpperCase() + mon.slice(1);
    const time = d.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' });
    return String(day) + ' ' + monUp + ' — ' + time;
  } catch {
    return '—';
  }
});

const seatLine = computed(() => {
  const t = props.ticket;
  if (!t) {
    return '—';
  }
  const label = t.seat?.label;
  const key = t.seat?.key;
  let s = '';
  if (typeof label === 'string' && label.trim() !== '') {
    s = label.trim();
  } else if (typeof key === 'string' && key.trim() !== '') {
    s = key.trim();
  } else {
    return '—';
  }
  return s.toUpperCase();
});

const eventBadge = computed(() => {
  const name = props.ticket?.event?.name;
  if (typeof name === 'string' && name.toLowerCase().indexOf('concert') >= 0) {
    return 'LIVE CONCERT';
  }
  return 'ESDEVENIMENT';
});

const eventLinkTo = computed(() => {
  const id = props.ticket?.event?.id;
  if (id === undefined || id === null) {
    return '';
  }
  return { path: '/events/' + String(id), query: { from: 'tickets' } };
});

const publicTicketId = computed(() => {
  const raw = props.ticket?.id;
  if (raw === undefined || raw === null) {
    return '—';
  }
  const id = String(raw);
  if (id.length < 8) {
    return '—';
  }
  const parts = id.split('-');
  if (parts.length >= 4) {
    const a = parts[0].slice(0, 3).toUpperCase();
    const b = parts[1].slice(0, 3).toUpperCase();
    const c = parts[2].slice(0, 2).toUpperCase();
    return 'TR3-' + a + '-' + b + '-' + c;
  }
  return 'TR3-' + id.slice(0, 12).toUpperCase();
});

function onTransferClick () {
  emit('transfer');
}
</script>

<style scoped>
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  font-size: 1.35rem;
  line-height: 1;
}

.tk-valid .tk-valid__ico {
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.tk-ticket-card {
  --tk-bg: #131313;
  --tk-on-bg: #e5e2e1;
  --tk-yellow: #f7e628;
  --tk-yellow-text: #6e6600;
  --tk-outline: #959178;
  --tk-surface-high: #2a2a2a;
  --tk-surface-highest: #353534;
  --tk-outline-var: #4a4733;
  --tk-container-low: #1c1b1b;
  color: var(--tk-on-bg);
  font-family: Inter, system-ui, sans-serif;
}

.tk-card {
  background: var(--tk-surface-high);
  border-radius: 24px;
  overflow: hidden;
  border: 1px solid rgba(74, 71, 51, 0.35);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.tk-card__hero {
  position: relative;
  height: 12rem;
  width: 100%;
}

.tk-card__hero-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: grayscale(1) contrast(1.15);
  opacity: 0.65;
}

.tk-card__hero-placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
}

.tk-card__hero-grad {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, var(--tk-surface-high), transparent);
}

.tk-card__hero-text {
  position: absolute;
  bottom: 1.5rem;
  left: 1.5rem;
  right: 1.5rem;
}

.tk-pill {
  display: inline-block;
  background: var(--tk-yellow);
  color: var(--tk-yellow-text);
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 10px;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
}

.tk-card__title {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  font-size: 1.75rem;
  line-height: 1;
  letter-spacing: -0.02em;
  text-transform: uppercase;
  color: #fff;
}

.tk-card__body {
  padding: 1.5rem;
}

.tk-grid2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.tk-field__label {
  margin: 0 0 0.25rem;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--tk-outline);
}

.tk-field__val {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: #fff;
}

.tk-field--wide {
  grid-column: 1 / -1;
  padding: 1rem;
  background: var(--tk-container-low);
  border-radius: 0.5rem;
  border: 1px solid rgba(74, 71, 51, 0.2);
}

.tk-assign {
  margin: 0;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--tk-yellow);
}

.tk-tear {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem 0;
}

.tk-tear__hole {
  position: absolute;
  top: 50%;
  width: 1.5rem;
  height: 3rem;
  margin-top: -1.5rem;
  background: var(--tk-bg);
  border-radius: 9999px;
}

.tk-tear__hole--l {
  left: -0.75rem;
}

.tk-tear__hole--r {
  right: -0.75rem;
}

.tk-tear__line {
  width: 100%;
  border-top: 1px dashed rgba(74, 71, 51, 0.45);
}

.tk-qr-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  padding-top: 0.5rem;
}

.tk-qr-wrap {
  background: #fff;
  padding: 1.5rem;
  border-radius: 0.5rem;
  box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.06);
  display: flex;
  justify-content: center;
  align-items: center;
}

.tk-qr-wrap :deep(svg) {
  width: 12rem;
  height: 12rem;
  max-width: 100%;
  height: auto;
  shape-rendering: crispEdges;
}

.tk-qr-err {
  margin: 0;
  font-size: 0.9rem;
  color: #ffb4ab;
  text-align: center;
}

.tk-used {
  text-align: center;
  padding: 1rem;
}

.tk-used__ico {
  font-size: 2rem;
  color: #c0392b;
}

.tk-used__txt {
  margin: 0.5rem 0 0;
  font-size: 0.9rem;
  color: var(--tk-outline);
}

.tk-valid {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: rgba(46, 125, 50, 0.12);
  border: 1px solid rgba(76, 175, 80, 0.35);
  border-radius: 9999px;
}

.tk-valid__ico {
  color: #4caf50;
  font-size: 1rem;
}

.tk-valid__txt {
  font-size: 10px;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #4caf50;
}

.tk-idline {
  margin: 0;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  color: var(--tk-outline);
}

.tk-card__actions {
  background: var(--tk-surface-highest);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.tk-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  width: 100%;
  min-height: 3.5rem;
  border-radius: 9999px;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  letter-spacing: -0.02em;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.tk-btn:active {
  transform: scale(0.97);
}

.tk-btn--primary {
  background: var(--tk-yellow);
  color: var(--tk-yellow-text);
}

.tk-btn--ghost {
  background: var(--tk-bg);
  color: #fff;
  border: 1px solid rgba(74, 71, 51, 0.35);
}

.tk-btn--ghost:hover {
  background: #3a3939;
}
</style>
