<template>
  <main class="tk-shell tk-list">
    <header class="tk-bar tk-bar--list" aria-label="Les meves entrades">
      <div class="tk-bar__inner">
        <NuxtLink to="/" class="tk-bar__icon-btn" aria-label="Tornar a l’inici">
          <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
        </NuxtLink>
        <p class="tk-bar__brand">Les meves entrades</p>
        <span class="tk-bar__balance" aria-hidden="true" />
      </div>
    </header>

    <div class="tk-list__body">
      <p v-if="error" class="tk-err">{{ error }}</p>
      <p v-else-if="loading" class="tk-muted">Carregant…</p>

      <template v-else>
        <p v-if="grouped.length === 0" class="tk-muted">Encara no tens cap entrada.</p>

        <div v-for="block in grouped" :key="block.eventKey" class="tk-block">
          <NuxtLink :to="`/events/${block.eventId}`" class="tk-event-card">
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
          </NuxtLink>

          <div class="tk-swiper">
            <div class="tk-swiper__track" ref="swiperContainer">
              <div
                v-for="t in block.items"
                :key="t.id"
                class="tk-swiper__slide"
                @click="selectTicket(t)"
              >
                <div class="tk-mini" :class="{ 'tk-mini--on': selectedTicket?.id === t.id }">
                  <div class="tk-mini__row">
                    <span class="tk-mini__id">Entrada #{{ t.id.slice(0, 8) }}</span>
                    <span
                      class="tk-mini__st"
                      :data-status="t.displayStatus"
                    >{{ labelStatus(t.displayStatus) }}</span>
                  </div>
                  <p class="tk-mini__hint">Toca per veure el QR i les accions</p>
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedTicket && selectedBlockKey === block.eventKey" class="tk-panel">
            <p class="tk-panel__ev">{{ block.eventName }}</p>
            <p class="tk-panel__date">{{ block.startsAt }}</p>
            <p v-if="selectedTicket.seat && (selectedTicket.seat.label || selectedTicket.seat.key)" class="tk-panel__seat">
              Ubicació: {{ selectedTicket.seat.label || selectedTicket.seat.key }}
            </p>

            <div v-if="selectedTicket.displayStatus === 'venuda' && qrPreview" class="tk-panel__qr" v-html="qrPreview" />
            <p v-else-if="selectedTicket.displayStatus === 'venuda' && qrPreviewErr" class="tk-err">{{ qrPreviewErr }}</p>
            <p v-else-if="selectedTicket.displayStatus === 'utilitzada'" class="tk-muted">
              Entrada utilitzada; el QR ja no és vàlid.
            </p>

            <div v-if="selectedTicket.displayStatus === 'venuda'" class="tk-panel__valid">
              <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
              <span>Vàlida per a l’entrada</span>
            </div>
            <p class="tk-panel__tid">ID: {{ publicIdFor(selectedTicket.id) }}</p>

            <div class="tk-panel__actions">
              <button
                v-if="selectedTicket.displayStatus === 'venuda'"
                type="button"
                class="tk-btn tk-btn--primary"
                @click="openTransfer(selectedTicket)"
              >
                <span class="material-symbols-outlined" aria-hidden="true">send</span>
                Enviar a un amic
              </button>
              <NuxtLink
                v-if="block.eventId != null"
                :to="`/events/${block.eventId}`"
                class="tk-btn tk-btn--ghost"
              >
                <span class="material-symbols-outlined" aria-hidden="true">event</span>
                Veure esdeveniment
              </NuxtLink>
            </div>
          </div>
        </div>
      </template>
    </div>

    <div
      v-if="transferOpen"
      class="tk-modal-bg"
      role="dialog"
      aria-modal="true"
      aria-labelledby="tk-list-transfer-title"
      @click.self="closeTransfer"
    >
      <div class="tk-modal">
        <h2 id="tk-list-transfer-title" class="tk-modal__title">Enviar entrada</h2>
        <p class="tk-muted">
          Només a un amic amb invitació acceptada. Cerca per nom o usuari.
        </p>
        <div class="tk-search">
          <span class="tk-search__ico" aria-hidden="true">⌕</span>
          <input
            v-model="transferFriendQuery"
            type="search"
            class="tk-search__input"
            placeholder="Cercar amic…"
            @input="scheduleTransferFriendSearch"
          >
        </div>
        <ul v-if="transferFriendsLoading" class="tk-friends">
          <li class="tk-muted">Carregant…</li>
        </ul>
        <ul v-else class="tk-friends">
          <li v-for="f in transferFriends" :key="f.id">
            <button type="button" class="tk-friend-btn" @click="pickTransferFriend(f)">
              @{{ f.username }} · {{ f.name }}
            </button>
          </li>
        </ul>
        <p v-if="transferFriends.length === 0 && !transferFriendsLoading" class="tk-muted">
          Cap amic coincideix.
        </p>
        <p v-if="transferSelectedLabel" class="tk-pick">Destinatari: {{ transferSelectedLabel }}</p>
        <p v-if="transferErr" class="tk-err">{{ transferErr }}</p>
        <p v-if="transferOk" class="tk-ok">{{ transferOk }}</p>
        <div class="tk-modal__actions">
          <button type="button" class="tk-btn-sec" @click="closeTransfer">
            Cancel·lar
          </button>
          <button
            type="button"
            class="tk-btn-prim"
            :disabled="transferLoading"
            @click="submitTransfer"
          >
            {{ transferLoading ? 'Enviant…' : 'Confirmar' }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useEventImage } from '~/composables/useEventImage';
