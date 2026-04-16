<template>
  <div class="adm-us">
    <header class="adm-us__hero">
      <div class="adm-us__hero-text">
        <div class="admin-page-hero">
          <h1 class="admin-page-title">
            Gestió d’usuaris
          </h1>
          <p class="admin-page-lead">
            Cerca, crea i manté els comptes des d’un sol panell.
          </p>
        </div>
      </div>
      <div class="adm-us__hero-actions">
        <button type="button" class="admin-cta-create" @click="openCreate">
          <span class="admin-cta-create__mes" aria-hidden="true">
            <svg class="admin-cta-create__svg" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M6 1.5v9M1.5 6h9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
          </span>
          <span>Crear usuari</span>
        </button>
      </div>
    </header>

    <div class="adm-us__bento">
      <div class="adm-us__stat">
        <p class="adm-us__stat-label">
          Usuaris en línia
        </p>
        <p v-if="!summary" class="adm-us__stat-muted">
          …
        </p>
        <template v-else>
          <h2 class="adm-us__stat-value adm-us__stat-value--accent">
            {{ summaryOnlineDisplay }}
          </h2>
          <p class="adm-us__stat-foot">
            Mateixa mètrica que al dashboard (presència en temps real).
          </p>
        </template>
      </div>
      <div class="adm-us__stat">
        <p class="adm-us__stat-label">
          Total registrats
        </p>
        <p v-if="listPending" class="adm-us__stat-muted">
          …
        </p>
        <template v-else>
          <h2 class="adm-us__stat-value">
            {{ userListTotalDisplay }}
          </h2>
          <p class="adm-us__stat-foot">
            Segons la consulta actual (paginació API).
          </p>
        </template>
      </div>
    </div>

    <section class="adm-us__disc" aria-label="Cerca d’usuaris">
      <h2 class="adm-us__disc-title">
        Cerca
      </h2>
      <div class="adm-us__disc-row">
        <label class="adm-us__sr-only" for="us-q">Cerca per usuari o correu</label>
        <input
          id="us-q"
          v-model="searchQ"
          type="search"
          class="adm-us__disc-input"
          placeholder="Usuari o correu…"
          @keydown.enter.prevent="loadUsers"
        >
        <button type="button" class="adm-us__disc-btn" :disabled="listPending" @click="loadUsers">
          Cercar
        </button>
      </div>
    </section>

    <div class="adm-us__table-shell">
      <div class="adm-us__table-head">
        <div class="adm-us__table-head-left">
          <span class="adm-us__table-head-title">Usuaris registrats</span>
        </div>
      </div>

      <div class="adm-us__table-scroll">
        <p v-if="listErr" class="adm-us__err adm-us__table-pad">
          {{ listErr }}
        </p>
        <p v-else-if="listPending" class="adm-us__muted adm-us__table-pad">
          Carregant…
        </p>
        <table v-else class="adm-us__table" aria-label="Usuaris">
          <thead>
            <tr>
              <th scope="col">
                Usuari
              </th>
              <th scope="col">
                Estat
              </th>
              <th scope="col">
                Rol
              </th>
              <th scope="col" class="adm-us__th-actions">
                Accions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in userRows" :key="u.id" class="adm-us__row">
              <td class="adm-us__cell-detail">
                <div class="adm-us__detail">
                  <div class="adm-us__thumb-wrap">
                    <div class="adm-us__avatar-fallback" aria-hidden="true">
                      {{ initialsFromUser(u) }}
                    </div>
                  </div>
                  <div>
                    <p class="adm-us__ev-name">
                      {{ userDisplayLabel(u) }}
                    </p>
                    <p class="adm-us__ev-id">
                      ID {{ u.id }} · {{ u.email || '—' }}
                    </p>
                  </div>
                </div>
              </td>
              <td>
                <span class="adm-us__badge" :class="statusBadgeClass(u)">{{ userStatusLabel(u) }}</span>
              </td>
              <td>
                <span class="adm-us__role">{{ singleRoleLabel(u) }}</span>
              </td>
              <td class="adm-us__cell-actions">
                <div class="adm-us__accions">
                  <button
                    type="button"
                    class="adm-us__accio-ico"
                    aria-label="Editar usuari"
                    title="Editar"
                    @click="openEdit(u)"
                  >
                    <svg class="adm-us__ico" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
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
                    class="adm-us__accio-ico"
                    aria-label="Veure comandes i historial"
                    title="Comandes"
                    @click="openOrders(u)"
                  >
                    <svg class="adm-us__ico" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path
                        d="M7 3h10a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2z"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                      <path d="M9 9h6M9 13h4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="adm-us__accio-ico adm-us__accio-ico--perill"
                    aria-label="Eliminar usuari"
                    title="Eliminar"
                    @click="removeUser(u.id)"
                  >
                    <svg class="adm-us__ico" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path
                        d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14zM10 11v6M14 11v6"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="createModalOpen" class="admin-modal-root">
        <div class="admin-modal-backdrop" @click="closeCreate" />
        <div class="admin-modal-panel" role="dialog" aria-labelledby="create-user-title">
          <button type="button" class="admin-modal-close" aria-label="Tancar" @click="closeCreate">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
          <h2 id="create-user-title" class="admin-modal-title">
            Nou usuari
          </h2>
          <p class="admin-modal-lead">
            Omple les dades; la contrasenya ha de tenir com a mínim 8 caràcters.
          </p>
          <p v-if="createErr" class="admin-form-err">
            {{ createErr }}
          </p>
          <div class="admin-form-stack">
            <div class="admin-form-field">
              <label class="admin-form-label" for="create-username">Nom d’usuari</label>
              <input id="create-username" v-model="createForm.username" type="text" class="admin-form-input" autocomplete="username">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="create-email">Email</label>
              <input id="create-email" v-model="createForm.email" type="email" class="admin-form-input" autocomplete="email">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="create-password">Contrasenya</label>
              <input id="create-password" v-model="createForm.password" type="password" class="admin-form-input" autocomplete="new-password">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="create-role">Rol</label>
              <select id="create-role" v-model="createForm.role" class="admin-form-input">
                <option value="user">
                  Usuari
                </option>
                <option value="admin">
                  Administrador
                </option>
              </select>
            </div>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="admin-btn-primary" :disabled="createPending" @click="submitCreate">
              Crear
            </button>
            <button type="button" class="admin-btn-ghost" :disabled="createPending" @click="closeCreate">
              Cancel·lar
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="editUserId !== null" class="admin-modal-root">
        <div class="admin-modal-backdrop" @click="closeEdit" />
        <div class="admin-modal-panel" role="dialog" aria-labelledby="edit-user-title">
          <button type="button" class="admin-modal-close" aria-label="Tancar" @click="closeEdit">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
          <h2 id="edit-user-title" class="admin-modal-title">
            Editar usuari #{{ editUserId }}
          </h2>
          <p v-if="editErr" class="admin-form-err">
            {{ editErr }}
          </p>
          <div class="admin-form-stack">
            <div class="admin-form-field">
              <label class="admin-form-label" for="edit-username">Nom d’usuari</label>
              <input id="edit-username" v-model="editForm.username" type="text" class="admin-form-input" autocomplete="username">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="edit-email">Email</label>
              <input id="edit-email" v-model="editForm.email" type="email" class="admin-form-input" autocomplete="email">
            </div>
            <div class="admin-form-field">
              <label class="admin-form-label" for="edit-role">Rol</label>
              <select id="edit-role" v-model="editForm.role" class="admin-form-input">
                <option value="user">
                  Usuari
                </option>
                <option value="admin">
                  Administrador
                </option>
              </select>
            </div>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="admin-btn-primary" :disabled="editPending" @click="submitEdit">
              Desar
            </button>
            <button type="button" class="admin-btn-ghost" :disabled="editPending" @click="closeEdit">
              Cancel·lar
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="ordersUserId !== null" class="admin-modal-root">
        <div class="admin-modal-backdrop" @click="closeOrders" />
        <div class="admin-modal-panel admin-modal-panel--wide adm-us__orders-panel" role="dialog" aria-labelledby="ord-title">
          <button type="button" class="admin-modal-close" aria-label="Tancar" @click="closeOrders">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
          <h2 id="ord-title" class="admin-modal-title">
            Historial de compres
          </h2>
          <p class="admin-modal-lead">
            {{ ordersContextName }}<span v-if="ordersContextEmail !== ''"> · {{ ordersContextEmail }}</span>
          </p>
          <p v-if="ordersErr" class="admin-form-err">
            {{ ordersErr }}
          </p>
          <p v-else-if="ordersPending" class="admin-modal-lead">
            Carregant…
          </p>
          <div v-else class="adm-us__orders-body">
            <p v-if="ordersList.length === 0" class="adm-us__muted">
              Aquest usuari encara no té comandes.
            </p>
            <div v-else class="adm-us__txn-grid">
              <div
                v-for="(o, oi) in ordersList"
                :key="'ord'+oi"
                class="adm-us__txn"
                :class="txnCardToneClass(o)"
              >
                <div class="adm-us__txn-notch adm-us__txn-notch--left" />
                <div class="adm-us__txn-notch adm-us__txn-notch--right" />
                <div class="adm-us__txn-top">
                  <p class="adm-us__txn-id">
                    Comanda #{{ o.id }}
                  </p>
                  <p class="adm-us__txn-time">
                    {{ formatShortTime(o.updated_at) }}
                  </p>
                </div>
                <p class="adm-us__txn-event">
                  {{ orderEventTitle(o) }}
                </p>
                <p class="adm-us__txn-buyer">
                  Estat: {{ o.state }}
                </p>
                <ul v-if="o.lines && o.lines.length > 0" class="adm-us__lines">
                  <li v-for="(ln, li) in o.lines" :key="'ln'+li">
                    Seient {{ ln.seat_id }}
                    <span v-if="ln.ticket"> · {{ ln.ticket.status }}</span>
                  </li>
                </ul>
                <div class="adm-us__txn-bottom">
                  <div class="adm-us__txn-amount" :class="txnAmountClass(o)">
                    {{ txnAmountText(o) }}
                  </div>
                  <span v-if="o.state === OrderPaid" class="material-symbols-outlined adm-us__txn-ico" aria-hidden="true">receipt_long</span>
                  <span v-else-if="o.state === OrderFailed" class="material-symbols-outlined adm-us__txn-ico adm-us__txn-ico--err" aria-hidden="true">block</span>
                  <span v-else class="material-symbols-outlined adm-us__txn-ico" aria-hidden="true">schedule</span>
                </div>
              </div>
            </div>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="admin-btn-ghost" @click="closeOrders">
              Tancar
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

