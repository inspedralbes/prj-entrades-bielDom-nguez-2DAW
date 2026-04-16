<template>
  <div class="social user-page">
    <header class="user-page-hero user-page-hero--spaced">
      <h1 class="user-page-title">
        Social
      </h1>
      <p class="user-page-lead">
        Cerca usuaris, sol·licituds i amistats.
      </p>
    </header>

    <section class="social__block social__block--search">
      <UserSearchInput
        v-model="searchQ"
        input-id="social-search-input"
        sr-label="Cerca usuaris"
        placeholder="Ex.: Maria o @maria"
        @input="onSearchInput"
        @clear="onSearchClear"
      >
        <ul
          v-if="searchDropdownVisible && searchResults.length > 0"
          class="social__dropdown"
          role="listbox"
        >
          <li v-for="u in searchResults" :key="u.id" class="social__dropdown-item" role="option">
            <NuxtLink
              :to="'/users/' + String(u.id) + '?from=social'"
              class="social__dropdown-link"
              @click="closeSearchDropdown"
            >
              <span class="social__dropdown-name">{{ u.name }}</span>
              <span class="social__dropdown-user">@{{ u.username }}</span>
            </NuxtLink>
          </li>
        </ul>
      </UserSearchInput>
      <div v-if="searchLoading" class="social__search-state">
        <span class="social__spinner" aria-hidden="true" />
        <span class="social__search-label">Cercant…</span>
      </div>
      <p v-if="searchHint" class="social__muted">{{ searchHint }}</p>
      <p v-if="searchErr" class="social__err">{{ searchErr }}</p>
    </section>

    <section class="social__block social__block--invites">
      <div class="social__section-head">
        <h2 class="social__section-title">Sol·licituds</h2>
        <span class="social__pending-pill">{{ pendingInviteLabel }}</span>
      </div>
      <p v-if="loading" class="social__muted">Carregant…</p>
      <ul v-else-if="invitesPendingOnly.length" class="social__invite-list">
        <li v-for="inv in invitesPendingOnly" :key="inv.id" class="social__invite-card">
          <div class="social__invite-user">
            <span class="social__avatar-placeholder">{{ inviteInitial(inv) }}</span>
            <div class="social__invite-user-text">
              <p class="social__invite-name">{{ inviteDisplayName(inv) }}</p>
              <p class="social__invite-userline">{{ inviteDisplayUsername(inv) }}</p>
            </div>
          </div>
          <p class="social__invite-text">{{ inviteDescription(inv) }}</p>
          <div v-if="inv.status === 'pending' && canAcceptInvite(inv)" class="social__invite-actions">
            <button type="button" class="social__btn social__btn--primary" @click="respond(inv.id, 'accept')">
              Acceptar
            </button>
            <button
              type="button"
              class="social__btn social__btn--ghost"
              @click="respond(inv.id, 'reject')"
            >
              Rebutjar
            </button>
          </div>
        </li>
      </ul>
      <p v-else class="social__muted">No tens sol·licituds recents.</p>
    </section>

    <section class="social__block">
      <h2 class="social__section-title social__section-title--solo">La teva gent</h2>
      <p v-if="loading" class="social__muted">Carregant…</p>
      <ul v-else-if="friends.length" class="social__friend-list">
        <li v-for="f in friends" :key="f.id" class="social__friend-row">
          <NuxtLink :to="'/users/' + String(f.id) + '?from=social'" class="social__friend-link">
            <div class="social__friend-main">
              <span class="social__avatar-placeholder">{{ friendInitial(f) }}</span>
              <div class="social__friend-copy">
                <span class="social__friend-name">{{ f.name }}</span>
                <span class="social__friend-user">@{{ f.username }}</span>
              </div>
            </div>
            <div class="social__friend-right">
              <span
                v-if="unreadCountForFriend(f.id) > 0"
                class="social__notif-badge"
                :aria-label="'Notificacions sense llegir: ' + unreadCountForFriend(f.id)"
              >{{ unreadCountForFriend(f.id) }}</span>
              <span class="material-symbols-outlined social__chev" aria-hidden="true">chevron_right</span>
            </div>
          </NuxtLink>
        </li>
      </ul>
      <p v-else class="social__muted">Encara no tens amics acceptats.</p>
    </section>

    <footer class="social__footer">
      <p>
        Necessites enviar entrades?
        <NuxtLink to="/tickets" class="social__a">Ves a Les meves entrades</NuxtLink>
      </p>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import UserSearchInput from '~/components/UserSearchInput.vue';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const auth = useAuthStore();
const route = useRoute();
const { getJson, postJson, patchJson } = useAuthorizedApi();

function emitUnreadRefresh () {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('app:notifications-updated'));
  }
}

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

