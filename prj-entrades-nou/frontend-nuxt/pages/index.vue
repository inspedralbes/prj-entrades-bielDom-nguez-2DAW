<template>
  <div class="home">
    <p v-if="error" class="home__err">{{ error }}</p>
    <p v-else-if="loading" class="home__muted">Carregant…</p>

    <template v-else>
      <section class="home__section" aria-labelledby="h-featured">
        <h2 id="h-featured" class="home__h2">Destacats</h2>
        <ul v-if="featured.length" class="home__list">
          <li v-for="ev in featured" :key="ev.id" class="home__card">
            <NuxtLink :to="`/events/${ev.id}/seats`" class="home__link">
              <span class="home__name">{{ ev.name }}</span>
              <span class="home__meta">{{ formatDate(ev.starts_at) }} · {{ ev.venue?.name || '—' }}</span>
            </NuxtLink>
          </li>
        </ul>
        <p v-else class="home__muted">Sense esdeveniments destacats.</p>
      </section>

      <section v-if="auth.token" class="home__section" aria-labelledby="h-foryou">
        <h2 id="h-foryou" class="home__h2">Triats per a tu</h2>
        <ul v-if="forYou.length" class="home__list">
          <li v-for="ev in forYou" :key="'fy-'+ev.id" class="home__card">
            <NuxtLink :to="`/events/${ev.id}/seats`" class="home__link">
              <span class="home__name">{{ ev.name }}</span>
              <span class="home__meta">{{ formatDate(ev.starts_at) }} · {{ ev.venue?.name || '—' }}</span>
            </NuxtLink>
          </li>
        </ul>
        <p v-else class="home__muted">Inicia sessió i configura preferències per veure recomanacions.</p>
      </section>

      <section v-else class="home__section">
        <p class="home__muted">
          <NuxtLink to="/login" class="home__inline">Inicia sessió</NuxtLink>
          per veure «Triats per a tu» (Gemini stub + historial).
        </p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'default',
});

const config = useRuntimeConfig();
const auth = useAuthStore();
const { getJson } = useAuthorizedApi();

const loading = ref(true);
const error = ref('');
const featured = ref([]);
const forYou = ref([]);

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
    const base = (config.public.apiUrl || '').replace(/\/$/, '');
    const f = await $fetch(`${base}/api/feed/featured`);
    featured.value = f.events || [];
    if (auth.token) {
      const fy = await getJson('/api/feed/for-you');
      forYou.value = fy.events || [];
    }
  } catch (e) {
    error.value = 'No s’ha pogut carregar el feed.';
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.home {
  padding: 0 1rem 2rem;
  max-width: 48rem;
  margin: 0 auto;
}
.home__h2 {
  color: #ff0055;
  font-size: 1.1rem;
  margin: 0 0 0.75rem;
}
.home__section {
  margin-bottom: 2rem;
}
.home__list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.home__card {
  margin-bottom: 0.5rem;
}
.home__link {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem 1rem;
  background: #161616;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
  text-decoration: none;
  color: #f5f5f5;
}
.home__link:hover {
  border-color: #444;
}
.home__name {
  font-weight: 600;
}
.home__meta {
  font-size: 0.85rem;
  color: #888;
}
.home__muted {
  color: #888;
}
.home__err {
  color: #ff6b6b;
}
.home__inline {
  color: #ff0055;
}
</style>
