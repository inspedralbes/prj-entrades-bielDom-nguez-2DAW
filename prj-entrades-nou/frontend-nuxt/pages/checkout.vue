<template>
  <main class="checkout-page">
    <header class="checkout-header">
      <h1 class="checkout-title">Les meves entrades</h1>
    </header>

    <div v-if="pending" class="loading">Carregant…</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <template v-else-if="event && tickets.length > 0">
      <div class="tickets-swiper">
        <div class="tickets-swiper__container">
          <div
            v-for="(t, idx) in tickets"
            :key="t.id"
            class="tickets-swiper__slide"
          >
            <div class="ticket-card">
              <div class="ticket-card__header">
                <span class="ticket-card__label">Entrada {{ idx + 1 }}</span>
                <span class="ticket-card__status" data-status="venuda">Vàlida</span>
              </div>
              <div class="ticket-card__qr">
                <NuxtLink :to="`/tickets/${t.id}`" class="ticket-card__qr-link">
                  Veure QR
                </NuxtLink>
              </div>
              <div class="ticket-card__code">{{ shortId(t.id) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="event-info">
        <h2 class="event-name">{{ event.name }}</h2>
        <p class="event-date">{{ formatDate(event.starts_at) }}</p>
        <p v-if="event.venue" class="event-venue">
          {{ event.venue.name }}<br />
          <span v-if="event.venue.address">{{ event.venue.address }}, </span>{{ event.venue.city }}
        </p>
        <p v-if="event.price" class="event-price">
          Preu: €{{ event.price }} × {{ tickets.length }} = €{{ totalDisplay.toFixed(2) }}
        </p>
      </div>

      <div class="checkout-actions">
        <NuxtLink to="/tickets" class="btn-secondary">Veure totes les entrades</NuxtLink>
        <NuxtLink to="/" class="btn-primary">Tornar a l'inici</NuxtLink>
      </div>
    </template>
  </main>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useApi } from '~/composables/useApi';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const router = useRouter();
const { fetchApi } = useApi();
const { postJson, getJson } = useAuthorizedApi();

const event = ref(null);
const tickets = ref([]);
const pending = ref(true);
const error = ref('');

const totalDisplay = computed(() => {
  const p = parseFloat(event.value && event.value.price);
  if (Number.isNaN(p)) {
    return 0;
  }
  return p * tickets.value.length;
});

function parseQueryStr (val) {
  if (!val) {
    return null;
  }
  if (Array.isArray(val)) {
    return val[0];
  }
  return val;
}

function shortId (id) {
  if (!id || typeof id !== 'string') {
    return '';
  }
  if (id.length <= 12) {
    return id;
  }
  return `${id.slice(0, 8)}…`;
}

async function loadEvent (evId) {
  const f = await fetchApi(`/api/events/${evId}`);
  event.value = f;
}

async function runCheckout () {
  pending.value = true;
  error.value = '';

  const eventIdStr = parseQueryStr(route.query.eventId);
  const orderIdStr = parseQueryStr(route.query.orderId);
  const qtyRaw = parseQueryStr(route.query.quantity);
  let quantity = parseInt(qtyRaw, 10);
  if (Number.isNaN(quantity) || quantity < 1) {
    quantity = 1;
  }
  if (quantity > 6) {
    quantity = 6;
  }

  try {
    if (orderIdStr) {
      const oid = parseInt(orderIdStr, 10);
      const data = await getJson('/api/tickets');
      const list = data.tickets || [];
      const mine = [];
      for (let i = 0; i < list.length; i++) {
        const t = list[i];
        if (parseInt(t.order_id, 10) === oid) {
          mine.push(t);
        }
      }
      if (mine.length === 0) {
        error.value = 'No s\'han trobat les entrades d\'aquesta comanda.';
        return;
      }
      tickets.value = mine;
      const evId = mine[0].event && mine[0].event.id;
      if (evId) {
        await loadEvent(evId);
      }
      return;
    }

    if (!eventIdStr) {
      error.value = 'Enllaç de compra invàlid.';
      return;
    }

    const eventIdNum = parseInt(eventIdStr, 10);

    const seatKeysRaw = parseQueryStr(route.query.seatKeys);
    if (seatKeysRaw && String(seatKeysRaw).trim() !== '') {
      const seatKeys = [];
      const rawParts = String(seatKeysRaw).split(',');
      for (let pi = 0; pi < rawParts.length; pi++) {
        const trimmed = rawParts[pi].trim();
        if (trimmed !== '') {
          seatKeys.push(trimmed);
        }
      }
      if (seatKeys.length < 1 || seatKeys.length > 6) {
        error.value = 'Selecció de seients invàlida (1–6).';
        return;
      }

      const createdCinema = await postJson('/api/orders/cinema-seats', {
        event_id: eventIdNum,
        seat_keys: seatKeys,
      });

      const confirmedCinema = await postJson(`/api/orders/${createdCinema.order_id}/confirm-payment`, {});

      const listCinema = confirmedCinema.tickets || [];
      tickets.value = listCinema;
      await loadEvent(eventIdNum);

      await router.replace({
        path: '/checkout',
        query: {
          eventId: String(eventIdNum),
          orderId: String(createdCinema.order_id),
        },
      });
      return;
    }

    const created = await postJson('/api/orders/quantity', {
      event_id: eventIdNum,
      quantity,
    });

    const confirmed = await postJson(`/api/orders/${created.order_id}/confirm-payment`, {});

    const list = confirmed.tickets || [];
    tickets.value = list;
    await loadEvent(eventIdNum);

    await router.replace({
      path: '/checkout',
      query: {
        eventId: String(eventIdNum),
        quantity: String(quantity),
        orderId: String(created.order_id),
      },
    });
  } catch (e) {
    const status = e && e.status;
    if (status === 401) {
      navigateTo('/login');
      return;
    }
    let msg = 'No s\'ha pogut completar la compra.';
    if (e && e.data && e.data.message) {
      msg = e.data.message;
    }
    error.value = msg;
    console.error(e);
  } finally {
    pending.value = false;
  }
}

function formatDate (iso) {
  if (!iso) {
    return '';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES', {
      dateStyle: 'full',
      timeStyle: 'short',
    });
  } catch {
    return iso;
  }
}

