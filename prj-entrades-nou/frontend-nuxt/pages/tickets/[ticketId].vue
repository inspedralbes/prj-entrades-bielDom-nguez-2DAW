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
        <TicketCardFull
          :ticket="ticket"
          :qr-svg="qrSvg"
          :qr-error="qrError"
          :display-status="displayStatus"
          heading-is-h1
          @transfer="openTransfer"
        />

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
import TicketCardFull from '~/components/TicketCardFull.vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useTicketsStore } from '~/stores/tickets';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const { getJson, postJson, getTicketQrSvg } = useAuthorizedApi();
const ticketsStore = useTicketsStore();

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
  padding-bottom: calc(var(--footer-stack) + 1rem);
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
