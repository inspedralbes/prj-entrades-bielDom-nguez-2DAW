<template>
  <div class="adm-logs">
    <header class="adm-logs__hero">
      <div class="adm-logs__hero-text">
        <h1 class="adm-logs__title">
          Registre d’accions
        </h1>
        <p class="adm-logs__lead">
          Historial d’operacions dels administradors al panell (REST, sense GraphQL).
        </p>
      </div>
    </header>

    <div class="adm-logs__table-shell">
      <div class="adm-logs__table-head">
        <div class="adm-logs__table-head-left">
          <span class="adm-logs__table-head-title">Registre d’auditoria</span>
        </div>
      </div>

      <div class="adm-logs__table-scroll">
        <p v-if="err" class="adm-logs__err adm-logs__table-pad">
          {{ err }}
        </p>
        <p v-else-if="loading" class="adm-logs__muted adm-logs__table-pad">
          Carregant…
        </p>
        <table v-else-if="listRows.length > 0" class="adm-logs__table" aria-label="Registre d’accions">
          <thead>
            <tr>
              <th scope="col">
                Data i hora
              </th>
              <th scope="col">
                Administrador
              </th>
              <th scope="col">
                Adreça IP
              </th>
              <th scope="col">
                Resum
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in listRows" :key="'log-' + row.id" class="adm-logs__row">
              <td>
                <p class="adm-logs__date-main">
                  {{ formatLogDate(row.date) }}
                </p>
                <p class="adm-logs__date-sub">
                  {{ row.time }}
                </p>
              </td>
              <td class="adm-logs__cell-strong">
                {{ row.admin_name }}
              </td>
              <td>
                {{ row.ip_address }}
              </td>
              <td class="adm-logs__summary">
                {{ row.summary }}
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="adm-logs__muted adm-logs__table-pad">
          No hi ha registres.
        </p>
      </div>

      <div v-if="!loading && listPayload" class="adm-logs__pager">
        <p class="adm-logs__pager-info">
          Mostrant
          <span class="adm-logs__pager-strong">{{ pageRangeText }}</span>
          de {{ listTotal }} registres
        </p>
        <div class="adm-logs__pager-btns">
          <button
            type="button"
            class="adm-logs__page-btn adm-logs__page-btn--fletxa"
            :disabled="listPage <= 1"
            aria-label="Pàgina anterior"
            @click="goPage(listPage - 1)"
          >
            <svg
              class="adm-logs__fletxa-svg"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true"
            >
              <path
                d="M15 6l-6 6 6 6"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </button>
          <button
            v-for="pi in pageNumbers"
            :key="'p-' + pi"
            type="button"
            class="adm-logs__page-btn"
            :class="{ 'adm-logs__page-btn--on': pi === listPage }"
            @click="goPage(pi)"
          >
            {{ pi }}
          </button>
          <button
            type="button"
            class="adm-logs__page-btn adm-logs__page-btn--fletxa"
            :disabled="listPage >= listLastPage"
            aria-label="Pàgina següent"
            @click="goPage(listPage + 1)"
          >
            <svg
              class="adm-logs__fletxa-svg"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true"
            >
              <path
                d="M9 6l6 6-6 6"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const { getJson } = useAuthorizedApi();

const loading = ref(false);
const err = ref('');
const listPayload = ref(null);
const listPage = ref(1);

const listRows = computed(() => {
  const p = listPayload.value;
  if (!p || !p.data) {
    return [];
  }
  const d = p.data;
  const out = [];
  for (let i = 0; i < d.length; i++) {
    out.push(d[i]);
  }
  return out;
});

const listTotal = computed(() => {
  const p = listPayload.value;
  if (!p || !p.meta || p.meta.total === undefined || p.meta.total === null) {
    return 0;
  }
  return Number(p.meta.total);
});

const listLastPage = computed(() => {
  const p = listPayload.value;
  if (!p || !p.meta || p.meta.last_page === undefined || p.meta.last_page === null) {
    return 1;
  }
  const n = Number(p.meta.last_page);
  if (Number.isNaN(n) || n < 1) {
    return 1;
  }
  return n;
});

const pageRangeText = computed(() => {
  const p = listPayload.value;
  if (!p || !p.meta) {
    return '—';
  }
  const m = p.meta;
  const total = Number(m.total);
  if (Number.isNaN(total) || total === 0) {
    return '—';
  }
  const cur = Number(m.current_page);
  const per = Number(m.per_page);
  if (Number.isNaN(cur) || Number.isNaN(per)) {
    return '—';
  }
  const from = (cur - 1) * per + 1;
  let to = cur * per;
  if (to > total) {
    to = total;
  }
  return `${from}–${to}`;
});

