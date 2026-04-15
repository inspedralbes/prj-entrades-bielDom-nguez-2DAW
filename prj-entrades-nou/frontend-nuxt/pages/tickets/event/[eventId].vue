<template>
  <div class="tk-shell">
    <header class="tk-bar" aria-label="Entrades de l'esdeveniment">
      <div class="tk-bar__inner">
        <NuxtLink to="/tickets" class="tk-bar__icon-btn" aria-label="Tornar a les entrades">
          <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
        </NuxtLink>
        <p class="tk-bar__brand">Les meves entrades</p>
        <span class="tk-bar__balance" aria-hidden="true" />
      </div>
    </header>

    <section class="tk-body">
      <template v-if="loading">
        <p class="tk-muted">Carregant…</p>
      </template>
      <template v-else-if="error">
        <p class="tk-err">{{ error }}</p>
      </template>
      <template v-else-if="items.length === 0">
        <p class="tk-muted">No tens entrades per aquest esdeveniment.</p>
      </template>
      <template v-else>
        <div class="tk-event-tickets">
          <TicketCardFull
            v-for="(item, idx) in items"
            :key="'ent-' + String(item.ticket.id) + '-' + String(idx)"
            :ticket="item.ticket"
            :qr-svg="item.qrSvg"
            :qr-error="item.qrError"
            :display-status="item.displayStatus"
            @transfer="() => openTransfer(String(item.ticket.id))"
          />
        </div>
      </template>
    </section>

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
        <p class="tk-muted">Només a un amic amb invitació acceptada. Cerca per nom o usuari.</p>
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
        <p v-if="transferFriends.length === 0 && !transferFriendsLoading" class="tk-muted">Cap amic coincideix.</p>
        <p v-if="transferSelectedLabel" class="tk-pick">Destinatari: {{ transferSelectedLabel }}</p>
        <p v-if="transferErr" class="tk-err">{{ transferErr }}</p>
        <p v-if="transferOk" class="tk-ok">{{ transferOk }}</p>
        <div class="tk-modal__actions">
          <button type="button" class="tk-btn-sec" @click="closeTransfer">Cancel·lar</button>
          <button type="button" class="tk-btn-prim" :disabled="transferLoading" @click="submitTransfer">
            {{ transferButtonText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import TicketCardFull from '~/components/TicketCardFull.vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useTicketsStore } from '~/stores/tickets';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const { getJson, getTicketQrSvg, postJson } = useAuthorizedApi();
const ticketsStore = useTicketsStore();

const loading = ref(true);
const error = ref('');
const items = ref([]);
const eventId = computed(() => String(route.params.eventId || ''));

const transferOpen = ref(false);
const transferTicketId = ref('');
const transferUserId = ref(null);
const transferSelectedLabel = ref('');
const transferFriendQuery = ref('');
const transferFriends = ref([]);
const transferFriendsLoading = ref(false);
let transferSearchTimer = null;
const transferLoading = ref(false);
const transferErr = ref('');
const transferOk = ref('');

const transferButtonText = computed(() => {
  if (transferLoading.value) {
    return 'Enviant…';
  }
  return 'Confirmar';
});

async function loadTickets () {
  loading.value = true;
  error.value = '';
  items.value = [];
  try {
    const data = await getJson('/api/tickets');
    const list = data.tickets || [];
    const eventRows = [];
    for (let i = 0; i < list.length; i = i + 1) {
      const row = list[i];
      const rowEventId = row?.event?.id;
      if (String(rowEventId) === eventId.value) {
        eventRows.push(row);
      }
    }

    const normalized = [];
    for (let i = 0; i < eventRows.length; i = i + 1) {
      const row = eventRows[i];
      let effective = ticketsStore.effectiveStatus(row.id, row.status);
      if (effective !== 'venuda' && effective !== 'utilitzada') {
        if (typeof row.status === 'string' && row.status !== '') {
          effective = row.status;
        } else {
          effective = 'venuda';
        }
      }
      let qrSvg = '';
      let qrError = '';
      if (effective === 'venuda') {
        try {
          qrSvg = await getTicketQrSvg(row.id);
        } catch (e) {
          const st = e?.status;
          if (st === 409) {
            qrError = 'L’entrada ja no és vàlida per al QR.';
          } else if (st === 503) {
            qrError = 'El servei de QR no està disponible ara mateix.';
          } else {
            qrError = 'No s’ha pogut generar el QR.';
          }
        }
      }
      normalized.push({
        ticket: row,
        displayStatus: effective,
        qrSvg,
        qrError,
      });
    }
    items.value = normalized;
  } catch (e) {
    if (e?.status === 401) {
      navigateTo('/login');
      return;
    }
    error.value = 'No s\'han pogut carregar les entrades.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function openTransfer (ticketId) {
  transferOpen.value = true;
  transferTicketId.value = ticketId;
  transferUserId.value = null;
  transferSelectedLabel.value = '';
  transferFriendQuery.value = '';
  transferErr.value = '';
  transferOk.value = '';
  loadTransferFriends();
}

function closeTransfer () {
  transferOpen.value = false;
  transferTicketId.value = '';
}

function pickTransferFriend (f) {
  transferUserId.value = f.id;
  transferSelectedLabel.value = '@' + f.username + ' · ' + f.name;
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
  } catch {
    transferFriends.value = [];
  } finally {
    transferFriendsLoading.value = false;
  }
}

async function submitTransfer () {
  if (!transferTicketId.value || !transferUserId.value) {
    transferErr.value = 'Selecciona un amic de la llista.';
    return;
  }
  transferLoading.value = true;
  transferErr.value = '';
  transferOk.value = '';
  try {
    await postJson('/api/tickets/' + transferTicketId.value + '/transfer', {
      to_user_id: transferUserId.value,
    });
    transferOk.value = 'Entrada enviada. El QR anterior deixa de ser vàlid.';
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:notifications-updated'));
    }
    setTimeout(() => {
      closeTransfer();
      loadTickets();
    }, 1000);
  } catch (e) {
    transferErr.value = e?.data?.message || e?.message || 'No s\'ha pogut transferir.';
  } finally {
    transferLoading.value = false;
  }
}

onMounted(loadTickets);
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
  border-radius: 9999px;
  color: #ffee32;
  text-decoration: none;
}
.tk-bar__brand {
  margin: 0;
  flex: 1;
  text-align: center;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  text-transform: uppercase;
  font-size: 1.1rem;
  color: #ffee32;
}
.tk-bar__balance {
  width: 2.5rem;
  height: 2.5rem;
}

.tk-body {
  box-sizing: border-box;
  min-height: 45vh;
  padding: calc(var(--header-h, 56px) + 4rem + 1rem) 1.5rem 2rem;
  max-width: 28rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.tk-event-tickets {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
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
  margin: 0.5rem 0 0;
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
  color: var(--tk-yellow);
}
.tk-search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid var(--tk-outline-var);
  border-radius: 0.5rem;
}
.tk-search__input {
  flex: 1;
  border: none;
  background: transparent;
  color: #fff;
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
}
.tk-pick {
  margin: 0.75rem 0 0;
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
}
.tk-btn-prim {
  background: var(--tk-yellow);
  color: var(--tk-yellow-text);
  border: none;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
}
</style>
