<template>
  <div class="adm-ev">
    <header class="adm-ev__hero">
      <div class="adm-ev__hero-text">
        <div class="admin-page-hero">
          <h1 class="admin-page-title">
            Gestió d’esdeveniments
          </h1>
          <p class="admin-page-lead">
            Monitoritza, actualitza i gestiona el catàleg d’esdeveniments des d’un sol panell.
          </p>
        </div>
      </div>
      <div class="adm-ev__hero-actions">
        <button
          type="button"
          class="adm-ev__btn-sync"
          :disabled="syncPending"
          @click="runDiscoverySync"
        >
          Sincronitzar TM
        </button>
        <button type="button" class="admin-cta-create" @click="openCreateModal">
          <span class="admin-cta-create__mes" aria-hidden="true">
            <svg class="admin-cta-create__svg" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M6 1.5v9M1.5 6h9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
          </span>
          <span>Crear esdeveniment</span>
        </button>
      </div>
    </header>
    <p v-if="syncErr" class="adm-ev__sync-err">
      {{ syncErr }}
    </p>

    <div class="adm-ev__bento">
      <div class="adm-ev__stat">
        <p class="adm-ev__stat-label">
          Esdeveniments actius
        </p>
        <p v-if="metricsPending" class="adm-ev__stat-muted">
          …
        </p>
        <template v-else>
          <h2 class="adm-ev__stat-value">
            {{ metricsDisplay.active }}
          </h2>
          <p class="adm-ev__stat-foot">
            Visibles al catàleg amb data d’inici futura.
          </p>
        </template>
      </div>
      <div class="adm-ev__stat">
        <p class="adm-ev__stat-label">
          Volum de vendes
        </p>
        <p v-if="metricsPending" class="adm-ev__stat-muted">
          …
        </p>
        <template v-else>
          <h2 class="adm-ev__stat-value adm-ev__stat-value--accent">
            {{ metricsDisplay.salesCompact }}
          </h2>
          <p class="adm-ev__stat-foot">
            Ingressos acumulats (comandes pagades).
          </p>
        </template>
      </div>
    </div>

    <section class="adm-ev__disc" aria-label="Discovery Ticketmaster">
      <h2 class="adm-ev__disc-title">
        Discovery (Ticketmaster)
      </h2>
      <div class="adm-ev__disc-row">
        <label class="adm-ev__sr-only" for="disc-q">Paraula clau</label>
        <input
          id="disc-q"
          v-model="discKeyword"
          type="search"
          class="adm-ev__disc-input"
          placeholder="Cercar a Discovery…"
          @keydown.enter.prevent="runDiscoverySearch"
        >
        <button type="button" class="adm-ev__disc-btn" :disabled="discPending" @click="runDiscoverySearch">
          Cercar
        </button>
      </div>
      <p v-if="discErr" class="adm-ev__err">
        {{ discErr }}
      </p>
      <p v-else-if="discPending" class="adm-ev__muted">
        Cercant…
      </p>
      <ul v-else-if="discResults.length > 0" class="adm-ev__disc-list">
        <li v-for="(ev, idx) in discResults" :key="'d-' + idx" class="adm-ev__disc-item">
          <span class="adm-ev__disc-name">{{ discoveryLabel(ev) }}</span>
          <span class="adm-ev__disc-id">{{ discoveryId(ev) }}</span>
          <button type="button" class="adm-ev__disc-import" @click="importDiscovery(discoveryId(ev))">
            Importar
          </button>
        </li>
      </ul>
      <p v-else-if="discSearched" class="adm-ev__muted">
        Sense resultats.
      </p>
    </section>

    <div class="adm-ev__table-shell">
      <div class="adm-ev__table-head">
        <div class="adm-ev__table-head-left">
          <span class="adm-ev__table-head-title">Inventari</span>
        </div>
        <div class="adm-ev__table-head-filters">
          <div class="adm-ev__chips" role="tablist" aria-label="Filtre de visibilitat">
            <button
              type="button"
              class="adm-ev__filter-chip"
              :class="{ 'adm-ev__filter-chip--on': eventFilter === 'all' }"
              role="tab"
              :aria-selected="eventFilter === 'all'"
              @click="setEventFilter('all')"
            >
              Tots
            </button>
            <button
              type="button"
              class="adm-ev__filter-chip"
              :class="{ 'adm-ev__filter-chip--on': eventFilter === 'visible' }"
              role="tab"
              :aria-selected="eventFilter === 'visible'"
              @click="setEventFilter('visible')"
            >
              Publicats
            </button>
            <button
              type="button"
              class="adm-ev__filter-chip"
              :class="{ 'adm-ev__filter-chip--on': eventFilter === 'hidden' }"
              role="tab"
              :aria-selected="eventFilter === 'hidden'"
              @click="setEventFilter('hidden')"
            >
              Ocults
            </button>
          </div>
        </div>
      </div>

      <div class="adm-ev__table-scroll">
        <p v-if="listErr" class="adm-ev__err adm-ev__table-pad">
          {{ listErr }}
        </p>
        <p v-else-if="listPending" class="adm-ev__muted adm-ev__table-pad">
          Carregant…
        </p>
        <table v-else class="adm-ev__table" aria-label="Esdeveniments">
          <thead>
            <tr>
              <th scope="col">
                Esdeveniment
              </th>
              <th scope="col">
                Data
              </th>
              <th scope="col">
                Lloc
              </th>
              <th scope="col">
                Estat
              </th>
              <th scope="col" class="adm-ev__th-num">
                Preu (€)
              </th>
              <th scope="col" class="adm-ev__th-actions">
                Accions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in listRows" :key="'ev-' + row.id" class="adm-ev__row">
              <td class="adm-ev__cell-detail">
                <div class="adm-ev__detail">
                  <div class="adm-ev__thumb-wrap">
                    <img
                      v-if="row.image_url"
                      class="adm-ev__thumb-img"
                      :src="row.image_url"
                      :alt="row.name || 'Esdeveniment'"
                    >
                    <div v-else class="adm-ev__thumb-ph" aria-hidden="true" />
                  </div>
                  <div>
                    <p class="adm-ev__ev-name">
                      {{ row.name }}
                    </p>
                    <p class="adm-ev__ev-id">
                      ID {{ row.id }} · {{ row.external_tm_id || '—' }}
                    </p>
                  </div>
                </div>
              </td>
              <td>
                <p class="adm-ev__date-main">
                  {{ formatDateShort(row.starts_at) }}
                </p>
                <p class="adm-ev__date-sub">
                  {{ formatTimeTz(row.starts_at) }}
                </p>
              </td>
              <td>
                <p class="adm-ev__venue-main">
                  {{ venueLine(row).name }}
                </p>
                <p class="adm-ev__venue-sub">
                  {{ venueLine(row).sub }}
                </p>
              </td>
              <td>
                <span v-if="row.hidden_at" class="adm-ev__badge adm-ev__badge--muted">Ocult</span>
                <span v-else-if="isPastStart(row.starts_at)" class="adm-ev__badge adm-ev__badge--past">Passat</span>
                <span v-else class="adm-ev__badge adm-ev__badge--live">En venda</span>
              </td>
              <td class="adm-ev__cell-num">
                {{ formatPriceCell(row.price) }}
              </td>
              <td class="adm-ev__cell-actions">
                <div class="adm-ev__accions">
                  <NuxtLink
                    class="adm-ev__accio-ico"
                    :to="`/admin/events/${row.id}/monitor`"
                    aria-label="Obrir monitor de l’esdeveniment"
                    title="Monitor"
                  >
                    <svg class="adm-ev__ico" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path
                        d="M4 5h16v10H4V5zM8 19h8M12 15v4"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </NuxtLink>
                  <button
                    type="button"
                    class="adm-ev__accio-ico"
                    aria-label="Editar esdeveniment"
                    title="Editar"
                    @click="startEdit(row)"
                  >
                    <svg class="adm-ev__ico" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path
                        d="M12 20h9M4 20h2.5l10.5-10.5a2.1 2.1 0 0 0-3-3L4 17V20zM13 7l4 4"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="adm-ev__accio-ico adm-ev__accio-ico--perill"
                    aria-label="Ocultar esdeveniment"
                    title="Ocultar"
                    @click="hideEvent(row.id)"
                  >
                    <svg class="adm-ev__ico" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path
                        d="M10.7 5.1A10.7 10.7 0 0 1 21.9 11.7a1 1 0 0 1 0 .6 10.7 10.7 0 0 1-1.4 2.5M14.1 14.2a3 3 0 0 1-4.2-4.2M17.5 17.5a10.8 10.8 0 0 1-15.4-5.2 1 1 0 0 1 0-.6 10.8 10.8 0 0 1 4.4-5.2"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                      <path
                        d="M2 2l20 20"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                      />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!listPending && listPayload" class="adm-ev__pager">
        <p class="adm-ev__pager-info">
          Mostrant
          <span class="adm-ev__pager-strong">{{ pageRangeText }}</span>
          de {{ listTotal }} esdeveniments
        </p>
        <div class="adm-ev__pager-btns">
          <button
            type="button"
            class="adm-ev__page-btn adm-ev__page-btn--fletxa"
            :disabled="listPage <= 1"
            aria-label="Pàgina anterior"
            @click="goPage(listPage - 1)"
          >
            <svg
              class="adm-ev__fletxa-svg"
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
            class="adm-ev__page-btn"
            :class="{ 'adm-ev__page-btn--on': pi === listPage }"
            @click="goPage(pi)"
          >
            {{ pi }}
          </button>
          <button
            type="button"
            class="adm-ev__page-btn adm-ev__page-btn--fletxa"
            :disabled="listPage >= listLastPage"
            aria-label="Pàgina següent"
            @click="goPage(listPage + 1)"
          >
            <svg
              class="adm-ev__fletxa-svg"
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

    <Teleport to="body">
      <div
        v-if="createOpen"
        class="admin-modal-root"
        role="dialog"
        aria-modal="true"
        aria-labelledby="adm-ev-create-title"
      >
        <div class="admin-modal-backdrop" @click="closeCreateModal" />
        <div class="admin-modal-panel admin-modal-panel--wide" @click.stop>
          <button type="button" class="admin-modal-close" aria-label="Tancar" @click="closeCreateModal">
            <span class="adm-ev__tanca-x" aria-hidden="true">×</span>
          </button>
          <h2 id="adm-ev-create-title" class="admin-modal-title">
            Nou esdeveniment
          </h2>
          <p class="admin-modal-lead">
            Omple els camps obligatoris. El <code class="adm-ev__code">venue_id</code> ha d’existir a la taula <code class="adm-ev__code">venues</code> (sovint <code class="adm-ev__code">1</code> en dev).
          </p>
          <p v-if="createErr" class="admin-form-err">
            {{ createErr }}
          </p>
          <div class="admin-form-stack">
            <div class="admin-form-field">
              <label class="admin-form-label" for="cext">external_tm_id (únic)</label>
              <input id="cext" v-model="createForm.external_tm_id" type="text" class="admin-form-input" autocomplete="off">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="cname">Nom</label>
              <input id="cname" v-model="createForm.name" type="text" class="admin-form-input" autocomplete="off">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="cvenue">venue_id</label>
              <input id="cvenue" v-model.number="createForm.venue_id" type="number" min="1" class="admin-form-input">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="cstart">Data i hora d’inici</label>
              <input id="cstart" v-model="createForm.starts_at" type="datetime-local" class="admin-form-input">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="cprice">Preu (EUR, opcional)</label>
              <input id="cprice" v-model.number="createForm.price" type="number" min="0.01" step="0.01" class="admin-form-input">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="ccat">Categoria (opcional)</label>
              <input id="ccat" v-model="createForm.category" type="text" class="admin-form-input" autocomplete="off">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="cimg">URL imatge (opcional)</label>
              <input id="cimg" v-model="createForm.image_url" type="url" class="admin-form-input" autocomplete="off">
            </div>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="admin-btn-primary" :disabled="createPending" @click="submitCreate">
              Crear
            </button>
            <button type="button" class="admin-btn-ghost" @click="closeCreateModal">
              Cancel·lar
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="editId !== null"
        class="admin-modal-root"
        role="dialog"
        aria-modal="true"
        aria-labelledby="adm-ev-edit-title"
      >
        <div class="admin-modal-backdrop" @click="cancelEdit" />
        <div class="admin-modal-panel admin-modal-panel--wide" @click.stop>
          <button type="button" class="admin-modal-close" aria-label="Tancar" @click="cancelEdit">
            <span class="adm-ev__tanca-x" aria-hidden="true">×</span>
          </button>
          <h2 id="adm-ev-edit-title" class="admin-modal-title">
            Editar #{{ editId }}
          </h2>
          <p class="admin-modal-lead">
            Desa els canvis per actualitzar l’esdeveniment.
          </p>
          <p v-if="editErr" class="admin-form-err">
            {{ editErr }}
          </p>
          <div class="admin-form-stack">
            <div class="admin-form-field">
              <label class="admin-form-label" for="ename">Nom</label>
              <input id="ename" v-model="editForm.name" type="text" class="admin-form-input" autocomplete="off">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="estart">Data i hora d’inici</label>
              <input id="estart" v-model="editForm.starts_at" type="datetime-local" class="admin-form-input">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="evenue">venue_id</label>
              <input id="evenue" v-model.number="editForm.venue_id" type="number" min="1" class="admin-form-input">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="eprice">Preu</label>
              <input id="eprice" v-model.number="editForm.price" type="number" min="0.01" step="0.01" class="admin-form-input">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="ecat">Categoria</label>
              <input id="ecat" v-model="editForm.category" type="text" class="admin-form-input" autocomplete="off">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="eimg">URL imatge</label>
              <input id="eimg" v-model="editForm.image_url" type="url" class="admin-form-input" autocomplete="off">
            </div>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="admin-btn-primary" :disabled="editPending" @click="submitEdit">
              Desar
            </button>
            <button type="button" class="admin-btn-ghost" @click="cancelEdit">
              Cancel·lar
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const { getJson, postJson, patchJson, deleteJson } = useAuthorizedApi();

