<template>
  <div class="saved">
    <h1 class="saved__h1">Guardats</h1>
    <p v-if="error" class="saved__err">{{ error }}</p>
    <p v-else-if="loading" class="saved__muted">Carregant…</p>
    <ul v-else-if="events.length" class="saved__list">
      <li v-for="ev in events" :key="ev.id" class="saved__item">
        <NuxtLink :to="`/events/${ev.id}/seats`" class="saved__link">{{ ev.name }}</NuxtLink>
        <p class="saved__meta">{{ formatDate(ev.starts_at) }} · {{ ev.venue?.name || '—' }}</p>
      </li>
    </ul>
    <p v-else class="saved__muted">No tens esdeveniments guardats.</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const { getJson } = useAuthorizedApi();
const loading = ref(true);
const error = ref('');
const events = ref([]);

function formatDate (iso) {
  if (!iso) {
    return '';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES', { dateStyle: 'medium', timeStyle: 'short' });
  } catch {
    return iso;
  }
}

onMounted(async () => {
  loading.value = true;
  error.value = '';
  try {
    const data = await getJson('/api/saved-events');
    events.value = data.events || [];
  } catch (e) {
    error.value = 'No s’han pogut carregar els guardats.';
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.saved {
  padding: 0 1rem 2rem;
  max-width: 42rem;
  margin: 0 auto;
}
.saved__h1 {
  color: #ff0055;
  font-size: 1.35rem;
}
.saved__list {
  list-style: none;
  padding: 0;
  margin: 1rem 0 0;
}
.saved__item {
  padding: 0.75rem 0;
  border-bottom: 1px solid #222;
}
.saved__link {
  font-weight: 600;
  color: #fff;
  text-decoration: none;
}
.saved__link:hover {
  color: #ff0055;
}
.saved__meta {
  margin: 0.35rem 0 0;
  font-size: 0.85rem;
  color: #888;
}
.saved__muted {
  color: #888;
}
.saved__err {
  color: #ff6b6b;
}
</style>