import { usePrivateTicketSocket } from '~/composables/usePrivateTicketSocket';
import { useTicketsStore } from '~/stores/tickets';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const { getJson, postJson, getTicketQrSvg } = useAuthorizedApi();
const { imageSrc } = useEventImage();
const ticketsStore = useTicketsStore();
usePrivateTicketSocket();

const loading = ref(true);
const error = ref('');
const tickets = ref([]);
const selectedTicket = ref(null);
const swiperContainer = ref(null);

const qrPreview = ref('');
const qrPreviewErr = ref('');

const transferOpen = ref(false);
const transferTicket = ref(null);
const transferUserId = ref(null);
const transferSelectedLabel = ref('');
const transferFriendQuery = ref('');
const transferFriends = ref([]);
const transferFriendsLoading = ref(false);
let transferSearchTimer = null;
const transferLoading = ref(false);
const transferErr = ref('');
const transferOk = ref('');

function entradesLabel (n) {
  if (n === 1) {
    return '1 entrada';
  }
  return String(n) + ' entrades';
}

function publicIdFor (id) {
  if (typeof id !== 'string' || id.length < 8) {
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
}

const selectedBlockKey = computed(() => {
  const t = selectedTicket.value;
  if (!t || !t.event) {
    return '';
  }
  const id = t.event.id;
  if (id == null) {
    return 'unknown';
  }
  return String(id);
});

watch(selectedTicket, async (t) => {
  qrPreview.value = '';
  qrPreviewErr.value = '';
  if (!t) {
    return;
  }
  if (t.displayStatus !== 'venuda') {
    return;
  }
  try {
    qrPreview.value = await getTicketQrSvg(t.id);
  } catch (e) {
    console.error(e);
    qrPreviewErr.value = 'No s’ha pogut carregar el QR.';
  }
});

function selectTicket (t) {
  selectedTicket.value = selectedTicket.value?.id === t.id ? null : t;
}

function openTransfer (t) {
  transferTicket.value = t;
  transferUserId.value = null;
  transferSelectedLabel.value = '';
  transferFriendQuery.value = '';
  transferErr.value = '';
  transferOk.value = '';
  transferOpen.value = true;
  loadTransferFriends();
}

function scheduleTransferFriendSearch () {
  if (transferSearchTimer !== null) {
    clearTimeout(transferSearchTimer);
  }
  transferSearchTimer = setTimeout(() => {
    loadTransferFriends();
  }, 350);
}

async function loadTransferFriends () {
  if (!transferOpen.value) {
    return;
  }
  transferFriendsLoading.value = true;
  try {
    const q = transferFriendQuery.value.trim();
    let path = '/api/social/friends';
    if (q !== '') {
      path = path + '?q=' + encodeURIComponent(q);
    }
    const res = await getJson(path);
    transferFriends.value = res.friends || [];
  } catch (e) {
    console.error(e);
    transferFriends.value = [];
  } finally {
    transferFriendsLoading.value = false;
  }
}

function pickTransferFriend (f) {
  transferUserId.value = f.id;
  transferSelectedLabel.value = '@' + f.username + ' · ' + f.name;
}

function closeTransfer () {
  transferOpen.value = false;
  transferTicket.value = null;
}

async function submitTransfer () {
  if (!transferTicket.value || !transferUserId.value) {
    transferErr.value = 'Selecciona un amic de la llista.';
    return;
  }
  transferLoading.value = true;
  transferErr.value = '';
  transferOk.value = '';
  try {
    await postJson('/api/tickets/' + transferTicket.value.id + '/transfer', {
      to_user_id: transferUserId.value,
    });
    transferOk.value = 'Entrada enviada. El QR anterior deixa de ser vàlid.';
    const data = await getJson('/api/tickets');
    tickets.value = data.tickets || [];
    qrPreview.value = '';
    qrPreviewErr.value = '';
    selectedTicket.value = null;
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:notifications-updated'));
    }
    setTimeout(() => closeTransfer(), 1200);
  } catch (e) {
    transferErr.value = e?.data?.message || e?.message || 'No s\'ha pogut transferir.';
  } finally {
    transferLoading.value = false;
  }
}