const metricsPending = ref(true);
const metrics = ref(null);
const syncPending = ref(false);
const syncErr = ref('');

const listPending = ref(true);
const listErr = ref('');
const listPayload = ref(null);
const listPage = ref(1);
const eventFilter = ref('all');

const discKeyword = ref('');
const discPending = ref(false);
const discErr = ref('');
const discResults = ref([]);
const discSearched = ref(false);

const createOpen = ref(false);
const createPending = ref(false);
const createErr = ref('');
const createForm = reactive({
  external_tm_id: '',
  name: '',
  venue_id: 1,
  starts_at: '',
  price: null,
  image_url: '',
  category: '',
});

const editId = ref(null);
const editPending = ref(false);
const editErr = ref('');
const editForm = reactive({
  name: '',
  starts_at: '',
  venue_id: 1,
  price: 0,
  image_url: '',
  category: '',
});

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
  if (!p || p.total === undefined || p.total === null) {
    return 0;
  }
  return Number(p.total);
});

const listLastPage = computed(() => {
  const p = listPayload.value;
  if (!p || p.last_page === undefined || p.last_page === null) {
    return 1;
  }
  const n = Number(p.last_page);
  if (Number.isNaN(n) || n < 1) {
    return 1;
  }
  return n;
});

const pageRangeText = computed(() => {
  const p = listPayload.value;
  if (!p) {
    return '—';
  }
  const from = p.from;
  const to = p.to;
  if (from === undefined || to === undefined || from === null || to === null) {
    return '—';
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

const metricsDisplay = computed(() => {
  const m = metrics.value;
  let active = '—';
  let salesCompact = '—';
  if (m) {
    active = String(m.active_events_count);
    salesCompact = formatCompactEur(m.sales_volume_eur);
  }
  return {
    active,
    salesCompact,
  };
});

function formatCompactEur (eurStr) {
  const n = Number(eurStr);
  if (Number.isNaN(n)) {
    return '€0,00';
  }
  if (n >= 1000000) {
    const v = n / 1000000;
    return `€${v.toFixed(2)}M`;
  }
  if (n >= 1000) {
    const v = n / 1000;
    return `€${v.toFixed(1)}k`;
  }
  return `€${n.toFixed(2)}`;
}

function formatPriceCell (p) {
  if (p === undefined || p === null || p === '') {
    return '—';
  }
  const n = Number(p);
  if (Number.isNaN(n)) {
    return '—';
  }
  return n.toFixed(2);
}

function formatDateShort (iso) {
  if (!iso) {
    return '—';
  }
  try {
    const d = new Date(iso);
    return d.toLocaleDateString('ca-ES', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  } catch {
    return '—';
  }
}

function formatTimeTz (iso) {
  if (!iso) {
    return '';
  }
  try {
    const d = new Date(iso);
    return d.toLocaleTimeString('ca-ES', {
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return '';
  }
}

function venueLine (row) {
  const v = row.venue;
  if (!v) {
    return {
      name: `Venue #${row.venue_id}`,
      sub: '',
    };
  }
  let sub = '';
  if (v.city) {
    sub = v.city;
  }
  return {
    name: v.name || '—',
    sub,
  };
}

function isPastStart (iso) {
  if (!iso) {
    return false;
  }
  try {
    const t = new Date(iso).getTime();
    return t < Date.now();
  } catch {
    return false;
  }
}

async function loadMetrics () {
  metricsPending.value = true;
  try {
    metrics.value = await getJson('/api/admin/events/metrics');
  } catch (e) {
    console.error(e);
    metrics.value = null;
  } finally {
    metricsPending.value = false;
  }
}

function hiddenQueryPart () {
  if (eventFilter.value === 'all') {
    return '&hidden=include';
  }
  if (eventFilter.value === 'hidden') {
    return '&hidden=only';
  }
  return '';
}

async function loadList (page) {
  let pg = page;
  if (pg === undefined || pg === null) {
    pg = 1;
  }
  listPage.value = pg;
  listErr.value = '';
  listPending.value = true;
  try {
    const hiddenPart = hiddenQueryPart();
    const url = `/api/admin/events?per_page=10&page=${pg}${hiddenPart}`;
    listPayload.value = await getJson(url);
  } catch (e) {
    listErr.value = 'No s’ha pogut carregar la llista.';
    console.error(e);
  } finally {
    listPending.value = false;
  }
}

function setEventFilter (v) {
  eventFilter.value = v;
  loadList(1);
}

function goPage (n) {
  if (n < 1) {
    return;
  }
  if (n > listLastPage.value) {
    return;
  }
  loadList(n);
}

async function runDiscoverySync () {
  syncErr.value = '';
  syncPending.value = true;
  try {
    await postJson('/api/admin/discovery/sync', {});
    await loadMetrics();
    await loadList(listPage.value);
  } catch (e) {
    syncErr.value = 'La sincronització Discovery no ha estat possible.';
    console.error(e);
  } finally {
    syncPending.value = false;
  }
}

function discoveryId (ev) {
  if (ev && ev.id) {
    return String(ev.id);
  }
  return '';
}

function discoveryLabel (ev) {
  if (!ev) {
    return '';
  }
  if (ev.name) {
    return String(ev.name);
  }
  return discoveryId(ev);
}

async function runDiscoverySearch () {
  const kw = discKeyword.value.trim();
  if (kw === '') {
    discErr.value = 'Introdueix una paraula clau.';
    return;
  }
  discErr.value = '';
  discPending.value = true;
  discSearched.value = true;
  try {
    const q = new URLSearchParams();
    q.set('keyword', kw);
    q.set('page', '0');
    q.set('size', '20');
    const res = await getJson(`/api/admin/discovery/search?${q.toString()}`);
    const evs = res.events;
    const out = [];
    if (evs && Array.isArray(evs)) {
      for (let i = 0; i < evs.length; i++) {
        out.push(evs[i]);
      }
    }
    discResults.value = out;
  } catch (e) {
    discErr.value = 'Cerca no disponible o API TM sense clau.';
    discResults.value = [];
    console.error(e);
  } finally {
    discPending.value = false;
  }
}

async function importDiscovery (externalId) {
  if (!externalId) {
    return;
  }
  createErr.value = '';
  try {
    await postJson('/api/admin/discovery/import', { external_tm_id: externalId });
    await loadList(listPage.value);
    await loadMetrics();
  } catch (e) {
    let msg = 'Importació fallida.';
    if (e && e.data && e.data.message) {
      msg = e.data.message;
    }
    createErr.value = msg;
    console.error(e);
  }
}

function toDatetimeLocal (iso) {
  if (!iso) {
    return '';
  }
  try {
    const d = new Date(iso);
    const pad = (n) => {
      let s = String(n);
      if (s.length < 2) {
        s = `0${s}`;
      }
      return s;
    };
    const y = d.getFullYear();
    const m = pad(d.getMonth() + 1);
    const day = pad(d.getDate());
    const h = pad(d.getHours());
    const min = pad(d.getMinutes());
    return `${y}-${m}-${day}T${h}:${min}`;
  } catch {
    return '';
  }
}

function fromDatetimeLocal (s) {
  if (!s) {
    return null;
  }
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) {
    return null;
  }
  return d.toISOString();
}

function openCreateModal () {
  createErr.value = '';
  createOpen.value = true;
}

function closeCreateModal () {
  createOpen.value = false;
}

async function submitCreate () {
  createErr.value = '';
  if (!createForm.external_tm_id.trim() || !createForm.name.trim()) {
    createErr.value = 'external_tm_id i nom són obligatoris.';
    return;
  }
  const starts = fromDatetimeLocal(createForm.starts_at);
  if (!starts) {
    createErr.value = 'Data d’inici invàlida.';
    return;
  }
  createPending.value = true;
  try {
    const body = {
      external_tm_id: createForm.external_tm_id.trim(),
      name: createForm.name.trim(),
      venue_id: createForm.venue_id,
      starts_at: starts,
    };
    if (createForm.price !== null && createForm.price !== '' && !Number.isNaN(Number(createForm.price))) {
      body.price = Number(createForm.price);
    }
    if (createForm.image_url && createForm.image_url.trim() !== '') {
      body.image_url = createForm.image_url.trim();
    }
    if (createForm.category && createForm.category.trim() !== '') {
      body.category = createForm.category.trim();
    }
    await postJson('/api/admin/events', body);
    createForm.external_tm_id = '';
    createForm.name = '';
    createForm.starts_at = '';
    createForm.price = null;
    createForm.image_url = '';
    createForm.category = '';
    createOpen.value = false;
    await loadMetrics();
    await loadList(1);
  } catch (e) {
    let msg = 'No s’ha pogut crear.';
    if (e && e.data && e.data.message) {
      msg = e.data.message;
    }
    createErr.value = msg;
    console.error(e);
  } finally {
    createPending.value = false;
  }
}

function startEdit (row) {
  editErr.value = '';
  editId.value = row.id;
  editForm.name = row.name || '';
  editForm.starts_at = toDatetimeLocal(row.starts_at);
  editForm.venue_id = row.venue_id || 1;
  if (row.price) {
    editForm.price = Number(row.price);
  } else {
    editForm.price = 0;
  }
  editForm.image_url = row.image_url || '';
  editForm.category = row.category || '';
}

function cancelEdit () {
  editId.value = null;
}

async function submitEdit () {
  if (editId.value === null) {
    return;
  }
  editErr.value = '';
  const starts = fromDatetimeLocal(editForm.starts_at);
  if (!starts) {
    editErr.value = 'Data invàlida.';
    return;
  }
  editPending.value = true;
  try {
    await patchJson(`/api/admin/events/${editId.value}`, {
      name: editForm.name,
      starts_at: starts,
      venue_id: editForm.venue_id,
      price: editForm.price,
      image_url: editForm.image_url || null,
      category: editForm.category || null,
    });
    editId.value = null;
    await loadList(listPage.value);
    await loadMetrics();
  } catch (e) {
    let msg = 'No s’ha pogut desar.';
    if (e && e.data && e.data.message) {
      msg = e.data.message;
    }
    editErr.value = msg;
    console.error(e);
  } finally {
    editPending.value = false;
  }
}

async function hideEvent (id) {
  try {
    await deleteJson(`/api/admin/events/${id}`);
    await loadList(listPage.value);
    await loadMetrics();
  } catch (e) {
    console.error(e);
  }
}

onMounted(() => {
  loadMetrics();
  loadList(1);
});
</script>

<style scoped>
.adm-ev {
  box-sizing: border-box;
  width: 100%;
  max-width: 90rem;
  margin: 0 auto;
  padding-bottom: 2rem;
  font-family: Inter, system-ui, sans-serif;
  color: #e5e2e1;
}

.adm-ev__hero {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1.5rem;
  margin-bottom: 2.5rem;
}

.adm-ev__hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: center;
}

.adm-ev__btn-sync {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  border-radius: 9999px;
  border: 1px solid rgba(149, 145, 120, 0.35);
  background: transparent;
  color: #f7e628;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s ease;
}

.adm-ev__btn-sync:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.04);
}

.adm-ev__btn-sync:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.adm-ev__sync-err {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #ffb4ab;
}

.adm-ev__bento {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
  margin-bottom: 2.5rem;
}

@media (min-width: 768px) {
  .adm-ev__bento {
    grid-template-columns: 1fr 1fr;
  }
}

.adm-ev__stat {
  padding: 1.5rem;
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
}

.adm-ev__stat-label {
  margin: 0;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #ccc7ac;
}

.adm-ev__stat-value {
  margin: 0.5rem 0 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 2.25rem;
  font-weight: 900;
  color: #fff;
}

.adm-ev__stat-value--accent {
  color: #fff;
}

.adm-ev__stat-muted {
  margin: 0.5rem 0 0;
  color: rgba(255, 255, 255, 0.35);
}

.adm-ev__stat-foot {
  margin: 0.75rem 0 0;
  font-size: 0.75rem;
  line-height: 1.4;
  color: rgba(255, 255, 255, 0.45);
}

.adm-ev__disc {
  margin-bottom: 2.5rem;
  padding: 1.5rem;
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
}

.adm-ev__disc-title {
  margin: 0 0 1rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
}

.adm-ev__disc-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
}

.adm-ev__disc-input {
  flex: 1;
  min-width: 12rem;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  border: 1px solid rgba(149, 145, 120, 0.25);
  background: #0e0e0e;
  color: #e5e2e1;
  font-size: 0.9rem;
}

.adm-ev__disc-input:focus {
  outline: none;
  border-color: #f7e628;
}

.adm-ev__disc-btn {
  padding: 0.5rem 1.1rem;
  border: none;
  border-radius: 9999px;
  background: #f7e628;
  color: #1f1c00;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.8rem;
  font-weight: 800;
  cursor: pointer;
}

.adm-ev__disc-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.adm-ev__disc-list {
  list-style: none;
  margin: 1rem 0 0;
  padding: 0;
}

.adm-ev__disc-item {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
  padding: 0.65rem 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.adm-ev__disc-name {
  flex: 1;
  min-width: 10rem;
  font-size: 0.9rem;
}

.adm-ev__disc-id {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.35);
}

.adm-ev__disc-import {
  padding: 0.35rem 0.85rem;
  border-radius: 9999px;
  border: 1px solid rgba(247, 230, 40, 0.4);
  background: transparent;
  color: #f7e628;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.adm-ev__table-shell {
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
  overflow: hidden;
}

.adm-ev__table-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(74, 71, 51, 0.35);
  background: rgba(42, 42, 42, 0.35);
}

