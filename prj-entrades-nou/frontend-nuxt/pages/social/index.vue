<template>
  <div class="social">
    <h1 class="social__h1">Social</h1>

    <section class="social__block social__block--search">
      <h2 class="social__h2">Cercar usuaris</h2>
      <p class="social__lead">
        Escriu el <strong>nom</strong> o el <strong>nom d’usuari</strong> per trobar persones registrades. Tria un resultat per veure el perfil i enviar una sol·licitud d’amistat.
      </p>
      <div class="social__search-wrap">
        <label class="social__label" for="social-search-input">Cerca</label>
        <input
          id="social-search-input"
          v-model="searchQ"
          type="search"
          class="social__input social__input--search"
          autocomplete="off"
          placeholder="Ex.: Maria o @maria"
          @input="onSearchInput"
        >
        <ul
          v-if="searchDropdownVisible && searchResults.length > 0"
          class="social__dropdown"
          role="listbox"
        >
          <li v-for="u in searchResults" :key="u.id" class="social__dropdown-item" role="option">
            <NuxtLink
              :to="`/users/${u.id}`"
              class="social__dropdown-link"
              @click="closeSearchDropdown"
            >
              <span class="social__dropdown-name">{{ u.name }}</span>
              <span class="social__dropdown-user">@{{ u.username }}</span>
            </NuxtLink>
          </li>
        </ul>
        <p v-if="searchLoading" class="social__muted">Cercant…</p>
        <p v-if="searchHint" class="social__muted">{{ searchHint }}</p>
        <p v-if="searchErr" class="social__err">{{ searchErr }}</p>
      </div>
    </section>

    <section class="social__block">
      <h2 class="social__h2">Invitacions</h2>
      <p v-if="loading" class="social__muted">Carregant…</p>
      <ul v-else-if="invites.length" class="social__invite-list">
        <li v-for="inv in invites" :key="inv.id" class="social__invite-card">
          <p class="social__invite-text">{{ inviteDescription(inv) }}</p>
          <div v-if="inv.status === 'pending' && canAcceptInvite(inv)" class="social__invite-actions">
            <button type="button" class="social__btn social__btn--sm" @click="respond(inv.id, 'accept')">
              Acceptar
            </button>
            <button
              type="button"
              class="social__btn social__btn--sm social__btn--ghost"
              @click="respond(inv.id, 'reject')"
            >
              Rebutjar
            </button>
          </div>
        </li>
      </ul>
      <p v-else class="social__muted">No tens invitacions recents.</p>
    </section>

    <section class="social__block">
      <h2 class="social__h2">Amics</h2>
      <p v-if="loading" class="social__muted">Carregant…</p>
      <ul v-else-if="friends.length" class="social__friend-list">
        <li v-for="f in friends" :key="f.id" class="social__friend-row">
          <NuxtLink :to="`/users/${f.id}`" class="social__friend-link">
            <span class="social__friend-name">{{ f.name }}</span>
            <span class="social__friend-user">@{{ f.username }}</span>
            <span
              v-if="unreadCountForFriend(f.id) > 0"
              class="social__notif-badge"
              :aria-label="'Notificacions sense llegir: ' + unreadCountForFriend(f.id)"
            >{{ unreadCountForFriend(f.id) }}</span>
          </NuxtLink>
          <p class="social__friend-hint">Notificacions d’aquest amic (entrades i esdeveniments compartits).</p>
        </li>
      </ul>
      <p v-else class="social__muted">Encara no tens amics acceptats.</p>
    </section>

    <p class="social__hint">
      Per enviar una entrada a un amic, ves a <NuxtLink to="/tickets" class="social__a">Les meves entrades</NuxtLink>
      i obre el modal «Enviar entrada».
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { usePrivateTicketSocket } from '~/composables/usePrivateTicketSocket';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const auth = useAuthStore();
const { getJson, postJson, patchJson } = useAuthorizedApi();

function emitUnreadRefresh () {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('app:notifications-updated'));
  }
}

usePrivateTicketSocket();

function canAcceptInvite (inv) {
  if (inv.status !== 'pending') {
    return false;
  }
  const me = auth.user?.id;
  if (!me || !inv.receiver_id) {
    return false;
  }
  return String(inv.receiver_id) === String(me);
}

