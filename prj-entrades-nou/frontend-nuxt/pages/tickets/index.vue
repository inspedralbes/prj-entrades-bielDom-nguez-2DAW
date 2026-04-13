<template>
  <main class="tickets-page">
    <header class="tickets-page__header">
      <h1>Les meves entrades</h1>
    </header>

    <p v-if="error" class="tickets-page__error">{{ error }}</p>
    <p v-else-if="loading" class="tickets-page__muted">Carregant…</p>

    <template v-else>
      <p v-if="grouped.length === 0" class="tickets-page__muted">Encara no tens cap entrada.</p>

      <div v-for="block in grouped" :key="block.eventKey" class="tickets-event">
        <NuxtLink :to="`/events/${block.eventId}`" class="tickets-event__card">
          <div class="tickets-event__media" :class="{ 'tickets-event__media--empty': !block.imageUrl }">
            <img v-if="block.imageUrl" class="tickets-event__img" :src="block.imageUrl" :alt="block.eventName" loading="lazy">
          </div>
          <div class="tickets-event__body">
            <h2 class="tickets-event__title">{{ block.eventName }}</h2>
            <p class="tickets-event__meta">{{ block.startsAt }} · {{ block.venueName }}</p>
            <span class="tickets-event__count">{{ block.items.length }} entr{{ block.items.length === 1 ? 'ada' : 'ades' }}</span>
          </div>
        </NuxtLink>
        
        <div class="tickets-swiper">
          <div class="tickets-swiper__container" ref="swiperContainer">
            <div 
              v-for="t in block.items" 
              :key="t.id" 
              class="tickets-swiper__slide"
              @click="selectTicket(t)"
            >
              <div class="ticket-card" :class="{ 'ticket-card--selected': selectedTicket?.id === t.id }">
                <div class="ticket-card__header">
                  <span class="ticket-card__seat">Entrada #{{ t.id.slice(0, 8) }}</span>
                  <span 
                    class="ticket-card__status"
                    :data-status="t.displayStatus"
                  >{{ labelStatus(t.displayStatus) }}</span>
                </div>
                <NuxtLink
                  :to="`/tickets/${t.id}`"
                  class="ticket-card__qr-link"
                >
                  Veure QR
                </NuxtLink>
                <button
                  v-if="t.displayStatus === 'venuda'"
                  type="button"
                  class="ticket-card__send"
                  @click.stop="openTransfer(t)"
                >
                  Enviar a un amic
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="selectedTicket" class="ticket-info">
          <p class="ticket-info__event">{{ block.eventName }}</p>
          <p class="ticket-info__date">{{ block.startsAt }}</p>
          <p v-if="selectedTicket.seat && (selectedTicket.seat.label || selectedTicket.seat.key)" class="ticket-info__seat">
            Ubicació: {{ selectedTicket.seat.label || selectedTicket.seat.key }}
          </p>
          <NuxtLink :to="`/tickets/${selectedTicket.id}`" class="ticket-info__qr">
            Veure QR complet
          </NuxtLink>
        </div>
      </div>
    </template>

    <footer class="tickets-footer">
      <NuxtLink to="/" class="tickets-footer__back">← Enrere</NuxtLink>
      <span v-if="selectedEventTime" class="tickets-footer__time">{{ selectedEventTime }}</span>
    </footer>

    <div
      v-if="transferOpen"
      class="tickets-page__modal-backdrop"
      role="dialog"
      aria-modal="true"
      @click.self="closeTransfer"
    >
      <div class="tickets-page__modal">
        <h2 class="tickets-page__modal-title">Enviar entrada</h2>
        <p class="tickets-page__muted">
          Només a un amic amb invitació acceptada. Cerca per nom o usuari.
        </p>
        <div class="tickets-page__search">
          <span class="tickets-page__search-ico" aria-hidden="true">⌕</span>
          <input
            v-model="transferFriendQuery"
            type="search"
            class="tickets-page__input tickets-page__input--grow"
            placeholder="Cercar amic…"
            @input="scheduleTransferFriendSearch"
          >
        </div>
        <ul v-if="transferFriendsLoading" class="tickets-page__friend-list">
          <li class="tickets-page__muted">Carregant…</li>
        </ul>
        <ul v-else class="tickets-page__friend-list">
          <li v-for="f in transferFriends" :key="f.id">
            <button type="button" class="tickets-page__friend-btn" @click="pickTransferFriend(f)">
              @{{ f.username }} · {{ f.name }}
            </button>
          </li>
        </ul>
        <p v-if="transferFriends.length === 0 && !transferFriendsLoading" class="tickets-page__muted">
          Cap amic coincideix.
        </p>
        <p v-if="transferSelectedLabel" class="tickets-page__transfer-pick">
          Destinatari: {{ transferSelectedLabel }}
        </p>
        <p v-if="transferErr" class="tickets-page__error">{{ transferErr }}</p>
        <p v-if="transferOk" class="tickets-page__ok">{{ transferOk }}</p>
        <div class="tickets-page__modal-actions">
          <button type="button" class="tickets-page__btn-sec" @click="closeTransfer">
            Cancel·lar
          </button>
          <button
            type="button"
            class="tickets-page__link tickets-page__link--btn"
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
import { computed, onMounted, ref, onUnmounted } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useEventImage } from '~/composables/useEventImage';
import { usePrivateTicketSocket } from '~/composables/usePrivateTicketSocket';
import { useTicketsStore } from '~/stores/tickets';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const { getJson, postJson } = useAuthorizedApi();
const { imageSrc } = useEventImage();
const ticketsStore = useTicketsStore();
usePrivateTicketSocket();