.adm-ev__table-head-left {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.adm-ev__table-head-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-left: auto;
}

.adm-ev__table-head-title {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}

.adm-ev__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.adm-ev__filter-chip {
  padding: 0.45rem 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.25);
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    background 0.2s ease,
    color 0.2s ease;
}

.adm-ev__filter-chip:hover {
  border-color: rgba(247, 230, 40, 0.25);
  color: rgba(255, 255, 255, 0.85);
}

.adm-ev__filter-chip--on {
  border-color: rgba(247, 230, 40, 0.55);
  background: rgba(247, 230, 40, 0.12);
  color: #f7e628;
}

.adm-ev__table-scroll {
  overflow-x: auto;
}

.adm-ev__table-pad {
  padding: 1.5rem;
}

.adm-ev__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.adm-ev__table thead th {
  padding: 1rem 1.5rem;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  text-align: left;
  color: #ccc7ac;
  border-bottom: 1px solid rgba(74, 71, 51, 0.25);
}

.adm-ev__th-num,
.adm-ev__cell-num {
  text-align: right;
}

.adm-ev__th-actions,
.adm-ev__cell-actions {
  text-align: right;
}

.adm-ev__row {
  border-bottom: 1px solid rgba(74, 71, 51, 0.15);
  transition: background 0.15s ease;
}