function inviteDescription (inv) {
  const me = auth.user?.id;
  if (!me) {
    return '';
  }
  let senderName = 'Un usuari';
  if (inv.sender_name && String(inv.sender_name).trim() !== '') {
    senderName = inv.sender_name;
  }
  let receiverName = '';
  if (inv.receiver_name && String(inv.receiver_name).trim() !== '') {
    receiverName = inv.receiver_name;
  }

  if (inv.status === 'accepted') {
    return 'Amistat acceptada.';
  }
  if (inv.status === 'rejected') {
    return 'Sol·licitud rebutjada.';
  }

  if (String(inv.sender_id) === String(me)) {
    if (receiverName !== '') {
      return 'Has enviat una sol·licitud d’amistat a ' + receiverName + '.';
    }
    if (inv.receiver_email) {
      return 'Has enviat una sol·licitud a la adreça ' + inv.receiver_email + '.';
    }
    return 'Has enviat una sol·licitud d’amistat.';
  }

  return senderName + ' t’ha enviat una sol·licitud d’amistat.';
}

function otherPartyIdFromNotification (n) {
  const p = n.payload;
  if (!p) {
    return '';
  }
  if (p.direction === 'received') {
    if (p.actor_user_id !== undefined && p.actor_user_id !== null) {
      return String(p.actor_user_id);
    }
  }
  if (p.direction === 'sent') {
    if (p.recipient_user_id !== undefined && p.recipient_user_id !== null) {
      return String(p.recipient_user_id);
    }
  }
  return '';
}

const loading = ref(true);
const friends = ref([]);
const invites = ref([]);
const notifications = ref([]);

const searchQ = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const searchErr = ref('');
const searchDropdownVisible = ref(true);
let searchTimer = null;

const searchHint = computed(() => {
  const t = searchQ.value.trim();
  if (t.length > 0 && t.length < 2) {
    return 'Escriu almenys 2 caràcters.';
  }
  return '';
});

const unreadByFriend = computed(() => {
  const friendIds = {};
  for (let i = 0; i < friends.value.length; i++) {
    friendIds[String(friends.value[i].id)] = true;
  }
  const counts = {};
  const list = notifications.value;
  for (let j = 0; j < list.length; j++) {
    const n = list[j];
    if (n.read_at) {
      continue;
    }
    const t = n.type;
    if (t !== 'event_shared' && t !== 'ticket_shared') {
      continue;
    }
    const other = otherPartyIdFromNotification(n);
    if (other === '') {
      continue;
    }
    if (!friendIds[other]) {
      continue;
    }
    if (counts[other] === undefined) {
      counts[other] = 0;
    }
    counts[other] = counts[other] + 1;
  }
  return counts;
});

function unreadCountForFriend (friendId) {
  const k = String(friendId);
  const c = unreadByFriend.value[k];
  if (c === undefined) {
    return 0;
  }
  return c;
}

function closeSearchDropdown () {
  searchDropdownVisible.value = false;
}

function scheduleSearch () {
  if (searchTimer !== null) {
    clearTimeout(searchTimer);
  }
  searchTimer = setTimeout(runUserSearch, 320);
}

function onSearchInput () {
  searchDropdownVisible.value = true;
  const t = searchQ.value.trim();
  if (t.length < 2) {
    searchResults.value = [];
    searchErr.value = '';
    return;
  }
  scheduleSearch();
}

async function runUserSearch () {
  const t = searchQ.value.trim();
  if (t.length < 2) {
    searchResults.value = [];
    return;
  }
  searchLoading.value = true;
  searchErr.value = '';
  try {
    const res = await getJson('/api/social/discover/search?q=' + encodeURIComponent(t));
    searchResults.value = res.users || [];
  } catch (e) {
    searchErr.value = 'Error de cerca.';
    searchResults.value = [];
  } finally {
    searchLoading.value = false;
  }
}

function onSocketNotification () {
  loadNotifications();
  emitUnreadRefresh();
}

function onSocialInvitesUpdated () {
  load();
}