function labelStatus (s) {
  if (s === 'venuda') {
    return 'Vàlida';
  }
  if (s === 'utilitzada') {
    return 'Utilitzada';
  }
  return s || '—';
}

function statusFor (t) {
  return ticketsStore.effectiveStatus(t.id, t.status);
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
    row.items.push({
      ...t,
      displayStatus: statusFor(t),
    });
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
});
</script>

<style scoped>
.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  font-size: 1.25rem;
  line-height: 1;
}
.tk-panel__valid .material-symbols-outlined {
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  color: #4caf50;
  font-size: 1rem;
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
  padding-bottom: calc(56px + 1rem);
}

.tk-bar {
  position: fixed;
  top: var(--header-h, 56px);
  left: 0;
  right: 0;
  z-index: 45;
  height: 4rem;
  background: #131313;
  border-bottom: 1px solid rgba(255, 238, 50, 0.2);
}
.tk-bar__inner {
  max-width: 80rem;
  margin: 0 auto;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.5rem;
}
.tk-bar__icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: transparent;
  color: #ffee32;
  text-decoration: none;
  transition: background 0.2s ease, transform 0.15s ease;
}
.tk-bar__icon-btn:hover {
  background: #2a2a2a;
}
.tk-bar__icon-btn:active {
  transform: scale(0.95);
}
.tk-bar__brand {
  margin: 0;
  flex: 1;
  text-align: center;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  font-size: 1.1rem;
  color: #ffee32;
}
.tk-bar__balance {
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
}