.adm-ev__row:hover {
  background: rgba(255, 255, 255, 0.02);
}

.adm-ev__row td {
  padding: 1.25rem 1.5rem;
  vertical-align: middle;
  color: #e5e2e1;
}

.adm-ev__detail {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-width: 14rem;
}

.adm-ev__thumb-wrap {
  width: 3rem;
  height: 3rem;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 0.5rem;
  background: #353534;
}

.adm-ev__thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: grayscale(1);
  transition: filter 0.35s ease;
}

.adm-ev__row:hover .adm-ev__thumb-img {
  filter: grayscale(0);
}

.adm-ev__thumb-ph {
  width: 100%;
  height: 100%;
  min-height: 3rem;
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0) 50%),
    #353534;
}

.adm-ev__ev-name {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  color: #fff;
}

.adm-ev__ev-id {
  margin: 0.2rem 0 0;
  font-size: 0.7rem;
  color: rgba(255, 255, 255, 0.35);
}

.adm-ev__date-main {
  margin: 0;
  font-weight: 600;
}

.adm-ev__date-sub {
  margin: 0.2rem 0 0;
  font-size: 0.7rem;
  color: #ccc7ac;
  text-transform: uppercase;
}

.adm-ev__venue-main {
  margin: 0;
  font-weight: 600;
}

