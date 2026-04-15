<template>
  <main class="tk-shell tk-list">
    <div class="tk-list__body">
      <header class="user-page-hero user-page-hero--spaced tk-list__page-head">
        <h1 class="user-page-title">
          Les meves entrades
        </h1>
        <p class="user-page-lead">
          Consulta i comparteix les entrades dels teus esdeveniments.
        </p>
      </header>
      <p v-if="error" class="tk-err">{{ error }}</p>
      <p v-else-if="loading" class="tk-muted">Carregant…</p>

      <template v-else>
        <p v-if="grouped.length === 0" class="tk-muted">Encara no tens cap entrada.</p>

        <div
          v-for="block in grouped"
          :key="block.eventKey"
          class="tk-block"
          :class="{ 'tk-block--fresh': isFreshHighlight(block) }"
          :id="block.eventId != null ? 'tk-event-' + String(block.eventId) : undefined"
        >
          <div class="tk-event-card">
            <div class="tk-event-card__hero">
              <img
                v-if="block.imageUrl"
                class="tk-event-card__img"
                :src="block.imageUrl"
                :alt="block.eventName"
                loading="lazy"
              >
              <div v-else class="tk-event-card__ph" aria-hidden="true" />
              <div class="tk-event-card__grad" />
              <div class="tk-event-card__head">
                <span class="tk-pill">ESDEVENIMENT</span>
                <h2 class="tk-event-card__title">{{ block.eventName }}</h2>
              </div>
            </div>
            <div class="tk-event-card__foot">
              <p class="tk-event-card__meta">{{ block.startsAt }} · {{ block.venueName }}</p>
              <span class="tk-event-card__count">{{ entradesLabel(block.items.length) }}</span>
            </div>

            <div class="tk-event-card__actions">
              <NuxtLink
                v-if="block.eventId != null"
                :to="{ path: '/events/' + String(block.eventId), query: { from: 'tickets' } }"
                class="tk-btn tk-btn--ghost"
              >
                <span class="material-symbols-outlined" aria-hidden="true">event</span>
                Veure esdeveniment
              </NuxtLink>
              <NuxtLink
                v-if="block.eventId != null"
                :to="'/tickets/event/' + String(block.eventId)"
                class="tk-btn tk-btn--primary"
              >
                <span class="material-symbols-outlined" aria-hidden="true">confirmation_number</span>
                Veure entrades
              </NuxtLink>
            </div>
          </div>
        </div>
      </template>
    </div>
  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useEventImage } from '~/composables/useEventImage';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const router = useRouter();
const { getJson } = useAuthorizedApi();
const { imageSrc } = useEventImage();

const loading = ref(true);
const error = ref('');
const tickets = ref([]);
/** Ressalt breu després de compra (?eventId=&new=1) */
const highlightEventId = ref(null);
let highlightClearTimer = null;

function entradesLabel (n) {
  if (n === 1) {
    return '1 entrada';
  }
  return String(n) + ' entrades';
}

function isFreshHighlight (block) {
  if (highlightEventId.value === null) {
    return false;
  }
  if (block.eventId == null) {
    return false;
  }
  return String(block.eventId) === String(highlightEventId.value);
}

async function scrollToFreshEventIfNeeded () {
  if (!import.meta.client) {
    return;
  }
  const id = highlightEventId.value;
  if (id === null) {
    return;
  }
  await nextTick();
  await nextTick();
  const el = document.getElementById('tk-event-' + id);
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  if (route.query.new === '1') {
    router.replace({ path: '/tickets' });
  }
  if (highlightClearTimer !== null) {
    clearTimeout(highlightClearTimer);
    highlightClearTimer = null;
  }
  highlightClearTimer = window.setTimeout(() => {
    highlightEventId.value = null;
    highlightClearTimer = null;
  }, 6500);
}

const grouped = computed(() => {
  const map = new Map();
  for (const t of tickets.value) {
    const ev = t.event;
    let key = 'unknown';
    if (ev?.id != null) {
      key = String(ev.id);
    }
    if (!map.has(key)) {
      map.set(key, {
        eventKey: key,
        eventId: ev?.id,
        eventName: ev?.name || 'Esdeveniment',
        startsAt: formatDate(ev?.starts_at),
        venueName: ev?.venue?.name || '',
        imageUrl: imageSrc(ev),
        items: [],
      });
    }
    const row = map.get(key);
    row.items.push(t);
  }
  const out = [];
  const keys = Array.from(map.keys());
  for (let i = 0; i < keys.length; i = i + 1) {
    out.push(map.get(keys[i]));
  }
  return out;
});

function formatDate (iso) {
  if (!iso) {
    return '';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES', {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  } catch {
    return iso;
  }
}

