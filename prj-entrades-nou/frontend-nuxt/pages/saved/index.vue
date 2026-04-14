<template>
  <div class="saved user-page">
    <header class="user-page-hero user-page-hero--spaced">
      <h1 class="user-page-title">
        Guardats
      </h1>
      <p class="user-page-lead">
        Esdeveniments que has desat per tornar-hi més tard.
      </p>
    </header>
    <p v-if="error" class="saved__err">{{ error }}</p>
    <p v-else-if="loading" class="saved__muted">Carregant…</p>
    <ul v-else-if="events.length" class="saved__cards">
      <li v-for="ev in events" :key="ev.id" class="saved__card-item">
        <EventCardTr3
          :event="ev"
          :show-heart="true"
          :heart-filled="true"
          @favorite-click="removeSaved(ev.id)"
        />
      </li>
    </ul>
    <p v-else class="saved__muted">No tens esdeveniments guardats.</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import EventCardTr3 from '~/components/EventCardTr3.vue';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const { getJson, deleteJson } = useAuthorizedApi();
const loading = ref(true);
const error = ref('');
const events = ref([]);

async function removeSaved (eventId) {
  try {
    await deleteJson(`/api/saved-events/${encodeURIComponent(eventId)}`);
    const list = events.value;
    const next = [];
    for (let i = 0; i < list.length; i++) {
      if (list[i].id !== eventId) {
        next.push(list[i]);
      }
    }
    events.value = next;
  } catch (e) {
    console.error(e);
  }
}

onMounted(async () => {
  loading.value = true;
  error.value = '';
  try {
    const data = await getJson('/api/saved-events');
    events.value = data.events || [];
  } catch (e) {
    if (e?.status === 401) {
      navigateTo('/login');
      return;
    }
    error.value = 'No s\'han pogut carregar els guardats.';
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.saved {
  max-width: 42rem;
  margin: 0 auto;
}

.saved__cards {
  list-style: none;
  padding: 0;
  margin: 1rem 0 0;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.saved__card-item {
  margin: 0;
}

.saved__muted {
  color: var(--fg-muted);
}

.saved__err {
  color: #ff6b6b;
}
</style>
