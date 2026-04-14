<template>
  <main class="tk-shell tk-detail">
    <header class="tk-bar" aria-label="Capçalera entrada">
      <div class="tk-bar__inner">
        <NuxtLink to="/tickets" class="tk-bar__icon-btn" aria-label="Tornar a les entrades">
          <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
        </NuxtLink>
        <p class="tk-bar__brand">TR3-ENTRADES</p>
        <span class="tk-bar__balance" aria-hidden="true" />
      </div>
    </header>

    <div class="tk-detail__body">
      <p v-if="loading" class="tk-muted">Carregant…</p>
      <p v-else-if="error" class="tk-err">{{ error }}</p>

      <template v-else-if="ticket">
        <!-- Targeta principal (estil mockup TR3) -->
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
              <h1 class="tk-card__title">{{ ticket.event?.name || 'Esdeveniment' }}</h1>
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
              <div v-if="displayStatus === 'venuda' && qrSvg" class="tk-qr-wrap" v-html="qrSvg" />
              <p v-else-if="displayStatus === 'venuda' && qrError" class="tk-err">{{ qrError }}</p>
              <div v-else-if="displayStatus === 'utilitzada'" class="tk-used">
                <span class="tk-used__ico material-symbols-outlined" aria-hidden="true">cancel</span>
                <p class="tk-used__txt">Aquesta entrada ja s’ha utilitzat; el QR no és vàlid.</p>
              </div>

              <div v-if="displayStatus === 'venuda'" class="tk-valid">
                <span class="material-symbols-outlined tk-valid__ico" aria-hidden="true">check_circle</span>
                <span class="tk-valid__txt">Vàlida per a l’entrada</span>
              </div>
              <p class="tk-idline">ID: {{ publicTicketId }}</p>
            </div>
          </div>

          <div class="tk-card__actions">
            <button
              v-if="displayStatus === 'venuda'"
              type="button"
              class="tk-btn tk-btn--primary"
              @click="openTransfer"
            >
              <span class="material-symbols-outlined" aria-hidden="true">send</span>
              Enviar a un amic
            </button>
            <NuxtLink
              v-if="eventLinkTo"
              :to="eventLinkTo"
              class="tk-btn tk-btn--ghost"
            >
              <span class="material-symbols-outlined" aria-hidden="true">event</span>
              Veure esdeveniment
            </NuxtLink>
          </div>
        </div>

        <div class="tk-legal">
          <span class="material-symbols-outlined tk-legal__ico" aria-hidden="true">lock</span>
          <p class="tk-legal__txt">
            Aquesta entrada està protegida amb tecnologia TR3-Secure™. No comparteixis el codi QR abans de l’entrada.
          </p>
        </div>
      </template>
    </div>

    <div
      v-if="transferOpen"
      class="tk-modal-bg"
      role="dialog"
      aria-modal="true"
      aria-labelledby="tk-transfer-title"
      @click.self="closeTransfer"
    >
      <div class="tk-modal">
        <h2 id="tk-transfer-title" class="tk-modal__title">Enviar entrada</h2>
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

const route = useRoute();
const { getJson, postJson, getTicketQrSvg } = useAuthorizedApi();
const { imageSrc, imageAlt } = useEventImage();
const ticketsStore = useTicketsStore();
usePrivateTicketSocket();

const loading = ref(true);
const error = ref('');
const ticket = ref(null);
const qrSvg = ref('');
const qrError = ref('');

const transferOpen = ref(false);
const transferUserId = ref(null);
const transferSelectedLabel = ref('');
const transferFriendQuery = ref('');
const transferFriends = ref([]);
const transferFriendsLoading = ref(false);
let transferSearchTimer = null;
const transferLoading = ref(false);
const transferErr = ref('');
const transferOk = ref('');

const ticketId = computed(() => String(route.params.ticketId || ''));

const displayStatus = computed(() => {
  const t = ticket.value;
  if (!t) {
    return '';
  }
  return ticketsStore.effectiveStatus(t.id, t.status);
});

const heroImageUrl = computed(() => {
  const ev = ticket.value?.event;
  return imageSrc(ev);
});

const heroImageAlt = computed(() => {
  return imageAlt(ticket.value?.event);
});

const venueLine = computed(() => {
  const n = ticket.value?.event?.venue?.name;
  if (typeof n === 'string' && n.trim() !== '') {
    return n.trim().toUpperCase();
  }
  return '—';
});