const pendingInvitesCount = computed(() => {
  let total = 0;
  for (let i = 0; i < invites.value.length; i++) {
    if (invites.value[i].status === 'pending') {
      total = total + 1;
    }
  }
  return total;
});

const pendingInviteLabel = computed(() => {
  if (pendingInvitesCount.value === 1) {
    return '1 pendent';
  }
  return String(pendingInvitesCount.value) + ' pendents';
});

/** Només pendents: acceptades/rebutjades surten d’aquesta llista (no cal veure històric aquí). */
const invitesPendingOnly = computed(() => {
  const out = [];
  for (let i = 0; i < invites.value.length; i++) {
    const row = invites.value[i];
    if (row.status === 'pending') {
      out.push(row);
    }
  }
  return out;
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

function firstLetter (text) {
  if (!text) {
    return '?';
  }
  const t = String(text).trim();
  if (t === '') {
    return '?';
  }
  return t.charAt(0).toUpperCase();
}

function friendInitial (friend) {
  if (friend && friend.name) {
    return firstLetter(friend.name);
  }
  if (friend && friend.username) {
    return firstLetter(friend.username);
  }
  return '?';
}

function inviteDisplayName (inv) {
  const me = auth.user?.id;
  if (!me) {
    return 'Usuari';
  }
  if (String(inv.sender_id) === String(me)) {
    if (inv.receiver_name && String(inv.receiver_name).trim() !== '') {
      return String(inv.receiver_name);
    }
    if (inv.receiver_email && String(inv.receiver_email).trim() !== '') {
      return String(inv.receiver_email);
    }
    return 'Convidat';
  }
  if (inv.sender_name && String(inv.sender_name).trim() !== '') {
    return String(inv.sender_name);
  }
  return 'Usuari';
}

function inviteDisplayUsername (inv) {
  const me = auth.user?.id;
  if (!me) {
    return '';
  }
  if (String(inv.sender_id) === String(me)) {
    if (inv.receiver_username && String(inv.receiver_username).trim() !== '') {
      return '@' + String(inv.receiver_username);
    }
    return 'Sol·licitud enviada';
  }
  if (inv.sender_username && String(inv.sender_username).trim() !== '') {
    return '@' + String(inv.sender_username);
  }
  return 'Sol·licitud rebuda';
}

function inviteInitial (inv) {
  return firstLetter(inviteDisplayName(inv));
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

function onSearchClear () {
  searchQ.value = '';
  searchResults.value = [];
  searchErr.value = '';
  searchLoading.value = false;
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

async function markSocialNotificationsSeen () {
  try {
    await postJson('/api/notifications/mark-all-read', {});
  } catch (e) {
    let status = 0;
    if (e && typeof e === 'object') {
      if (e.statusCode !== undefined && e.statusCode !== null) {
        status = Number(e.statusCode);
      } else if (e.status !== undefined && e.status !== null) {
        status = Number(e.status);
      }
    }
    if (status !== 404) {
      console.error(e);
    }
  }
  await loadNotifications();
}

async function onSocketNotification (ev) {
  let nType = '';
  if (ev && ev.detail && typeof ev.detail.type === 'string') {
    nType = ev.detail.type;
  }
  if (nType === 'friend_request' || nType === 'friend_accepted') {
    reloadSocialLists();
  }
  if (route.path === '/social') {
    await markSocialNotificationsSeen();
  } else {
    await loadNotifications();
  }
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

async function reloadSocialLists () {
  try {
    const f = await getJson('/api/social/friends');
    friends.value = f.friends || [];
    const inv = await getJson('/api/social/friend-invites?direction=all');
    invites.value = inv.invites || [];
  } catch (e) {
    console.error(e);
  }
}

async function load () {
  loading.value = true;
  try {
    await reloadSocialLists();
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

watch(
  () => route.path,
  async (path) => {
    if (path !== '/social') {
      return;
    }
    await markSocialNotificationsSeen();
    emitUnreadRefresh();
  },
  { immediate: true },
);

onMounted(async () => {
  await load();
  if (typeof window !== 'undefined') {
    window.addEventListener('app:socket-notification', onSocketNotification);
    window.addEventListener('app:social-invites-updated', onSocialInvitesUpdated);
  }
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
  max-width: 32rem;
  margin: 0 auto;
  padding-bottom: 2.5rem;
}

.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
  line-height: 1;
}

.social__block {
  margin-bottom: 2rem;
}

.social__block--search {
  position: relative;
  z-index: 20;
}

.social__search-state {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.55rem;
  padding-left: 0.25rem;
}

.social__spinner {
  width: 0.95rem;
  height: 0.95rem;
  border-radius: 9999px;
  border: 2px solid rgba(217, 201, 0, 0.25);
  border-top-color: #d9c900;
  animation: social-spin 0.8s linear infinite;
}

.social__search-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #d9c900;
}

.social__dropdown {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0;
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  background: #1c1b1b;
  border: 1px solid rgba(74, 71, 51, 0.5);
  border-radius: 16px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
  max-height: 240px;
  overflow-y: auto;
  z-index: 50;
}
.social__dropdown-item {
  border-bottom: 1px solid rgba(74, 71, 51, 0.4);
}

.social__dropdown-item:last-child {
  border-bottom: none;
}

.social__dropdown-link {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.75rem 0.9rem;
  text-decoration: none;
  color: inherit;
}

.social__dropdown-link:hover {
  background: rgba(247, 230, 40, 0.08);
}

.social__dropdown-name {
  font-weight: 700;
  color: #e5e2e1;
}

.social__dropdown-user {
  font-size: 0.82rem;
  color: #959178;
}

.social__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.social__section-title {
  margin: 0;
  font-size: 0.66rem;
  font-family: Inter, system-ui, sans-serif;
  font-weight: 800;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #ccc7ac;
}

.social__section-title--solo {
  margin-bottom: 1rem;
}

.social__pending-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 1.45rem;
  padding: 0.2rem 0.55rem;
  border-radius: 9999px;
  background: #f7e628;
  color: #1f1c00;
  font-size: 0.62rem;
  font-family: Inter, system-ui, sans-serif;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.social__invite-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
}

.social__invite-card {
  position: relative;
  padding: 1rem;
  border-radius: 16px;
  border: 1px solid rgba(74, 71, 51, 0.45);
  background: #2a2a2a;
  overflow: hidden;
}

.social__invite-user {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.social__invite-user-text {
  min-width: 0;
}

.social__invite-name {
  margin: 0;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1rem;
  line-height: 1.15;
  font-weight: 800;
  color: #f7e628;
}

.social__invite-userline {
  margin: 0.2rem 0 0;
  font-size: 0.82rem;
  color: #ccc7ac;
}

.social__invite-text {
  margin: 0.9rem 0 0;
  font-size: 0.84rem;
  color: #e5e2e1;
  line-height: 1.4;
}

.social__invite-actions {
  display: flex;
  gap: 0.65rem;
  margin-top: 0.85rem;
}

.social__friend-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.social__friend-row {
  border-radius: 14px;
  border: 1px solid rgba(74, 71, 51, 0.24);
  background: #1c1b1b;
  overflow: hidden;
}

.social__friend-link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.85rem;
  padding: 0.8rem 0.9rem;
  text-decoration: none;
  color: #e5e2e1;
}

.social__friend-main {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  min-width: 0;
}

.social__friend-copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.social__friend-name {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.98rem;
  font-weight: 700;
  color: #e5e2e1;
}

.social__friend-user {
  font-size: 0.78rem;
  color: #959178;
}

.social__friend-right {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.social__chev {
  font-size: 1.2rem;
  color: #4a4733;
}

.social__friend-link:hover .social__chev {
  color: #d9c900;
}

.social__avatar-placeholder {
  width: 2.8rem;
  height: 2.8rem;
  flex-shrink: 0;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(145deg, #2f2e2e, #1f1f1f);
  border: 1px solid rgba(149, 145, 120, 0.28);
  color: #e5e2e1;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 900;
  font-size: 0.88rem;
}

.social__notif-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.32rem;
  border-radius: 999px;
  background: #ff4b4b;
  color: #ffffff;
  font-size: 0.68rem;
  font-family: Inter, system-ui, sans-serif;
  font-weight: 800;
}

.social__btn {
  flex: 1;
  min-height: 2.6rem;
  padding: 0.45rem 0.75rem;
  border-radius: 12px;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.68rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  font-weight: 900;
  cursor: pointer;
  border: 1px solid transparent;
  transition: transform 0.15s ease;
}

.social__btn:active {
  transform: scale(0.98);
}

.social__btn--primary {
  background: #f7e628;
  color: #1f1c00;
}

.social__btn--ghost {
  background: transparent;
  border-color: rgba(149, 145, 120, 0.4);
  color: #ccc7ac;
}

.social__muted {
  margin: 0.45rem 0 0;
  color: #959178;
  font-size: 0.84rem;
}

.social__err {
  margin: 0.45rem 0 0;
  color: #ffb4ab;
  font-size: 0.84rem;
}

.social__footer {
  margin-top: 0.4rem;
  padding-top: 0.5rem;
}

.social__footer p {
  margin: 0;
  font-size: 0.84rem;
  color: #959178;
}

.social__a {
  margin-left: 0.25rem;
  color: #d9c900;
  font-weight: 700;
  text-decoration: none;
}

.social__a:hover {
  text-decoration: underline;
}

@keyframes social-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