const pageNumbers = computed(() => {
  const last = listLastPage.value;
  const cur = listPage.value;
  const maxBtns = 5;
  const out = [];
  let start = cur - 2;
  if (start < 1) {
    start = 1;
  }
  let end = start + maxBtns - 1;
  if (end > last) {
    end = last;
    start = end - maxBtns + 1;
    if (start < 1) {
      start = 1;
    }
  }
  for (let n = start; n <= end; n++) {
    out.push(n);
  }
  return out;
});

function formatLogDate (ymd) {
  if (!ymd) {
    return '—';
  }
  try {
    const d = new Date(`${ymd}T12:00:00`);
    return d.toLocaleDateString('ca-ES', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  } catch {
    return ymd;
  }
}

async function fetchPage (p) {
  let pg = p;
  if (pg === undefined || pg === null) {
    pg = 1;
  }
  err.value = '';
  loading.value = true;
  try {
    const res = await getJson(`/api/admin/logs?page=${pg}&per_page=10`);
    listPayload.value = res;
    if (res.meta && res.meta.current_page !== undefined) {
      listPage.value = Number(res.meta.current_page);
    } else {
      listPage.value = pg;
    }
  } catch (e) {
    err.value = 'No s’ha pogut carregar el registre.';
    listPayload.value = null;
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function goPage (n) {
  if (n < 1) {
    return;
  }
  if (n > listLastPage.value) {
    return;
  }
  await fetchPage(n);
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
  box-sizing: border-box;
  width: 100%;
  max-width: 90rem;
  margin: 0 auto;
  padding-bottom: 2rem;
  font-family: Inter, system-ui, sans-serif;
  color: #e5e2e1;
}

.adm-logs__hero {
  margin-bottom: 2.5rem;
}

.adm-logs__title {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 900;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  color: #f7e628;
}

.adm-logs__lead {
  margin: 0.5rem 0 0;
  max-width: 40rem;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #ccc7ac;
}

.adm-logs__table-shell {
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
  overflow: hidden;
}

.adm-logs__table-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(74, 71, 51, 0.35);
  background: rgba(42, 42, 42, 0.35);
}

.adm-logs__table-head-left {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
}

.adm-logs__table-head-title {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}

.adm-logs__table-scroll {
  overflow-x: auto;
}

.adm-logs__table-pad {
  padding: 1.5rem;
}

.adm-logs__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.adm-logs__table thead th {
  padding: 1rem 1.5rem;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  text-align: left;
  color: #ccc7ac;
  border-bottom: 1px solid rgba(74, 71, 51, 0.25);
}

.adm-logs__row {
  border-bottom: 1px solid rgba(74, 71, 51, 0.15);
  transition: background 0.15s ease;
}

.adm-logs__row:hover {
  background: rgba(255, 255, 255, 0.02);
}

.adm-logs__row td {
  padding: 1.1rem 1.5rem;
  vertical-align: top;
  color: #e5e2e1;
}

.adm-logs__date-main {
  margin: 0;
  font-weight: 600;
}

.adm-logs__date-sub {
  margin: 0.2rem 0 0;
  font-size: 0.7rem;
  color: #ccc7ac;
  font-variant-numeric: tabular-nums;
}

.adm-logs__cell-strong {
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 700;
  color: #fff;
}

.adm-logs__summary {
  max-width: 28rem;
  line-height: 1.45;
  color: rgba(255, 255, 255, 0.88);
}

.adm-logs__pager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid rgba(74, 71, 51, 0.25);
}

.adm-logs__pager-info {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.45;
  color: #ccc7ac;
}

.adm-logs__pager-strong {
  color: #fff;
  font-weight: 800;
}

.adm-logs__pager-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}

.adm-logs__page-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.5rem;
  height: 2.5rem;
  padding: 0 0.5rem;
  border: 1px solid rgba(149, 145, 120, 0.25);
  border-radius: 9999px;
  background: transparent;
  color: #e5e2e1;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s ease;
}

.adm-logs__page-btn:hover:not(:disabled) {
  background: #2a2a2a;
}

.adm-logs__page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.adm-logs__page-btn--on {
  border-color: #f7e628;
  background: #f7e628;
  color: #000;
}

.adm-logs__page-btn--fletxa {
  min-width: 2.75rem;
  padding: 0;
  font-family: inherit;
}

.adm-logs__fletxa-svg {
  display: block;
  width: 1.15rem;
  height: 1.15rem;
  color: inherit;
}

.adm-logs__err {
  color: #ffb4ab;
  font-size: 0.9rem;
}

.adm-logs__muted {
  color: rgba(255, 255, 255, 0.45);
  font-size: 0.9rem;
}
</style>
