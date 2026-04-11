<template>
  <div class="search">
    <NuxtLink to="/search/map" class="search__fab" title="Mapa">Mapa</NuxtLink>

    <form class="search__form" @submit.prevent="runSearch">
      <label class="search__label">
        Text
        <input v-model="q" type="search" class="search__input" placeholder="Nom de l’esdeveniment">
      </label>
      <label class="search__label">
        Categoria
        <input v-model="category" type="text" class="search__input" placeholder="opcional">
      </label>
      <div class="search__row">
        <label class="search__label">
          Des de
          <input v-model="dateFrom" type="date" class="search__input">
        </label>
        <label class="search__label">
          Fins
          <input v-model="dateTo" type="date" class="search__input">
        </label>
      </div>
      <button type="submit" class="search__btn" :disabled="loading">
        Cercar
      </button>
    </form>

    <p v-if="error" class="search__err">{{ error }}</p>
    <p v-else-if="loading" class="search__muted">Carregant…</p>

    <ul v-else class="search__list">
      <li v-for="ev in events" :key="ev.id" class="search__item">
        <div class="search__main">
          <NuxtLink :to="`/events/${ev.id}/seats`" class="search__title">{{ ev.name }}</NuxtLink>
          <p class="search__meta">{{ formatDate(ev.starts_at) }} · {{ ev.venue?.name || '—' }}</p>
        </div>
        <button
          v-if="auth.token"
          type="button"
          class="search__heart"
          :aria-pressed="savedIds.has(ev.id)"
          @click="toggleSaved(ev.id)"
        >
          {{ savedIds.has(ev.id) ? '♥' : '♡' }}
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useApi } from '~/composables/useApi';

definePageMeta({
  layout: 'default',
});

const auth = useAuthStore();
const { fetchApi } = useApi();
const { getJson, postJson, deleteJson } = useAuthorizedApi();

const q = ref('');
const category = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const loading = ref(false);
const error = ref('');
const events = ref([]);
const savedIds = ref(new Set());

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

async function loadSaved () {
  if (!auth.token) {
    return;
  }
  try {
    const data = await getJson('/api/saved-events');
    const ids = new Set((data.events || []).map((e) => e.id));
    savedIds.value = ids;
  } catch {
    /* ignore */
  }
}

async function runSearch () {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams();
    if (q.value) {
      params.set('q', q.value);
    }
    if (category.value) {
      params.set('category', category.value);
    }
    if (dateFrom.value) {
      params.set('date_from', dateFrom.value);
    }
    if (dateTo.value) {
      params.set('date_to', dateTo.value);
    }
    const qs = params.toString();
    const path = `/api/search/events${qs ? `?${qs}` : ''}`;
    const data = await fetchApi(path);
    events.value = data.events || [];
  } catch (e) {
    error.value = 'Error de cerca.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function toggleSaved (eventId) {
  if (!auth.token) {
    return;
  }
  try {
    if (savedIds.value.has(eventId)) {
      await deleteJson(`/api/saved-events/${eventId}`);
      const next = new Set(savedIds.value);
      next.delete(eventId);
      savedIds.value = next;
    } else {
      await postJson('/api/saved-events', { event_id: eventId });
      const next = new Set(savedIds.value);
      next.add(eventId);
      savedIds.value = next;
    }
  } catch (e) {
    console.error(e);
  }
}

onMounted(async () => {
  await loadSaved();
  await runSearch();
});
</script>

<style scoped>
.search {
  position: relative;
  padding: 0 1rem 2rem;
  max-width: 42rem;
  margin: 0 auto;
}
.search__fab {
  position: fixed;
  right: 1rem;
  bottom: calc(56px + 1rem);
  z-index: 50;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 50%;
  background: #ff0055;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  font-size: 0.75rem;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
}
@media (min-width: 900px) {
  .search__fab {
    bottom: 1.5rem;
  }
}
.search__form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
.search__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: #aaa;
}
.search__input {
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  border: 1px solid #333;
  background: #111;
  color: #fff;
}
.search__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}
.search__btn {
  align-self: flex-start;
  padding: 0.55rem 1.2rem;
  background: #ff0055;
  border: none;
  border-radius: 6px;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
.search__btn:disabled {
  opacity: 0.6;
}
.search__list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.search__item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 0;
  border-bottom: 1px solid #222;
}
.search__title {
  font-weight: 600;
  color: #fff;
  text-decoration: none;
}
.search__title:hover {
  color: #ff0055;
}
.search__meta {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  color: #888;
}
.search__heart {
  flex-shrink: 0;
  background: transparent;
  border: none;
  font-size: 1.35rem;
  cursor: pointer;
  line-height: 1;
  color: #ff0055;
}
.search__muted {
  color: #888;
}
.search__err {
  color: #ff6b6b;
}
</style>
