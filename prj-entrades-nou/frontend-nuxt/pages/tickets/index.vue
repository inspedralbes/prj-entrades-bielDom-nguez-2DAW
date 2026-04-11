<template>
  <main class="tickets-page">
    <header class="tickets-page__header">
      <h1>Les meves entrades</h1>
      <NuxtLink to="/" class="tickets-page__back">← Inici</NuxtLink>
    </header>

    <p v-if="error" class="tickets-page__error">{{ error }}</p>
    <p v-else-if="loading" class="tickets-page__muted">Carregant…</p>

    <template v-else>
      <p v-if="grouped.length === 0" class="tickets-page__muted">Encara no tens cap entrada.</p>

      <section
        v-for="block in grouped"
        :key="block.eventKey"
        class="tickets-page__event"
      >
        <h2 class="tickets-page__event-title">{{ block.eventName }}</h2>
        <p v-if="block.startsAt" class="tickets-page__muted">{{ block.startsAt }}</p>
        <ul class="tickets-page__list">
          <li v-for="t in block.items" :key="t.id" class="tickets-page__card">
            <div class="tickets-page__card-main">
              <span class="tickets-page__seat">Seient {{ t.seat?.key || '—' }}</span>
              <span
                class="tickets-page__status"
                :data-status="t.displayStatus"
              >{{ labelStatus(t.displayStatus) }}</span>
            </div>
            <div class="tickets-page__actions">
              <NuxtLink
                :to="`/tickets/${t.id}`"
                class="tickets-page__link"
              >
                Veure QR
              </NuxtLink>
              <button
                v-if="t.displayStatus === 'venuda'"
                type="button"
                class="tickets-page__send"
                @click="openTransfer(t)"
              >
                Enviar entrada
              </button>
            </div>
          </li>
        </ul>
      </section>
    </template>

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
          Només a un amic amb invitació acceptada. Indica l’ID d’usuari destinatari.
        </p>
        <label class="tickets-page__label">
          ID usuari
          <input
            v-model.number="transferUserId"
            type="number"
            min="1"
            class="tickets-page__input"
          >
        </label>
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
import { computed, onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { usePrivateTicketSocket } from '~/composables/usePrivateTicketSocket';
import { useTicketsStore } from '~/stores/tickets';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const { getJson, postJson } = useAuthorizedApi();
const ticketsStore = useTicketsStore();
usePrivateTicketSocket();

const loading = ref(true);
const error = ref('');
const tickets = ref([]);

const transferOpen = ref(false);
const transferTicket = ref(null);
const transferUserId = ref(null);
const transferLoading = ref(false);
const transferErr = ref('');
const transferOk = ref('');

function openTransfer (t) {
  transferTicket.value = t;
  transferUserId.value = null;
  transferErr.value = '';
  transferOk.value = '';
  transferOpen.value = true;
}

function closeTransfer () {
  transferOpen.value = false;
  transferTicket.value = null;
}

async function submitTransfer () {
  if (!transferTicket.value || !transferUserId.value) {
    transferErr.value = 'Indica un usuari vàlid.';
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
    setTimeout(() => closeTransfer(), 1200);
  } catch (e) {
    transferErr.value = e?.data?.message || e?.message || 'No s’ha pogut transferir.';
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
    const key = ev?.id != null ? String(ev.id) : 'unknown';
    if (!map.has(key)) {
      map.set(key, {
        eventKey: key,
        eventName: ev?.name || 'Esdeveniment',
        startsAt: formatDate(ev?.starts_at),
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
    error.value = 'No s’ha pogut carregar les entrades.';
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
  max-width: 42rem;
  margin: 0 auto;
}
.tickets-page__header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.tickets-page__header h1 {
  margin: 0;
  font-size: 1.5rem;
  color: #ff0055;
}
.tickets-page__back {
  color: #aaa;
  text-decoration: none;
  font-size: 0.9rem;
}
.tickets-page__back:hover {
  color: #fff;
}
.tickets-page__muted {
  color: #888;
}
.tickets-page__error {
  color: #ff6b6b;
}
.tickets-page__event {
  margin-bottom: 2rem;
}
.tickets-page__event-title {
  font-size: 1.15rem;
  margin: 0 0 0.25rem;
}
.tickets-page__list {
  list-style: none;
  padding: 0;
  margin: 0.75rem 0 0;
}
.tickets-page__card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1rem;
  margin-bottom: 0.5rem;
  background: #161616;
  border-radius: 8px;
  border: 1px solid #2a2a2a;
}
.tickets-page__card-main {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.tickets-page__seat {
  font-weight: 600;
}
.tickets-page__status {
  font-size: 0.8rem;
  color: #aaa;
}
.tickets-page__status[data-status='venuda'] {
  color: #7bed9f;
}
.tickets-page__status[data-status='utilitzada'] {
  color: #888;
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
.tickets-page__link:hover {
  filter: brightness(1.1);
}
.tickets-page__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.35rem;
}
.tickets-page__send {
  background: transparent;
  border: 1px solid #555;
  color: #ccc;
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  font-size: 0.8rem;
  cursor: pointer;
}
.tickets-page__send:hover {
  border-color: #ff0055;
  color: #fff;
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
.tickets-page__input {
  padding: 0.45rem 0.6rem;
  border-radius: 6px;
  border: 1px solid #444;
  background: #0a0a0a;
  color: #fff;
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
.tickets-page__link--btn {
  border: none;
  cursor: pointer;
}
.tickets-page__ok {
  color: #7bed9f;
  font-size: 0.9rem;
  margin: 0.5rem 0 0;
}
</style>