.adm-ev__venue-sub {
  margin: 0.2rem 0 0;
  font-size: 0.75rem;
  color: #ccc7ac;
}

.adm-ev__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.65rem;
  border-radius: 9999px;
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.adm-ev__badge--live {
  border: 1px solid rgba(34, 197, 94, 0.35);
  background: rgba(34, 197, 94, 0.12);
  color: #4ade80;
}

.adm-ev__badge--past {
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.35);
}

.adm-ev__badge--muted {
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.04);
  color: rgba(255, 255, 255, 0.35);
}

.adm-ev__cell-num {
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 800;
}

.adm-ev__accions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: center;
  gap: 0.4rem;
}

.adm-ev__accio-ico {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  padding: 0;
  border-radius: 9999px;
  border: 1px solid rgba(247, 230, 40, 0.4);
  background: rgba(0, 0, 0, 0.2);
  color: rgba(255, 255, 255, 0.65);
  text-decoration: none;
  cursor: pointer;
  transition:
    background 0.2s ease,
    color 0.2s ease,
    border-color 0.2s ease;
}

.adm-ev__accio-ico:hover {
  background: #f7e628;
  border-color: #f7e628;
  color: #131313;
}

.adm-ev__accio-ico--perill {
  border-color: rgba(255, 180, 171, 0.45);
  color: rgba(255, 180, 171, 0.95);
}

