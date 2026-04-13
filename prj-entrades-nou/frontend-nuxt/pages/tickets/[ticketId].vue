<template>
  <main class="ticket-detail">
    <header class="ticket-detail__header">
      <NuxtLink to="/tickets" class="ticket-detail__back">← Totes les entrades</NuxtLink>
    </header>

    <p v-if="loading" class="ticket-detail__muted">Carregant…</p>
    <p v-else-if="error" class="ticket-detail__error">{{ error }}</p>

    <template v-else-if="ticket">
      <h1 class="ticket-detail__title">{{ ticket.event?.name || 'Esdeveniment' }}</h1>
      <p v-if="startsAt" class="ticket-detail__muted">{{ startsAt }}</p>

      <div class="ticket-detail__meta">
        <p><strong>Seient</strong> {{ ticket.seat?.key || '—' }}</p>
        <p>
          <strong>Estat</strong>
          <span class="ticket-detail__status" :data-status="displayStatus">{{ labelStatus(displayStatus) }}</span>
        </p>
      </div>

      <div v-if="displayStatus === 'utilitzada'" class="ticket-detail__stamp-wrap" aria-hidden="true">
        <span class="ticket-detail__stamp">✕</span>
      </div>

      <div v-if="displayStatus === 'venuda' && qrSvg" class="ticket-detail__qr" v-html="qrSvg" />
      <p v-else-if="displayStatus === 'venuda' && qrError" class="ticket-detail__error">{{ qrError }}</p>
      <p v-else-if="displayStatus === 'utilitzada'" class="ticket-detail__muted">
        Aquesta entrada ja s’ha utilitzat; el QR no és vàlid.
      </p>
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { usePrivateTicketSocket } from '~/composables/usePrivateTicketSocket';
import { useTicketsStore } from '~/stores/tickets';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const { getJson, getTicketQrSvg } = useAuthorizedApi();
const ticketsStore = useTicketsStore();
usePrivateTicketSocket();

const loading = ref(true);
const error = ref('');
const ticket = ref(null);
const qrSvg = ref('');
const qrError = ref('');

const ticketId = computed(() => String(route.params.ticketId || ''));

const displayStatus = computed(() => {
  const t = ticket.value;
  if (!t) {
    return '';
  }
  return ticketsStore.effectiveStatus(t.id, t.status);
});

const startsAt = computed(() => {
  const iso = ticket.value?.event?.starts_at;
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
});

function labelStatus (s) {
  if (s === 'venuda') {
    return 'Vàlida';
  }
  if (s === 'utilitzada') {
    return 'Utilitzada';
  }
  return s || '—';
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
    const found = list.find((t) => t.id === id);
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
.ticket-detail {
  min-height: 100vh;
  background: #0a0a0a;
  color: #f5f5f5;
  padding: 1.5rem;
  max-width: 28rem;
  margin: 0 auto;
}
.ticket-detail__header {
  margin-bottom: 1.25rem;
}
.ticket-detail__back {
  color: #aaa;
  text-decoration: none;
  font-size: 0.9rem;
}
.ticket-detail__back:hover {
  color: #fff;
}
.ticket-detail__title {
  margin: 0 0 0.5rem;
  font-size: 1.35rem;
  color: #ff0055;
}
.ticket-detail__muted {
  color: #888;
}
.ticket-detail__error {
  color: #ff6b6b;
}
.ticket-detail__meta {
  margin: 1.25rem 0;
  line-height: 1.6;
}
.ticket-detail__status[data-status='venuda'] {
  color: #7bed9f;
}
.ticket-detail__status[data-status='utilitzada'] {
  color: #888;
}
.ticket-detail__stamp-wrap {
  display: flex;
  justify-content: center;
  margin: 1rem 0;
}
.ticket-detail__stamp {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 4.5rem;
  height: 4.5rem;
  border: 4px solid #c0392b;
  border-radius: 50%;
  color: #c0392b;
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1;
}
.ticket-detail__qr {
  margin-top: 1rem;
  padding: 1rem;
  background: #fff;
  border-radius: 12px;
  display: flex;
  justify-content: center;
  align-items: center;
}
.ticket-detail__qr :deep(svg) {
  max-width: 100%;
  height: auto;
}
</style>