const loading = ref(true);
const error = ref('');
const tickets = ref([]);
const selectedTicket = ref(null);
const swiperContainer = ref(null);

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

function selectTicket(t) {
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
      path = `${path}?q=${encodeURIComponent(q)}`;
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
    await postJson(`/api/tickets/${transferTicket.value.id}/transfer`, {
      to_user_id: transferUserId.value,
    });
    transferOk.value = 'Entrada enviada. El QR anterior deixa de ser vàlid.';
    const data = await getJson('/api/tickets');
    tickets.value = data.tickets || [];
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
  if (s === 'venuda') return 'Vàlida';
  if (s === 'utilitzada') return 'Utilitzada';
  return s || '—';
}

function statusFor (t) {
  return ticketsStore.effectiveStatus(t.id, t.status);
}

const grouped = computed(() => {
  const map = new Map();
  for (const t of tickets.value) {
    const ev = t.event;
    const key = ev?.id != null ? String(ev.id) : 'unknown';
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
    map.get(key).items.push({
      ...t,
      displayStatus: statusFor(t),
    });
  }
  return [...map.values()];
});

const selectedEventTime = computed(() => {
  if (!selectedTicket.value?.event?.starts_at) return null;
  return formatDate(selectedTicket.value.event.starts_at);
});

function formatDate (iso) {
  if (!iso) return '';
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
.tickets-page {
  min-height: 100vh;
  background: #0a0a0a;
  color: #f5f5f5;
  padding: 1.5rem;
  padding-bottom: 80px;
  max-width: 42rem;
  margin: 0 auto;
}
.tickets-page__header {
  margin-bottom: 1.5rem;
}
.tickets-page__header h1 {
  margin: 0;
  font-size: 1.5rem;
  color: #ff0055;
}
.tickets-page__muted {
  color: #888;
}
.tickets-page__error {
  color: #ff6b6b;
}
.tickets-page__ok {
  color: #7bed9f;
  font-size: 0.9rem;
  margin: 0.5rem 0 0;
}
.tickets-event {
  margin-bottom: 2rem;
}
.tickets-event__card {
  display: flex;
  flex-direction: column;
  text-decoration: none;
  color: #f5f5f5;
  background: #1a1a1a;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 1rem;
}
.tickets-event__media {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #222;
}
.tickets-event__media--empty {
  background: linear-gradient(145deg, #2a2a2a 0%, #1a1a1a 100%);
}
.tickets-event__media--empty::after {
  content: 'Sense imatge';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  color: #666;
}
.tickets-event__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.tickets-event__body {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.tickets-event__title {
  font-size: 1.15rem;
  font-weight: 600;
  margin: 0;
  color: #f5f5f5;
}
.tickets-event__meta {
  font-size: 0.9rem;
  color: #888;
  margin: 0;
}
.tickets-event__count {
  display: inline-block;
  margin-top: 0.5rem;
  padding: 0.35rem 0.65rem;
  background: #ff0055;
  color: #fff;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  align-self: flex-start;
}

.tickets-swiper {
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  margin: 0 -1.5rem;
  padding: 0 1rem;
}
.tickets-swiper__container {
  display: flex;
  gap: 0.75rem;
  padding-bottom: 0.5rem;
}
.tickets-swiper__slide {
  flex: 0 0 200px;
  scroll-snap-align: start;
}

.ticket-card {
  background: #161616;
  border-radius: 8px;
  border: 1px solid #2a2a2a;
  padding: 1rem;
  cursor: pointer;
  transition: border-color 0.2s;
}
.ticket-card:hover {
  border-color: #444;
}
.ticket-card--selected {
  border-color: #ff0055;
}
.ticket-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}
.ticket-card__seat {
  font-weight: 600;
  font-size: 0.9rem;
}
.ticket-card__status {
  font-size: 0.75rem;
  color: #aaa;
}
.ticket-card__status[data-status='venuda'] {
  color: #7bed9f;
}
.ticket-card__status[data-status='utilitzada'] {
  color: #888;
}
.ticket-card__qr-link {
  display: block;
  text-align: center;
  padding: 0.5rem;
  background: #ff0055;
  color: #fff;
  text-decoration: none;
  border-radius: 6px;
  font-size: 0.85rem;
}
.ticket-card__qr-link:hover {
  filter: brightness(1.1);
}
.ticket-card__send {
  display: block;
  width: 100%;
  margin-top: 0.5rem;
  padding: 0.45rem;
  background: transparent;
  border: 1px solid #444;
  color: #ccc;
  border-radius: 6px;
  font-size: 0.8rem;
  cursor: pointer;
}
.ticket-card__send:hover {
  border-color: #ff0055;
  color: #ff0055;
}

.ticket-info {
  background: #1a1a1a;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}
.ticket-info__event {
  font-weight: 600;
  margin: 0 0 0.5rem;
}
.ticket-info__date {
  color: #888;
  font-size: 0.9rem;
  margin: 0 0 0.25rem;
}
.ticket-info__seat {
  color: #666;
  font-size: 0.85rem;
  margin: 0 0 0.75rem;
}
.ticket-info__qr {
  color: #ff0055;
  text-decoration: none;
  font-size: 0.9rem;
}

.tickets-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #1a1a1a;
  border-top: 1px solid #2a2a2a;
  max-width: 42rem;
  margin: 0 auto;
  left: 50%;
  transform: translateX(-50%);
}
.tickets-footer__back {
  color: #ff0055;
  text-decoration: none;
}
.tickets-footer__time {
  color: #888;
  font-size: 0.9rem;
}

.tickets-page__modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.tickets-page__modal {
  background: #141414;
  border: 1px solid #333;
  border-radius: 10px;
  padding: 1.25rem;
  max-width: 22rem;
  width: 100%;
}
.tickets-page__modal-title {
  margin: 0 0 0.5rem;
  font-size: 1.1rem;
  color: #ff0055;
}
.tickets-page__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: #aaa;
  margin-top: 0.75rem;
}
.tickets-page__search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid #333;
  border-radius: 8px;
  background: #0a0a0a;
}
.tickets-page__search-ico {
  color: #666;
}
.tickets-page__input {
  padding: 0.45rem 0.6rem;
  border-radius: 6px;
  border: 1px solid #444;
  background: #0a0a0a;
  color: #fff;
}
.tickets-page__input--grow {
  flex: 1;
  border: none;
  background: transparent;
}
.tickets-page__friend-list {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0;
  max-height: 180px;
  overflow-y: auto;
}
.tickets-page__friend-btn {
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
.tickets-page__friend-btn:hover {
  background: #1a1a1a;
}
.tickets-page__transfer-pick {
  margin: 0.75rem 0 0;
  font-size: 0.9rem;
  color: #7bed9f;
}
.tickets-page__modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}
.tickets-page__btn-sec {
  background: #333;
  border: none;
  color: #fff;
  padding: 0.45rem 0.85rem;
  border-radius: 6px;
  cursor: pointer;
}
.tickets-page__link {
  flex-shrink: 0;
  padding: 0.4rem 0.85rem;
  background: #ff0055;
  color: #fff;
  text-decoration: none;
  border-radius: 6px;
  font-size: 0.9rem;
}
.tickets-page__link--btn {
  border: none;
  cursor: pointer;
}
</style>