async function loadNotifications () {
  try {
    const res = await getJson('/api/notifications?limit=100');
    notifications.value = res.notifications || [];
  } catch (e) {
    console.error(e);
  }
}

async function load () {
  loading.value = true;
  try {
    const f = await getJson('/api/social/friends');
    friends.value = f.friends || [];
    const inv = await getJson('/api/social/friend-invites?direction=all');
    invites.value = inv.invites || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function respond (inviteId, action) {
  try {
    await patchJson(`/api/social/friend-invites/${inviteId}`, { action });
    await load();
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:social-invites-updated'));
    }
  } catch (e) {
    console.error(e);
  }
}

onMounted(() => {
  load();
  loadNotifications();
  if (typeof window !== 'undefined') {
    window.addEventListener('app:socket-notification', onSocketNotification);
    window.addEventListener('app:social-invites-updated', onSocialInvitesUpdated);
  }
  emitUnreadRefresh();
});

onUnmounted(() => {
  if (searchTimer !== null) {
    clearTimeout(searchTimer);
  }
  if (typeof window !== 'undefined') {
    window.removeEventListener('app:socket-notification', onSocketNotification);
    window.removeEventListener('app:social-invites-updated', onSocialInvitesUpdated);
  }
});
</script>

<style scoped>
.social {
  padding: 0 1rem 2rem;
  max-width: 36rem;
  margin: 0 auto;
}
.social__h1 {
  color: #ff0055;
  font-size: 1.35rem;
}
.social__h2 {
  font-size: 1rem;
  color: #ccc;
  margin: 0 0 0.5rem;
}
.social__lead {
  font-size: 0.9rem;
  color: #999;
  line-height: 1.45;
  margin: 0 0 0.75rem;
}
.social__block {
  margin-bottom: 1.75rem;
}
.social__block--search {
  position: relative;
  z-index: 20;
}
.social__search-wrap {
  position: relative;
}
.social__label {
  display: block;
  font-size: 0.85rem;
  color: #aaa;
  margin-bottom: 0.35rem;
}
.social__input {
  padding: 0.55rem 0.75rem;
  border-radius: 8px;
  border: 1px solid #333;
  background: #111;
  color: #fff;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}
.social__input--search {
  font-size: 1rem;
}
.social__dropdown {
  list-style: none;
  padding: 0;
  margin: 0.35rem 0 0;
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  background: #161616;
  border: 1px solid #333;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
  max-height: 240px;
  overflow-y: auto;
  z-index: 50;
}
.social__dropdown-item {
  border-bottom: 1px solid #222;
}
.social__dropdown-item:last-child {
  border-bottom: none;
}
.social__dropdown-link {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.65rem 0.75rem;
  text-decoration: none;
  color: inherit;
}
.social__dropdown-link:hover {
  background: rgba(255, 0, 85, 0.08);
}
.social__dropdown-name {
  font-weight: 600;
  color: #eee;
}
.social__dropdown-user {
  font-size: 0.85rem;
  color: #888;
}
.social__invite-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.social__invite-card {
  padding: 0.75rem 0;
  border-bottom: 1px solid #222;
}
.social__invite-text {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
  color: #ddd;
  line-height: 1.4;
}
.social__invite-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.social__friend-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.social__friend-row {
  padding: 0.65rem 0;
  border-bottom: 1px solid #222;
}
.social__friend-link {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
  text-decoration: none;
  color: #eee;
}
.social__friend-link:hover .social__friend-name {
  color: #ff0055;
}
.social__friend-name {
  font-weight: 600;
}
.social__friend-user {
  font-size: 0.85rem;
  color: #888;
}
.social__notif-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.35rem;
  height: 1.35rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  background: #ff0055;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
}
.social__friend-hint {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  color: #666;
}
.social__btn {
  align-self: flex-start;
  padding: 0.45rem 0.9rem;
  background: #ff0055;
  border: none;
  border-radius: 6px;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
.social__btn--sm {
  font-size: 0.8rem;
}
.social__btn--ghost {
  background: #333;
}
.social__muted {
  color: #888;
}
.social__err {
  color: #ff6b6b;
}
.social__hint {
  font-size: 0.85rem;
  color: #666;
}
.social__a {
  color: #ff0055;
}
</style>