const OrderPaid = 'paid';
const OrderFailed = 'failed';
const OrderPending = 'pending_payment';

const { getJson, postJson, patchJson, deleteJson } = useAuthorizedApi();

const searchQ = ref('');
const listPending = ref(false);
const listErr = ref('');
const listPayload = ref(null);

const summary = ref(null);

const createModalOpen = ref(false);
const createPending = ref(false);
const createErr = ref('');
const createForm = reactive({
  username: '',
  email: '',
  password: '',
  role: 'user',
});

const editUserId = ref(null);
const editPending = ref(false);
const editErr = ref('');
const editForm = reactive({
  username: '',
  email: '',
  role: 'user',
});

const ordersUserId = ref(null);
const ordersContextName = ref('');
const ordersContextEmail = ref('');
const ordersPending = ref(false);
const ordersErr = ref('');
const ordersPayload = ref(null);

const userRows = computed(() => {
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

const ordersList = computed(() => {
  const p = ordersPayload.value;
  if (!p || !p.orders) {
    return [];
  }
  const o = p.orders;
  const out = [];
  for (let i = 0; i < o.length; i++) {
    out.push(o[i]);
  }
  return out;
});

const summaryOnlineDisplay = computed(() => {
  if (!summary.value || summary.value.online_users === undefined || summary.value.online_users === null) {
    return '—';
  }
  return String(summary.value.online_users);
});

const userListTotalDisplay = computed(() => {
  const p = listPayload.value;
  if (!p) {
    return '—';
  }
  if (p.meta !== undefined && p.meta !== null && p.meta.total !== undefined) {
    return String(p.meta.total);
  }
  if (p.total !== undefined) {
    return String(p.total);
  }
  return '—';
});

function singleRoleFromUser (u) {
  if (u && u.role && (u.role === 'admin' || u.role === 'user')) {
    return u.role;
  }
  if (!u || !u.roles || u.roles.length === 0) {
    return 'user';
  }
  let i = 0;
  for (; i < u.roles.length; i++) {
    if (u.roles[i].name === 'admin') {
      return 'admin';
    }
  }
  const n0 = u.roles[0].name;
  if (n0 === 'admin' || n0 === 'user') {
    return n0;
  }
  return 'user';
}

function singleRoleLabel (u) {
  const r = singleRoleFromUser(u);
  if (r === 'admin') {
    return 'Administrador';
  }
  return 'Usuari';
}

function userStatusLabel (_u) {
  return 'Actiu';
}

function statusBadgeClass (_u) {
  return 'adm-us__badge--ok';
}

function initialsFromName (name) {
  if (!name || typeof name !== 'string') {
    return '?';
  }
  const t = name.trim();
  if (t === '') {
    return '?';
  }
  const parts = t.split(/\s+/);
  if (parts.length === 1) {
    const s = parts[0];
    if (s.length === 1) {
      return s.toUpperCase();
    }
    return (s[0] + s[1]).toUpperCase();
  }
  const a = parts[0][0];
  const b = parts[parts.length - 1][0];
  return (a + b).toUpperCase();
}

function userDisplayLabel (u) {
  if (!u) {
    return '—';
  }
  if (u.username && String(u.username).trim() !== '') {
    return '@' + String(u.username).trim();
  }
  if (u.name && String(u.name).trim() !== '') {
    return String(u.name).trim();
  }
  return '—';
}

function initialsFromUser (u) {
  let src = '';
  if (u && u.username && String(u.username).trim() !== '') {
    src = String(u.username).trim();
  } else if (u && u.name && String(u.name).trim() !== '') {
    src = String(u.name).trim();
  }
  return initialsFromName(src);
}

function formatShortTime (iso) {
  if (!iso) {
    return '';
  }
  try {
    const d = new Date(iso);
    return d.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' });
  } catch {
    return '';
  }
}

function orderEventTitle (o) {
  if (o.event && o.event.name) {
    return o.event.name;
  }
  return 'Sense esdeveniment';
}

function txnCardToneClass (o) {
  if (o.state === OrderFailed) {
    return 'adm-us__txn--fail';
  }
  if (o.state === OrderPending) {
    return 'adm-us__txn--pending';
  }
  return '';
}

function txnAmountClass (o) {
  if (o.state === OrderFailed) {
    return 'adm-us__txn-amount--err';
  }
  return '';
}

function txnAmountText (o) {
  if (o.state === OrderFailed) {
    return 'REBUTJADA';
  }
  const raw = o.total_amount;
  const n = parseFloat(raw);
  if (Number.isNaN(n)) {
    return raw + ' ' + o.currency;
  }
  let formatted = '';
  try {
    formatted = new Intl.NumberFormat('ca-ES', {
      style: 'currency',
      currency: 'EUR',
    }).format(n);
  } catch {
    formatted = raw + ' ' + o.currency;
  }
  return formatted;
}

async function loadSummary () {
  try {
    summary.value = await getJson('/api/admin/summary');
  } catch (e) {
    console.error(e);
  }
}

async function loadUsers () {
  listErr.value = '';
  listPending.value = true;
  try {
    const q = new URLSearchParams();
    q.set('per_page', '50');
    const term = searchQ.value.trim();
    if (term !== '') {
      q.set('q', term);
    }
    listPayload.value = await getJson(`/api/admin/users?${q.toString()}`);
  } catch (e) {
    listErr.value = 'No s’ha pogut carregar la llista.';
    console.error(e);
  } finally {
    listPending.value = false;
  }
}

function openCreate () {
  createErr.value = '';
  createModalOpen.value = true;
}

function closeCreate () {
  createModalOpen.value = false;
  createErr.value = '';
}

async function submitCreate () {
  createErr.value = '';
  if (!createForm.username.trim() || !createForm.email.trim() || createForm.password.length < 8) {
    createErr.value = 'Nom d’usuari, email i contrasenya (mín. 8) obligatoris.';
    return;
  }
  createPending.value = true;
  try {
    await postJson('/api/admin/users', {
      username: createForm.username.trim(),
      email: createForm.email.trim(),
      password: createForm.password,
      role: createForm.role,
    });
    createForm.username = '';
    createForm.email = '';
    createForm.password = '';
    closeCreate();
    await loadUsers();
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

function openEdit (u) {
  ordersUserId.value = null;
  editErr.value = '';
  editUserId.value = u.id;
  editForm.username = u.username || '';
  editForm.email = u.email || '';
  editForm.role = singleRoleFromUser(u);
}

function closeEdit () {
  editUserId.value = null;
  editErr.value = '';
}

async function submitEdit () {
  editErr.value = '';
  if (!editForm.username.trim() || !editForm.email.trim()) {
    editErr.value = 'Nom d’usuari i email obligatoris.';
    return;
  }
  editPending.value = true;
  try {
    await patchJson(`/api/admin/users/${editUserId.value}`, {
      username: editForm.username.trim(),
      email: editForm.email.trim(),
      role: editForm.role,
    });
    closeEdit();
    await loadUsers();
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

async function removeUser (id) {
  if (!window.confirm('Eliminar aquest usuari?')) {
    return;
  }
  try {
    await deleteJson(`/api/admin/users/${id}`);
    await loadUsers();
  } catch (e) {
    let msg = 'No s’ha pogut eliminar.';
    if (e && e.data && e.data.message) {
      msg = e.data.message;
    }
    alert(msg);
    console.error(e);
  }
}

async function openOrders (u) {
  editUserId.value = null;
  ordersContextName.value = userDisplayLabel(u);
  ordersContextEmail.value = u.email || '';
  ordersUserId.value = u.id;
  ordersErr.value = '';
  ordersPending.value = true;
  ordersPayload.value = null;
  try {
    ordersPayload.value = await getJson(`/api/admin/users/${u.id}/orders`);
  } catch (e) {
    ordersErr.value = 'No s’han pogut carregar les comandes.';
    console.error(e);
  } finally {
    ordersPending.value = false;
  }
}

function closeOrders () {
  ordersUserId.value = null;
  ordersContextName.value = '';
  ordersContextEmail.value = '';
}

onMounted(() => {
  loadSummary();
  loadUsers();
});
</script>

<style scoped>
.adm-us {
  box-sizing: border-box;
  width: 100%;
  max-width: 80rem;
  margin: 0 auto;
  padding: 0.5rem 0 2rem;
  font-family: Inter, system-ui, sans-serif;
  color: #e5e2e1;
}

.adm-us__hero {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.adm-us__hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: center;
}

.adm-us__bento {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (min-width: 768px) {
  .adm-us__bento {
    grid-template-columns: 1fr 1fr;
  }
}

.adm-us__stat {
  padding: 1.5rem;
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
}

.adm-us__stat-label {
  margin: 0;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #ccc7ac;
}

.adm-us__stat-value {
  margin: 0.5rem 0 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 2.25rem;
  font-weight: 900;
  color: #fff;
}

.adm-us__stat-value--accent {
  color: #ffee32;
}

.adm-us__stat-muted {
  margin: 0.5rem 0 0;
  color: rgba(255, 255, 255, 0.35);
}

.adm-us__stat-foot {
  margin: 0.75rem 0 0;
  font-size: 0.75rem;
  line-height: 1.4;
  color: rgba(255, 255, 255, 0.45);
}

.adm-us__disc {
  margin-bottom: 2.5rem;
  padding: 1.5rem;
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
}

.adm-us__disc-title {
  margin: 0 0 1rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
}

.adm-us__disc-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
}

.adm-us__disc-input {
  flex: 1;
  min-width: 12rem;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  border: 1px solid rgba(149, 145, 120, 0.25);
  background: #0e0e0e;
  color: #e5e2e1;
  font-size: 0.9rem;
}

.adm-us__disc-input:focus {
  outline: none;
  border-color: #f7e628;
}

.adm-us__disc-btn {
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

.adm-us__disc-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.adm-us__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.adm-us__table-shell {
  border-radius: 1rem;
  border: 1px solid rgba(149, 145, 120, 0.2);
  background: #1c1b1b;
  overflow: hidden;
}

.adm-us__table-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(74, 71, 51, 0.35);
  background: rgba(42, 42, 42, 0.35);
}

.adm-us__table-head-left {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
}

.adm-us__table-head-title {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}

.adm-us__table-scroll {
  overflow-x: auto;
}

.adm-us__table-pad {
  padding: 1.5rem;
}

.adm-us__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.adm-us__table thead th {
  padding: 1rem 1.5rem;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  text-align: left;
  color: #ccc7ac;
  border-bottom: 1px solid rgba(74, 71, 51, 0.25);
}

/* La regla genèrica `thead th { text-align: left }` no ha de guanyar sobre Accions */
.adm-us__table thead th.adm-us__th-actions {
  text-align: center;
}

.adm-us__table tbody td.adm-us__cell-actions {
  text-align: center;
  vertical-align: middle;
}

.adm-us__row {
  border-bottom: 1px solid rgba(74, 71, 51, 0.15);
  transition: background 0.15s ease;
}

.adm-us__row:hover {
  background: rgba(255, 255, 255, 0.02);
}

.adm-us__row td {
  padding: 1.25rem 1.5rem;
  vertical-align: middle;
  color: #e5e2e1;
}

.adm-us__detail {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-width: 14rem;
}

.adm-us__thumb-wrap {
  width: 3rem;
  height: 3rem;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 9999px;
  background: #353534;
}

.adm-us__avatar-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.7rem;
  font-weight: 900;
  color: #ffee32;
}

.adm-us__ev-name {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  color: #fff;
}

.adm-us__ev-id {
  margin: 0.2rem 0 0;
  font-size: 0.7rem;
  color: rgba(255, 255, 255, 0.35);
}

.adm-us__role {
  font-weight: 600;
}

.adm-us__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.65rem;
  border-radius: 9999px;
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.adm-us__badge--ok {
  border: 1px solid rgba(247, 230, 40, 0.35);
  background: rgba(247, 230, 40, 0.12);
  color: #f7e628;
}

.adm-us__accions {
  box-sizing: border-box;
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 0.4rem;
  margin: 0 auto;
}

.adm-us__accio-ico {
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
  cursor: pointer;
  transition:
    background 0.2s ease,
    color 0.2s ease,
    border-color 0.2s ease;
}

.adm-us__accio-ico:hover {
  background: #f7e628;
  border-color: #f7e628;
  color: #131313;
}

.adm-us__accio-ico--perill {
  border-color: rgba(255, 180, 171, 0.45);
  color: rgba(255, 180, 171, 0.95);
}

.adm-us__accio-ico--perill:hover {
  background: #f7e628;
  border-color: #f7e628;
  color: #131313;
}

.adm-us__ico {
  display: block;
  width: 1.05rem;
  height: 1.05rem;
}

.adm-us__err {
  margin: 0 0 0.75rem;
  color: #ffb4ab;
  font-size: 0.88rem;
}

.adm-us__muted {
  margin: 0 0 0.75rem;
  color: rgba(255, 255, 255, 0.38);
  font-size: 0.85rem;
}

.adm-us__orders-panel {
  max-width: 42rem;
}

.adm-us__orders-body {
  max-height: min(70vh, 36rem);
  overflow-y: auto;
  margin-bottom: 0.5rem;
}

.adm-us__txn-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
}

@media (min-width: 520px) {
  .adm-us__txn-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.adm-us__txn {
  position: relative;
  padding: 1.35rem;
  border-radius: 0.65rem;
  background: #353534;
  overflow: hidden;
}

.adm-us__txn--fail {
  border: 1px solid rgba(255, 238, 50, 0.12);
}

.adm-us__txn--pending {
  outline: 1px dashed rgba(255, 255, 255, 0.12);
  outline-offset: 2px;
}

.adm-us__txn-notch {
  position: absolute;
  top: 50%;
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 9999px;
  background: #1c1b1b;
  transform: translateY(-50%);
}

.adm-us__txn-notch--left {
  left: -0.45rem;
}

.adm-us__txn-notch--right {
  right: -0.45rem;
}

.adm-us__txn-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.15rem;
}

.adm-us__txn-id {
  margin: 0;
  font-size: 0.62rem;
  font-weight: 900;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #ffee32;
}

.adm-us__txn--fail .adm-us__txn-id {
  color: #ffb4ab;
}

.adm-us__txn-time {
  margin: 0;
  font-size: 0.62rem;
  color: rgba(255, 255, 255, 0.38);
}

.adm-us__txn-event {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}

.adm-us__txn-buyer {
  margin: 0 0 0.65rem;
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.38);
}

.adm-us__txn-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.adm-us__txn-amount {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.35rem;
  font-weight: 900;
  color: #fff;
}

.adm-us__txn-amount--err {
  font-size: 1.1rem;
  color: #ffb4ab;
}

.adm-us__txn-ico {
  color: rgba(255, 255, 255, 0.22);
  transition: color 0.2s ease;
}

.adm-us__txn:hover .adm-us__txn-ico {
  color: #ffee32;
}

.adm-us__txn-ico--err {
  color: #ffb4ab;
}

.adm-us__lines {
  margin: 0 0 0.85rem;
  padding-left: 1.1rem;
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.5);
}
</style>
