<template>
  <div class="adm-ev">
    <h1 class="adm-ev__h1">Esdeveniments</h1>

    <section class="adm-ev__panel">
      <h2 class="adm-ev__h2">Discovery (Ticketmaster)</h2>
      <div class="adm-ev__row">
        <label class="adm-ev__lbl" for="disc-q">Paraula clau</label>
        <input
          id="disc-q"
          v-model="discKeyword"
          type="search"
          class="adm-ev__input"
          @keydown.enter.prevent="runDiscoverySearch"
        >
        <button type="button" class="adm-ev__btn" :disabled="discPending" @click="runDiscoverySearch">
          Cercar
        </button>
      </div>
      <p v-if="discErr" class="adm-ev__err">{{ discErr }}</p>
      <p v-else-if="discPending" class="adm-ev__muted">Cercant…</p>
      <ul v-else-if="discResults.length > 0" class="adm-ev__disc-list">
        <li v-for="(ev, idx) in discResults" :key="idx" class="adm-ev__disc-item">
          <span class="adm-ev__disc-name">{{ discoveryLabel(ev) }}</span>
          <span class="adm-ev__disc-id">{{ discoveryId(ev) }}</span>
          <button type="button" class="adm-ev__btn adm-ev__btn--sm" @click="importDiscovery(discoveryId(ev))">
            Importar
          </button>
        </li>
      </ul>
      <p v-else-if="discSearched" class="adm-ev__muted">Sense resultats.</p>
    </section>

    <section class="adm-ev__panel">
      <h2 class="adm-ev__h2">Crear manualment</h2>
      <p class="adm-ev__muted">
        <code>venue_id</code>: usa l’ID de la taula <code>venues</code> (dev: sovint <code>1</code>).
      </p>
      <div class="adm-ev__grid">
        <label class="adm-ev__lbl">external_tm_id (únic)</label>
        <input v-model="createForm.external_tm_id" type="text" class="adm-ev__input">
        <label class="adm-ev__lbl">Nom</label>
        <input v-model="createForm.name" type="text" class="adm-ev__input">
        <label class="adm-ev__lbl">venue_id</label>
        <input v-model.number="createForm.venue_id" type="number" min="1" class="adm-ev__input">
        <label class="adm-ev__lbl">Data inici (ISO)</label>
        <input v-model="createForm.starts_at" type="datetime-local" class="adm-ev__input">
        <label class="adm-ev__lbl">Preu (EUR, opcional)</label>
        <input v-model.number="createForm.price" type="number" min="0.01" step="0.01" class="adm-ev__input">
        <label class="adm-ev__lbl">Imatge URL (opcional)</label>
        <input v-model="createForm.image_url" type="url" class="adm-ev__input">
      </div>
      <p v-if="createErr" class="adm-ev__err">{{ createErr }}</p>
      <button type="button" class="adm-ev__btn" :disabled="createPending" @click="submitCreate">
        Crear
      </button>
    </section>

    <section class="adm-ev__panel">
      <h2 class="adm-ev__h2">Llista local</h2>
      <p v-if="listErr" class="adm-ev__err">{{ listErr }}</p>
      <p v-else-if="listPending" class="adm-ev__muted">Carregant…</p>
      <table v-else class="adm-ev__table" aria-label="Esdeveniments">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Data</th>
            <th>Preu</th>
            <th>Ocult</th>
            <th>Accions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in listRows" :key="row.id">
            <td>{{ row.id }}</td>
            <td>{{ row.name }}</td>
            <td>{{ formatIso(row.starts_at) }}</td>
            <td>{{ row.price }}</td>
            <td>{{ row.hidden_at ? 'sí' : 'no' }}</td>
            <td class="adm-ev__actions">
              <NuxtLink class="adm-ev__link" :to="`/admin/events/${row.id}/monitor`">Monitor</NuxtLink>
              <button type="button" class="adm-ev__btn adm-ev__btn--sm" @click="startEdit(row)">Editar</button>
              <button type="button" class="adm-ev__btn adm-ev__btn--sm" @click="hideEvent(row.id)">Ocultar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="editId !== null" class="adm-ev__panel adm-ev__modal" role="dialog" aria-labelledby="edit-title">
      <h2 id="edit-title" class="adm-ev__h2">Editar #{{ editId }}</h2>
      <div class="adm-ev__grid">
        <label class="adm-ev__lbl">Nom</label>
        <input v-model="editForm.name" type="text" class="adm-ev__input">
        <label class="adm-ev__lbl">Data inici</label>
        <input v-model="editForm.starts_at" type="datetime-local" class="adm-ev__input">
        <label class="adm-ev__lbl">venue_id</label>
        <input v-model.number="editForm.venue_id" type="number" min="1" class="adm-ev__input">
        <label class="adm-ev__lbl">Preu</label>
        <input v-model.number="editForm.price" type="number" min="0.01" step="0.01" class="adm-ev__input">
        <label class="adm-ev__lbl">Imatge URL</label>
        <input v-model="editForm.image_url" type="url" class="adm-ev__input">
        <label class="adm-ev__lbl">Categoria</label>
        <input v-model="editForm.category" type="text" class="adm-ev__input">
      </div>
      <p v-if="editErr" class="adm-ev__err">{{ editErr }}</p>
      <div class="adm-ev__row">
        <button type="button" class="adm-ev__btn" :disabled="editPending" @click="submitEdit">Desar</button>
        <button type="button" class="adm-ev__btn adm-ev__btn--ghost" @click="cancelEdit">Cancel·lar</button>
      </div>
    </div>
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