onMounted(async () => {
  if (route.query.new === '1' && route.query.eventId !== undefined && route.query.eventId !== null) {
    highlightEventId.value = String(route.query.eventId);
  }
  loading.value = true;
  error.value = '';
  try {
    const data = await getJson('/api/tickets');
    tickets.value = data.tickets || [];
  } catch (e) {
    if (e?.status === 401) {
      navigateTo('/login');
      return;
    }
    error.value = 'No s\'ha pogut carregar les entrades.';
    console.error(e);
  } finally {
    loading.value = false;
  }
  await scrollToFreshEventIfNeeded();
});

onUnmounted(() => {
  if (highlightClearTimer !== null) {
    clearTimeout(highlightClearTimer);
    highlightClearTimer = null;
  }
});
</script>

<style scoped>
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  font-size: 1.25rem;
  line-height: 1;
}
.tk-shell {
  --tk-bg: #131313;
  --tk-on-bg: #e5e2e1;
  --tk-yellow: #f7e628;
  --tk-yellow-text: #6e6600;
  --tk-outline: #959178;
  --tk-surface-high: #2a2a2a;
  --tk-outline-var: #4a4733;
  --tk-container-low: #1c1b1b;
  min-height: min(100dvh, 884px);
  background: var(--tk-bg);
  color: var(--tk-on-bg);
  font-family: Inter, system-ui, sans-serif;
  padding-bottom: calc(var(--footer-stack) + 1rem);
}

/* Mateix criteri que `.user-page` (app.css): aire superior + safe area com la resta de pàgines usuari */
.tk-list__body {
  box-sizing: border-box;
  padding: max(1.75rem, env(safe-area-inset-top, 0px)) 1rem 2rem;
  max-width: 28rem;
  margin: 0 auto;
}

@media (min-width: 900px) {
  .tk-list__body {
    padding: 2rem 2rem 2rem;
  }
}

.tk-list__page-head {
  text-align: left;
}

.tk-muted {
  color: var(--tk-outline);
}
.tk-err {
  color: #ffb4ab;
  margin: 0;
}
.tk-ok {
  color: #7bed9f;
  font-size: 0.9rem;
  margin: 0.5rem 0 0;
}

.tk-block {
  margin-bottom: 2.5rem;
  scroll-margin-top: 5rem;
}

.tk-block--fresh {
  animation: tk-block-fresh 1.8s ease-out 1;
  border-radius: 28px;
  box-shadow: 0 0 0 2px rgba(247, 230, 40, 0.45);
}

@keyframes tk-block-fresh {
  0% {
    box-shadow: 0 0 0 3px rgba(247, 230, 40, 0.65);
  }
  100% {
    box-shadow: 0 0 0 1px rgba(247, 230, 40, 0.2);
  }
}

.tk-event-card {
  display: block;
  text-decoration: none;
  color: inherit;
  border-radius: 24px;
  overflow: hidden;
  border: 1px solid rgba(74, 71, 51, 0.35);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
  margin-bottom: 1rem;
}
.tk-event-card__hero {
  position: relative;
  height: 10rem;
}
.tk-event-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: grayscale(1) contrast(1.1);
  opacity: 0.7;
}
.tk-event-card__ph {
  width: 100%;
  height: 100%;
  background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
}
.tk-event-card__grad {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, var(--tk-surface-high), transparent);
}
.tk-event-card__head {
  position: absolute;
  bottom: 1rem;
  left: 1rem;
  right: 1rem;
}
.tk-pill {
  display: inline-block;
  background: var(--tk-yellow);
  color: var(--tk-yellow-text);
  padding: 0.2rem 0.65rem;
  border-radius: 9999px;
  font-size: 10px;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 0.35rem;
}
.tk-event-card__title {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  font-size: 1.25rem;
  line-height: 1.1;
  letter-spacing: -0.02em;
  text-transform: uppercase;
  color: #fff;
}
.tk-event-card__foot {
  padding: 1rem 1.25rem;
  background: var(--tk-surface-high);
}
.tk-event-card__meta {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  color: var(--tk-outline);
}
.tk-event-card__count {
  display: inline-block;
  padding: 0.35rem 0.75rem;
  background: var(--tk-yellow);
  color: var(--tk-yellow-text);
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 900;
  font-family: Epilogue, system-ui, sans-serif;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.tk-event-card__actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.9rem;
  padding: 0 1.25rem 1.35rem;
  padding-top: 1rem;
}
.tk-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  min-height: 3.25rem;
  border-radius: 9999px;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  text-decoration: none;
  border: none;
  cursor: pointer;
  font-size: 0.8rem;
  letter-spacing: -0.02em;
  transition: transform 0.15s ease;
}
.tk-btn:active {
  transform: scale(0.98);
}
.tk-btn--primary {
  background: var(--tk-yellow);
  color: var(--tk-yellow-text);
}
.tk-btn--ghost {
  background: #1d1d1d;
  color: #fff;
  border: 1px solid rgba(74, 71, 51, 0.45);
}
.tk-btn--ghost:hover {
  background: #3a3939;
}
</style>
