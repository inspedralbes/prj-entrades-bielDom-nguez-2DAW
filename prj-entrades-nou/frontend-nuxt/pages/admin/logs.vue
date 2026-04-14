<template>
  <div class="adm-logs">
    <div class="adm-logs__head">
      <NuxtLink to="/admin" class="adm-logs__back">← Dashboard</NuxtLink>
      <h1 class="adm-logs__h1">Registre d’accions (administradors)</h1>
      <p class="adm-logs__intro">
        Historial d’operacions registrades al panell (REST, sense GraphQL).
      </p>
    </div>

    <p v-if="err" class="adm-logs__err">{{ err }}</p>

    <ul v-else-if="rows.length > 0" class="adm-logs__list">
      <li v-for="row in rows" :key="'log'+row.id" class="adm-logs__item">
        <span class="adm-logs__main">{{ row.date }} {{ row.time }} — {{ row.admin_name }}</span>
        <span class="adm-logs__ip">IP {{ row.ip_address }}</span>
        <span class="adm-logs__sum">{{ row.summary }}</span>
      </li>
    </ul>

    <p v-else-if="!loading" class="adm-logs__muted">No hi ha registres.</p>
    <p v-if="loading" class="adm-logs__muted">Carregant…</p>

    <div v-if="meta && meta.last_page > 1" class="adm-logs__pager">
      <button
        type="button"
        :disabled="page <= 1 || loading"
        @click="goPage(page - 1)"
      >
        Anterior
      </button>
      <span>Pàgina {{ page }} / {{ meta.last_page }} ({{ meta.total }} registres)</span>
      <button
        type="button"
        :disabled="page >= meta.last_page || loading"
        @click="goPage(page + 1)"
      >
        Següent
      </button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const { getJson } = useAuthorizedApi();

const rows = ref([]);
const meta = ref(null);
const page = ref(1);
const err = ref('');
const loading = ref(false);

async function fetchPage (p) {
  err.value = '';
  loading.value = true;
  try {
    const res = await getJson(`/api/admin/logs?page=${p}&per_page=10`);
    rows.value = res.data;
    meta.value = res.meta;
    page.value = res.meta.current_page;
  } catch (e) {
    err.value = 'No s’ha pogut carregar el registre.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function goPage (p) {
  if (p < 1) {
    return;
  }
  await fetchPage(p);
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

onMounted(() => {
  fetchPage(1);
});
</script>

<style scoped>
.adm-logs {
  max-width: 48rem;
}
.adm-logs__head {
  margin-bottom: 1.25rem;
}
.adm-logs__back {
  display: inline-block;
  margin-bottom: 0.75rem;
  font-size: 0.9rem;
  color: #888;
  text-decoration: none;
}
.adm-logs__back:hover {
  color: #ff0055;
}
.adm-logs__h1 {
  margin: 0 0 0.5rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-logs__intro {
  margin: 0;
  font-size: 0.85rem;
  color: #888;
}
.adm-logs__list {
  list-style: none;
  margin: 0;
  padding: 0;
  background: #111;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
  overflow: hidden;
}
.adm-logs__item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #2a2a2a;
  font-size: 0.9rem;
  color: #ddd;
}
.adm-logs__item:last-child {
  border-bottom: none;
}
.adm-logs__main {
  color: #e8e8e8;
  font-weight: 600;
}
.adm-logs__ip {
  font-size: 0.8rem;
  color: #777;
}
.adm-logs__sum {
  color: #bbb;
  line-height: 1.4;
}
.adm-logs__muted {
  color: #777;
  font-size: 0.9rem;
}
.adm-logs__err {
  color: #ff6b6b;
  margin-bottom: 0.75rem;
}
.adm-logs__pager {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 1.25rem;
  font-size: 0.85rem;
  color: #aaa;
}
.adm-logs__pager button {
  background: #222;
  color: #eee;
  border: 1px solid #444;
  border-radius: 6px;
  padding: 0.4rem 0.75rem;
  cursor: pointer;
}
.adm-logs__pager button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>