.tk-list__body {
  padding: calc(var(--header-h, 56px) + 4rem + 1rem) 1.5rem 2rem;
  max-width: 28rem;
  margin: 0 auto;
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

.tk-swiper {
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  margin: 0 -1.5rem;
  padding: 0 1rem 0.5rem;
}
.tk-swiper__track {
  display: flex;
  gap: 0.75rem;
}
.tk-swiper__slide {
  flex: 0 0 210px;
  scroll-snap-align: start;
}

.tk-mini {
  background: #201f1f;
  border-radius: 16px;
  border: 1px solid rgba(74, 71, 51, 0.35);
  padding: 1rem;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.tk-mini:hover {
  border-color: rgba(255, 238, 50, 0.35);
}
.tk-mini--on {
  border-color: #ffee32;
  box-shadow: 0 0 0 1px rgba(247, 230, 40, 0.35);
}
.tk-mini__row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}
.tk-mini__id {
  font-weight: 700;
  font-size: 0.85rem;
  color: #fff;
}
.tk-mini__st {
  font-size: 0.7rem;
  color: #aaa;
}
.tk-mini__st[data-status='venuda'] {
  color: #4caf50;
}
.tk-mini__st[data-status='utilitzada'] {
  color: #888;
}
.tk-mini__hint {
  margin: 0;
  font-size: 0.65rem;
  color: var(--tk-outline);
  line-height: 1.3;
}

.tk-panel {
  margin-top: 1.25rem;
  padding: 1.5rem;
  background: var(--tk-surface-high);
  border-radius: 24px;
  border: 1px solid rgba(74, 71, 51, 0.35);
}
.tk-panel__ev {
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 800;
  margin: 0 0 0.35rem;
  color: #fff;
}
.tk-panel__date {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  color: var(--tk-outline);
}
.tk-panel__seat {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #ccc;
}
.tk-panel__qr {
  background: #fff;
  padding: 1.25rem;
  border-radius: 0.75rem;
  display: flex;
  justify-content: center;
  margin-bottom: 1rem;
}
.tk-panel__qr :deep(svg) {
  width: 11rem;
  height: 11rem;
  max-width: 100%;
  shape-rendering: crispEdges;
}
.tk-panel__valid {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.85rem;
  background: rgba(46, 125, 50, 0.12);
  border: 1px solid rgba(76, 175, 80, 0.35);
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 900;
  font-family: Epilogue, system-ui, sans-serif;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #4caf50;
  margin-bottom: 0.75rem;
}
.tk-panel__tid {
  margin: 0 0 1rem;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  font-size: 0.7rem;
  letter-spacing: 0.06em;
  color: var(--tk-outline);
}
.tk-panel__actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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
  background: var(--tk-bg);
  color: #fff;
  border: 1px solid rgba(74, 71, 51, 0.45);
}
.tk-btn--ghost:hover {
  background: #3a3939;
}

.tk-modal-bg {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.tk-modal {
  background: #1c1b1b;
  border: 1px solid var(--tk-outline-var);
  border-radius: 1rem;
  padding: 1.25rem;
  max-width: 22rem;
  width: 100%;
}
.tk-modal__title {
  margin: 0 0 0.5rem;
  font-size: 1.1rem;
  color: var(--tk-yellow);
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 800;
}
.tk-search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid var(--tk-outline-var);
  border-radius: 0.5rem;
  background: #0e0e0e;
}
.tk-search__ico {
  color: #666;
}
.tk-search__input {
  flex: 1;
  border: none;
  background: transparent;
  color: #fff;
  font-size: 0.9rem;
  min-width: 0;
}
.tk-friends {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0;
  max-height: 180px;
  overflow-y: auto;
}
.tk-friend-btn {
  width: 100%;
  text-align: left;
  padding: 0.5rem;
  border: none;
  border-bottom: 1px solid #222;
  background: transparent;
  color: #eee;
  font-size: 0.85rem;
  cursor: pointer;
}
.tk-friend-btn:hover {
  background: #2a2a2a;
}
.tk-pick {
  margin: 0.75rem 0 0;
  font-size: 0.9rem;
  color: #7bed9f;
}
.tk-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}
.tk-btn-sec {
  background: #444;
  border: none;
  color: #fff;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
  cursor: pointer;
}
.tk-btn-prim {
  background: var(--tk-yellow);
  color: var(--tk-yellow-text);
  border: none;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 700;
}
</style>
