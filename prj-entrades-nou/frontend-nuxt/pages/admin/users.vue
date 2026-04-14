<template>
  <div class="adm-us">
    <h1 class="adm-us__h1">Usuaris</h1>

    <section class="adm-us__panel">
      <h2 class="adm-us__h2">Cercar</h2>
      <div class="adm-us__row">
        <input v-model="searchQ" type="search" class="adm-us__input" placeholder="Nom o email" @keydown.enter.prevent="loadUsers">
        <button type="button" class="adm-us__btn" :disabled="listPending" @click="loadUsers">Actualitzar</button>
      </div>
    </section>

    <section class="adm-us__panel">
      <h2 class="adm-us__h2">Crear usuari</h2>
      <div class="adm-us__grid">
        <label class="adm-us__lbl">Nom</label>
        <input v-model="createForm.name" type="text" class="adm-us__input">
        <label class="adm-us__lbl">Email</label>
        <input v-model="createForm.email" type="email" class="adm-us__input">
        <label class="adm-us__lbl">Contrasenya</label>
        <input v-model="createForm.password" type="password" class="adm-us__input" autocomplete="new-password">
        <label class="adm-us__lbl">Rols (comma)</label>
        <input v-model="createForm.rolesText" type="text" class="adm-us__input" placeholder="user, admin">
      </div>
      <p v-if="createErr" class="adm-us__err">{{ createErr }}</p>
      <button type="button" class="adm-us__btn" :disabled="createPending" @click="submitCreate">Crear</button>
    </section>

    <section class="adm-us__panel">
      <h2 class="adm-us__h2">Llista</h2>
      <p v-if="listErr" class="adm-us__err">{{ listErr }}</p>
      <p v-else-if="listPending" class="adm-us__muted">Carregant…</p>
      <table v-else class="adm-us__table" aria-label="Usuaris">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Rols</th>
            <th>Accions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in userRows" :key="u.id">
            <td>{{ u.id }}</td>
            <td>{{ u.name }}</td>
            <td>{{ u.email }}</td>
            <td>{{ formatRoles(u) }}</td>
            <td class="adm-us__actions">
              <button type="button" class="adm-us__btn adm-us__btn--sm" @click="openOrders(u.id)">Comandes</button>
              <button type="button" class="adm-us__btn adm-us__btn--sm adm-us__btn--danger" @click="removeUser(u.id)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="ordersUserId !== null" class="adm-us__panel adm-us__modal" role="dialog" aria-labelledby="ord-title">
      <h2 id="ord-title" class="adm-us__h2">Comandes usuari #{{ ordersUserId }}</h2>
      <p v-if="ordersErr" class="adm-us__err">{{ ordersErr }}</p>
      <p v-else-if="ordersPending" class="adm-us__muted">Carregant…</p>
      <div v-else class="adm-us__orders">
        <article v-for="(o, oi) in ordersList" :key="oi" class="adm-us__order">
          <p class="adm-us__order-head">
            <strong>Comanda {{ o.id }}</strong> · {{ o.state }} · {{ o.total_amount }} {{ o.currency }}
          </p>
          <p class="adm-us__muted">{{ formatIso(o.updated_at) }}</p>
          <p v-if="o.event" class="adm-us__muted">{{ o.event.name }}</p>
          <ul class="adm-us__lines">
            <li v-for="(ln, li) in o.lines" :key="li">
              Seient {{ ln.seat_id }} · tiquet
              <span v-if="ln.ticket">
                {{ ln.ticket.status }}
                <span v-if="ln.ticket.validated"> · validat</span>
                <span v-if="ln.ticket.transfer"> · transferència</span>
              </span>
            </li>
          </ul>
        </article>
      </div>
      <button type="button" class="adm-us__btn adm-us__btn--ghost" @click="closeOrders">Tancar</button>
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

const { getJson, postJson, deleteJson } = useAuthorizedApi();

const searchQ = ref('');
const listPending = ref(false);
const listErr = ref('');
const listPayload = ref(null);