const heroDateLine = computed(() => {
  const iso = ticket.value?.event?.starts_at;
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
  const t = ticket.value;
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
  const name = ticket.value?.event?.name;
  if (typeof name === 'string' && name.toLowerCase().indexOf('concert') >= 0) {
    return 'LIVE CONCERT';
  }
  return 'ESDEVENIMENT';
});

const eventLinkTo = computed(() => {
  const id = ticket.value?.event?.id;
  if (id === undefined || id === null) {
    return '';
  }
  return '/events/' + String(id);
});

const publicTicketId = computed(() => {
  const id = ticket.value?.id;
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
});

function openTransfer () {
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
}

async function submitTransfer () {
  const tid = ticket.value?.id;
  if (!tid || !transferUserId.value) {
    transferErr.value = 'Selecciona un amic de la llista.';
    return;
  }
  transferLoading.value = true;
  transferErr.value = '';
  transferOk.value = '';
  try {
    await postJson('/api/tickets/' + tid + '/transfer', {
      to_user_id: transferUserId.value,
    });
    transferOk.value = 'Entrada enviada. El QR anterior deixa de ser vàlid.';
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:notifications-updated'));
    }
    setTimeout(() => {
      closeTransfer();
      loadTicket();
    }, 1200);
  } catch (e) {
    transferErr.value = e?.data?.message || e?.message || 'No s\'ha pogut transferir.';
  } finally {
    transferLoading.value = false;
  }
}

async function loadTicket () {
  loading.value = true;
  error.value = '';
  ticket.value = null;
  qrSvg.value = '';
  qrError.value = '';

  const id = ticketId.value;
  if (!id) {
    error.value = 'Identificador d’entrada no vàlid.';
    loading.value = false;
    return;
  }

  try {
    const data = await getJson('/api/tickets');
    const list = data.tickets || [];
    let found = null;
    for (let i = 0; i < list.length; i = i + 1) {
      if (list[i].id === id) {
        found = list[i];
        break;
      }
    }
    if (!found) {
      error.value = 'No s’ha trobat aquesta entrada al teu compte.';
      return;
    }
    ticket.value = found;

    if (ticketsStore.effectiveStatus(found.id, found.status) === 'venuda') {
      try {
        qrSvg.value = await getTicketQrSvg(id);
      } catch (e) {
        const st = e?.status;
        if (st === 409) {
          qrError.value = 'L’entrada ja no és vàlida per al QR.';
        } else if (st === 503) {
          qrError.value = 'El servei de QR no està disponible ara mateix.';
        } else {
          qrError.value = 'No s’ha pogut generar el QR.';
        }
      }
    }
  } catch (e) {
    error.value = 'No s’ha pogut carregar l’entrada.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(loadTicket);
watch(ticketId, () => {
  loadTicket();
});

watch(displayStatus, (s) => {
  if (s === 'utilitzada') {
    qrSvg.value = '';
    qrError.value = '';
  }
});
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

.tk-shell {
  --tk-bg: #131313;
  --tk-on-bg: #e5e2e1;
  --tk-yellow: #f7e628;
  --tk-yellow-text: #6e6600;
  --tk-outline: #959178;
  --tk-surface-high: #2a2a2a;
  --tk-surface-highest: #353534;
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
  border: none;
  border-radius: 9999px;
  background: transparent;
  color: #ffee32;
  text-decoration: none;
  cursor: pointer;
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
  font-size: 1.35rem;
  color: #ffee32;
}
.tk-bar__balance {
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
}

.tk-detail__body {
  padding: calc(var(--header-h, 56px) + 4rem + 1.5rem) 1.5rem 2rem;
  max-width: 28rem;
  margin: 0 auto;
}

.tk-muted {
  color: var(--tk-outline);
  margin: 0;
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

.tk-legal {
  margin-top: 2rem;
  padding: 1.5rem;
  background: var(--tk-container-low);
  border: 1px solid rgba(74, 71, 51, 0.35);
  border-radius: 0.5rem;
  text-align: center;
}
.tk-legal__ico {
  display: block;
  margin: 0 auto 0.5rem;
  color: var(--tk-outline);
  font-size: 1.25rem;
}
.tk-legal__txt {
  margin: 0;
  font-size: 10px;
  line-height: 1.5;
  color: var(--tk-outline);
  padding: 0 0.5rem;
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