const listPending = ref(true);
const listErr = ref('');
const listPayload = ref(null);

const discKeyword = ref('');
const discPending = ref(false);
const discErr = ref('');
const discResults = ref([]);
const discSearched = ref(false);

const createPending = ref(false);
const createErr = ref('');
const createForm = reactive({
  external_tm_id: '',
  name: '',
  venue_id: 1,
  starts_at: '',
  price: null,
  image_url: '',
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

function formatIso (iso) {
  if (!iso) {
    return '—';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES');
  } catch {
    return iso;
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

async function loadList () {
  listErr.value = '';
  listPending.value = true;
  try {
    listPayload.value = await getJson('/api/admin/events?hidden=include&per_page=100');
  } catch (e) {
    listErr.value = 'No s’ha pogut carregar la llista.';
    console.error(e);
  } finally {
    listPending.value = false;
  }
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
    await loadList();
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
    await postJson('/api/admin/events', body);
    createForm.external_tm_id = '';
    createForm.name = '';
    createForm.starts_at = '';
    createForm.price = null;
    createForm.image_url = '';
    await loadList();
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
  editForm.price = row.price ? Number(row.price) : 0;
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
    await loadList();
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
    await loadList();
  } catch (e) {
    console.error(e);
  }
}

onMounted(() => {
  loadList();
});
</script>

<style scoped>
.adm-ev {
  max-width: 56rem;
}
.adm-ev__h1 {
  margin: 0 0 1rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-ev__h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  color: #bbb;
}
.adm-ev__panel {
  margin-bottom: 1.25rem;
  padding: 1rem;
  background: #111;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
}
.adm-ev__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}
.adm-ev__grid {
  display: grid;
  grid-template-columns: 10rem 1fr;
  gap: 0.5rem 1rem;
  align-items: center;
  margin-bottom: 0.75rem;
}
.adm-ev__lbl {
  font-size: 0.85rem;
  color: #aaa;
}
.adm-ev__input {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #eee;
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
  max-width: 100%;
}
.adm-ev__btn {
  background: #ff0055;
  color: #fff;
  border: none;
  padding: 0.4rem 0.85rem;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
.adm-ev__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.adm-ev__btn--sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.8rem;
}
.adm-ev__btn--ghost {
  background: transparent;
  border: 1px solid #555;
  color: #ccc;
}
.adm-ev__muted {
  font-size: 0.85rem;
  color: #777;
  margin: 0.25rem 0;
}
.adm-ev__err {
  color: #ff6b6b;
  font-size: 0.9rem;
}
.adm-ev__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.adm-ev__table th,
.adm-ev__table td {
  border: 1px solid #333;
  padding: 0.35rem 0.5rem;
  text-align: left;
}
.adm-ev__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}
.adm-ev__link {
  color: #ff0055;
  font-size: 0.85rem;
}
.adm-ev__disc-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.adm-ev__disc-item {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  padding: 0.35rem 0;
  border-bottom: 1px solid #2a2a2a;
}
.adm-ev__disc-name {
  flex: 1;
  min-width: 8rem;
  color: #ddd;
}
.adm-ev__disc-id {
  font-size: 0.75rem;
  color: #888;
}
.adm-ev__modal {
  border-color: #ff0055;
}
</style>