onMounted(runCheckout);
</script>

<style scoped>
.checkout-page {
  padding: 1rem;
  padding-bottom: 100px;
  max-width: 42rem;
  margin: 0 auto;
}
.checkout-header {
  margin-bottom: 1.5rem;
}
.checkout-title {
  font-size: 1.5rem;
  color: #ff0055;
  margin: 0;
}
.loading, .error {
  text-align: center;
  color: #888;
  padding: 2rem;
}
.error {
  color: #ff6b6b;
}

.tickets-swiper {
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  margin: 0 -1rem;
  padding: 0 1rem;
}
.tickets-swiper__container {
  display: flex;
  gap: 0.75rem;
  padding-bottom: 0.5rem;
}
.tickets-swiper__slide {
  flex: 0 0 220px;
  scroll-snap-align: start;
}

.ticket-card {
  background: #161616;
  border-radius: 12px;
  border: 1px solid #2a2a2a;
  padding: 1.25rem;
  text-align: center;
}
.ticket-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.ticket-card__label {
  font-weight: 600;
  color: #f5f5f5;
}
.ticket-card__status {
  font-size: 0.75rem;
  color: #7bed9f;
  background: rgba(123, 237, 159, 0.1);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}
.ticket-card__qr {
  background: #1a1a1a;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1rem;
  min-height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ticket-card__qr-link {
  color: #ff0055;
  font-weight: 600;
  text-decoration: none;
}
.ticket-card__qr-link:hover {
  text-decoration: underline;
}
.ticket-card__code {
  font-family: monospace;
  font-size: 0.85rem;
  color: #666;
}

.event-info {
  background: #1a1a1a;
  border-radius: 12px;
  padding: 1.25rem;
  margin-top: 1.5rem;
}
.event-name {
  font-size: 1.25rem;
  color: #f5f5f5;
  margin: 0 0 0.75rem;
}
.event-date {
  color: #888;
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}
.event-venue {
  color: #666;
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  line-height: 1.4;
}
.event-price {
  color: #ff0055;
  font-weight: 600;
  margin: 0;
  padding-top: 0.75rem;
  border-top: 1px solid #333;
}

.checkout-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
}
.btn-secondary, .btn-primary {
  flex: 1;
  padding: 0.85rem 1rem;
  text-align: center;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
}
.btn-secondary {
  background: #2a2a2a;
  color: #f5f5f5;
  border: 1px solid #444;
}
.btn-primary {
  background: #ff0055;
  color: #fff;
}
.btn-secondary:hover, .btn-primary:hover {
  filter: brightness(1.1);
}
</style>