const createPending = ref(false);
const createErr = ref('');
const createForm = reactive({
  name: '',
  email: '',
  password: '',
  rolesText: 'user',
});

const ordersUserId = ref(null);
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

function formatRoles (u) {
  if (!u || !u.roles) {
    return '—';
  }
  const r = u.roles;
  const parts = [];
  for (let i = 0; i < r.length; i++) {
    const name = r[i].name;
    if (name) {
      parts.push(name);
    }
  }
  return parts.join(', ');
}

function formatIso (iso) {
  if (!iso) {
    return '';
  }
  try {
    return new Date(iso).toLocaleString('ca-ES');
  } catch {
    return iso;
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

function parseRoles (text) {
  const out = [];
  const parts = text.split(',');
  for (let i = 0; i < parts.length; i++) {
    const s = parts[i].trim();
    if (s !== '') {
      out.push(s);
    }
  }
  return out;
}

async function submitCreate () {
  createErr.value = '';
  if (!createForm.name.trim() || !createForm.email.trim() || createForm.password.length < 8) {
    createErr.value = 'Nom, email i contrasenya (mín. 8) obligatoris.';
    return;
  }
  createPending.value = true;
  try {
    const roles = parseRoles(createForm.rolesText);
    const body = {
      name: createForm.name.trim(),
      email: createForm.email.trim(),
      password: createForm.password,
    };
    if (roles.length > 0) {
      body.roles = roles;
    }
    await postJson('/api/admin/users', body);
    createForm.name = '';
    createForm.email = '';
    createForm.password = '';
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

async function openOrders (id) {
  ordersUserId.value = id;
  ordersErr.value = '';
  ordersPending.value = true;
  ordersPayload.value = null;
  try {
    ordersPayload.value = await getJson(`/api/admin/users/${id}/orders`);
  } catch (e) {
    ordersErr.value = 'No s’han pogut carregar les comandes.';
    console.error(e);
  } finally {
    ordersPending.value = false;
  }
}

function closeOrders () {
  ordersUserId.value = null;
}

onMounted(() => {
  loadUsers();
});
</script>

<style scoped>
.adm-us {
  max-width: 56rem;
}
.adm-us__h1 {
  margin: 0 0 1rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-us__h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  color: #bbb;
}
.adm-us__panel {
  margin-bottom: 1.25rem;
  padding: 1rem;
  background: #111;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
}
.adm-us__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.adm-us__grid {
  display: grid;
  grid-template-columns: 10rem 1fr;
  gap: 0.5rem 1rem;
  align-items: center;
  margin-bottom: 0.75rem;
}
.adm-us__lbl {
  font-size: 0.85rem;
  color: #aaa;
}
.adm-us__input {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #eee;
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
}
.adm-us__btn {
  background: #ff0055;
  color: #fff;
  border: none;
  padding: 0.4rem 0.85rem;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
.adm-us__btn--sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.8rem;
}
.adm-us__btn--danger {
  background: #842029;
}
.adm-us__btn--ghost {
  background: transparent;
  border: 1px solid #555;
  color: #ccc;
  margin-top: 0.75rem;
}
.adm-us__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.adm-us__table th,
.adm-us__table td {
  border: 1px solid #333;
  padding: 0.35rem 0.5rem;
  text-align: left;
}
.adm-us__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.adm-us__err {
  color: #ff6b6b;
}
.adm-us__muted {
  color: #777;
  font-size: 0.85rem;
}
.adm-us__modal {
  border-color: #ff0055;
}
.adm-us__orders {
  max-height: 24rem;
  overflow: auto;
}
.adm-us__order {
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #2a2a2a;
}
.adm-us__order-head {
  margin: 0 0 0.25rem;
  color: #e0e0e0;
}
.adm-us__lines {
  margin: 0.25rem 0 0;
  padding-left: 1.1rem;
  font-size: 0.85rem;
  color: #bbb;
}
</style>
