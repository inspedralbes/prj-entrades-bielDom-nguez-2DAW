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
            <NuxtLink
              :to="`/tickets/${t.id}`"
              class="tickets-page__link"
            >
              Veure QR
            </NuxtLink>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { usePrivateTicketSocket } from '~/composables/usePrivateTicketSocket';
import { useTicketsStore } from '~/stores/tickets';

definePageMeta({
  middleware: 'auth',
});

const { getJson } = useAuthorizedApi();
const ticketsStore = useTicketsStore();
usePrivateTicketSocket();

const loading = ref(true);
const error = ref('');
const tickets = ref([]);

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
</style>