.adm-ev__accio-ico--perill:hover {
  background: #f7e628;
  border-color: #f7e628;
  color: #131313;
}

.adm-ev__ico {
  display: block;
  width: 1.05rem;
  height: 1.05rem;
}

.adm-ev__pager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid rgba(74, 71, 51, 0.25);
}

.adm-ev__pager-info {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.45;
  color: #ccc7ac;
}

.adm-ev__pager-strong {
  color: #fff;
  font-weight: 800;
}

.adm-ev__pager-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}

.adm-ev__page-btn {
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

.adm-ev__page-btn:hover:not(:disabled) {
  background: #2a2a2a;
}

.adm-ev__page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.adm-ev__page-btn--on {
  border-color: #f7e628;
  background: #f7e628;
  color: #000;
}

.adm-ev__page-btn--fletxa {
  min-width: 2.75rem;
  padding: 0;
  font-family: inherit;
}

.adm-ev__fletxa-svg {
  display: block;
  width: 1.15rem;
  height: 1.15rem;
  color: inherit;
}

.adm-ev__tanca-x {
  display: block;
  font-size: 1.35rem;
  font-weight: 400;
  line-height: 1;
}

.adm-ev__err {
  color: #ffb4ab;
  font-size: 0.9rem;
}

.adm-ev__muted {
  color: rgba(255, 255, 255, 0.45);
  font-size: 0.9rem;
}

.adm-ev__code {
  font-size: 0.8em;
  color: #f7e628;
}

.adm-ev__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
</style>
