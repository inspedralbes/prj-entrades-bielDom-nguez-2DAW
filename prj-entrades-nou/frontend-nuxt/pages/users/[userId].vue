<template>
  <main v-if="loading" class="user-public user-page">
    <nav class="user-public__nav" aria-label="Navegació">
      <button
        type="button"
        class="user-public__back-btn"
        aria-label="Tornar enrere"
        @click="goBack"
      >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      </button>
    </nav>
    <div class="user-public__skeleton" aria-busy="true">
      <div class="user-public__sk-avatar" />
      <div class="user-public__sk-line user-public__sk-line--lg" />
      <div class="user-public__sk-line user-public__sk-line--sm" />
    </div>
  </main>

  <main v-else-if="err" class="user-public user-page">
    <nav class="user-public__nav" aria-label="Navegació">
      <button
        type="button"
        class="user-public__back-btn"
        aria-label="Tornar enrere"
        @click="goBack"
      >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      </button>
    </nav>
    <p class="user-public__err" role="alert">{{ err }}</p>
  </main>

  <main
    v-else-if="profile && profile.relationship === 'friend'"
    class="friend-chat-page user-page"
  >
    <header class="friend-chat-page__bar">
      <button
        type="button"
        class="user-public__back-btn friend-chat-page__back"
        aria-label="Tornar enrere"
        @click="goBack"
      >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      </button>
      <div class="friend-chat-page__peer">
        <div class="friend-chat-page__miniava" aria-hidden="true">
          {{ avatarInitials }}
        </div>
        <div class="friend-chat-page__who">
          <span class="friend-chat-page__n">{{ profile.name }}</span>
          <span class="friend-chat-page__u">@{{ profile.username }}</span>
        </div>
      </div>
      <div class="friend-chat-page__menuslot">
        <button
          type="button"
          class="friend-chat-page__kebab"
          aria-label="Opcions del xat amb aquest amic"
          :aria-expanded="menuOpen"
          @click="menuOpen = !menuOpen"
        >
          <span class="material-symbols-outlined" aria-hidden="true">more_vert</span>
        </button>
        <div
          v-if="menuOpen"
          class="friend-chat-page__dropdown"
          role="menu"
        >
          <button
            type="button"
            role="menuitem"
            class="friend-chat-page__menu-item"
            @click="toggleThreadMute"
          >
            {{ threadMuteMenuLabel }}
          </button>
        </div>
      </div>
    </header>
    <FriendShareChat
      :peer-id="String(profile.id)"
      :peer-username="profile.username"
      @meta="onFriendChatMeta"
    />
  </main>

  <main v-else-if="profile" class="user-public user-page">
    <nav class="user-public__nav" aria-label="Navegació">
      <button
        type="button"
        class="user-public__back-btn"
        aria-label="Tornar enrere"
        @click="goBack"
      >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      </button>
    </nav>

    <article class="user-public__card">
      <header class="user-public__hero">
        <div class="user-public__avatar" aria-hidden="true">
          {{ avatarInitials }}
        </div>
        <div class="user-public__hero-text">
          <p class="user-public__kicker">Perfil públic</p>
          <h1 class="user-public__h1">{{ profile.name }}</h1>
          <p class="user-public__user">@{{ profile.username }}</p>
          <p v-if="relationshipChipText !== ''" class="user-public__chip">
            {{ relationshipChipText }}
          </p>
        </div>
      </header>

      <section
        class="user-public__section"
        aria-label="Accions"
      >
        <div v-if="profile.relationship === 'self'" class="user-public__actions">
          <p class="user-public__muted">
            Aquest és el teu perfil. Pots editar el nom i l’usuari des del teu compte.
          </p>
          <NuxtLink to="/profile" class="user-public__btn user-public__btn--ghost">
            Anar al meu perfil
          </NuxtLink>
        </div>

        <div v-else class="user-public__actions">
          <template v-if="profile.relationship === 'none'">
            <p class="user-public__lead">
              Envia una sol·licitud d’amistat per connectar i compartir esdeveniments i entrades.
            </p>
            <button
              type="button"
              class="user-public__btn"
              :disabled="inviteSending"
              @click="sendInvite"
            >
              Enviar sol·licitud d’amistat
            </button>
            <p v-if="inviteMsg" class="user-public__ok">{{ inviteMsg }}</p>
            <p v-if="inviteErr" class="user-public__err">{{ inviteErr }}</p>
          </template>

          <p v-else-if="profile.relationship === 'pending_sent'" class="user-public__muted">
            Ja has enviat una sol·licitud d’amistat. Quan l’altre usuari respongui, ho veuràs aquí i a Social.
          </p>

          <template v-else-if="profile.relationship === 'pending_received'">
            <p class="user-public__invite">
              Aquest usuari vol ser el teu amic.
            </p>
            <div class="user-public__row">
              <button
                type="button"
                class="user-public__btn"
                :disabled="respondLoading"
                @click="respondInvite('accept')"
              >
                Acceptar
              </button>
              <button
                type="button"
                class="user-public__btn user-public__btn--ghost"
                :disabled="respondLoading"
                @click="respondInvite('reject')"
              >
                Rebutjar
              </button>
            </div>
          </template>
        </div>
      </section>
    </article>
  </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FriendShareChat from '~/components/FriendShareChat.vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useSocialThreadMutesStore } from '~/stores/socialThreadMutes';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const router = useRouter();
const { getJson, postJson, patchJson } = useAuthorizedApi();
const socialThreadMutes = useSocialThreadMutesStore();

const menuOpen = ref(false);
const threadMuted = ref(false);

const threadMuteMenuLabel = computed(() => {
  if (threadMuted.value) {
    return 'Activar notificacions emergents';
  }
  return 'Silenciar notificacions emergents';
});

function onFriendChatMeta (payload) {
  if (payload && typeof payload.thread_notifications_muted === 'boolean') {
    threadMuted.value = payload.thread_notifications_muted;
  }
}

async function toggleThreadMute () {
  menuOpen.value = false;
  if (!profile.value) {
    return;
  }
  const next = !threadMuted.value;
  try {
    await patchJson(
      '/api/social/users/' + encodeURIComponent(String(profile.value.id)) + '/thread-notification-mute',
      { muted: next },
    );
    threadMuted.value = next;
    socialThreadMutes.setPeerMuted(String(profile.value.id), next);
  } catch (e) {
    console.error(e);
  }
}

function goBack () {
  const fromRaw = route.query.from;
  const from = fromRaw === undefined || fromRaw === null ? '' : String(fromRaw).toLowerCase().trim();
  if (from === 'social') {
    router.push('/social');
    return;
  }
  if (import.meta.client && typeof window !== 'undefined' && window.history.length > 1) {
    router.back();
    return;
  }
  router.push('/social');
}

const loading = ref(true);
const err = ref('');
const profile = ref(null);
const inviteSending = ref(false);
const inviteMsg = ref('');
const inviteErr = ref('');
const respondLoading = ref(false);

function userIdParam () {
  const raw = route.params.userId;
  if (Array.isArray(raw)) {
    if (raw[0] !== undefined && raw[0] !== null) {
      return String(raw[0]);
    }
    return '';
  }
  if (raw !== undefined && raw !== null) {
    return String(raw);
  }
  return '';
}

function computeInitials (name) {
  if (!name || typeof name !== 'string') {
    return '?';
  }
  const t = name.trim();
  if (t === '') {
    return '?';
  }
  const parts = [];
  const chunks = t.split(/\s+/);
  for (let i = 0; i < chunks.length; i++) {
    if (chunks[i] !== '') {
      parts.push(chunks[i]);
    }
  }
  if (parts.length === 0) {
    return '?';
  }
  if (parts.length === 1) {
    const w = parts[0];
    if (w.length === 1) {
      return w.toUpperCase();
    }
    return w.slice(0, 2).toUpperCase();
  }
  const first = parts[0].charAt(0);
  const last = parts[parts.length - 1].charAt(0);
  return (first + last).toUpperCase();
}

const avatarInitials = computed(() => {
  if (!profile.value || !profile.value.name) {
    return '?';
  }
  return computeInitials(profile.value.name);
});

const relationshipChipText = computed(() => {
  if (!profile.value) {
    return '';
  }
  const rel = profile.value.relationship;
  if (rel === 'friend') {
    return 'Amic';
  }
  if (rel === 'pending_sent') {
    return 'Sol·licitud enviada';
  }
  if (rel === 'pending_received') {
    return 'Sol·licitud rebuda';
  }
  return '';
});

function httpStatusFromError (e) {
  if (!e) {
    return 0;
  }
  if (e.status !== undefined && e.status !== null) {
    return Number(e.status);
  }
  if (e.statusCode !== undefined && e.statusCode !== null) {
    return Number(e.statusCode);
  }
  if (e.response && e.response.status !== undefined) {
    return Number(e.response.status);
  }
  return 0;
}

function applySeo (p) {
  if (!p || !p.name) {
    return;
  }
  useSeoMeta({
    title: p.name + ' · Perfil',
    description: 'Perfil públic de ' + p.name + ' (@' + p.username + ')',
  });
}

async function load () {
  loading.value = true;
  err.value = '';
  inviteMsg.value = '';
  inviteErr.value = '';
  const id = userIdParam();
  if (id === '') {
    err.value = 'Usuari invàlid.';
    profile.value = null;
    loading.value = false;
    return;
  }
  try {
    const p = await getJson(`/api/social/users/${encodeURIComponent(id)}`);
    profile.value = p;
    applySeo(p);
    if (p.relationship === 'friend') {
      try {
        await postJson('/api/notifications/mark-read-for-actor/' + encodeURIComponent(String(p.id)), {});
      } catch (markErr) {
        console.error(markErr);
      }
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('app:notifications-updated'));
      }
    }
  } catch (e) {
    profile.value = null;
    const st = httpStatusFromError(e);
    if (st === 404) {
      err.value = 'Usuari no trobat.';
    } else {
      err.value = 'No s\'ha pogut carregar el perfil. Torna-ho a provar.';
    }
  } finally {
    loading.value = false;
  }
}

async function sendInvite () {
  const id = userIdParam();
  if (id === '') {
    return;
  }
  inviteSending.value = true;
  inviteMsg.value = '';
  inviteErr.value = '';
  try {
    await postJson('/api/social/friend-invites', { receiver_id: parseInt(id, 10) });
    inviteMsg.value = 'Sol·licitud enviada.';
    await load();
  } catch (e) {
    let m = 'No s\'ha pogut enviar la sol·licitud.';
    if (e && e.data && e.data.message) {
      m = e.data.message;
    }
    inviteErr.value = m;
  } finally {
    inviteSending.value = false;
  }
}

async function respondInvite (action) {
  const invId = profile.value && profile.value.pending_invite_id;
  if (!invId) {
    return;
  }
  respondLoading.value = true;
  try {
    await patchJson(`/api/social/friend-invites/${encodeURIComponent(invId)}`, { action });
    await load();
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('app:social-invites-updated'));
    }
  } catch (e) {
    err.value = 'No s\'ha pogut respondre la sol·licitud.';
  } finally {
    respondLoading.value = false;
  }
}

watch(
  () => route.params.userId,
  () => {
    load();
  },
  { immediate: true },
);
</script>

<style scoped>
.user-public {
  max-width: 26rem;
  margin: 0 auto;
  min-height: 60vh;
}

.friend-chat-page {
  max-width: 32rem;
  margin: 0 auto;
  width: 100%;
  min-height: calc(100dvh - var(--footer-stack) - 0.75rem);
  display: flex;
  flex-direction: column;
  padding: 0 0.5rem 0.35rem;
  box-sizing: border-box;
}

@media (min-width: 900px) {
  .friend-chat-page {
    min-height: calc(100dvh - var(--header-h) - 2rem);
  }
}

.friend-chat-page__bar {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex-shrink: 0;
  padding: 0.25rem 0 0.55rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

.friend-chat-page__peer {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
  flex: 1;
}

.friend-chat-page__miniava {
  flex-shrink: 0;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background: linear-gradient(145deg, var(--accent) 0%, #5c5600 100%);
  color: var(--accent-on);
  font-size: 0.82rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 10px rgba(247, 230, 40, 0.2);
}

.friend-chat-page__who {
  display: flex;
  flex-direction: column;
  gap: 0.08rem;
  min-width: 0;
}

.friend-chat-page__n {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.98rem;
  font-weight: 800;
  color: #f5f5f5;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.friend-chat-page__u {
  font-size: 0.76rem;
  color: #8a8a8a;
}

.friend-chat-page__menuslot {
  position: relative;
  flex-shrink: 0;
}

.friend-chat-page__kebab {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.45rem;
  height: 2.45rem;
  padding: 0;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(30, 30, 30, 0.92);
  color: #c8c8c8;
  cursor: pointer;
}

.friend-chat-page__kebab:hover {
  opacity: 0.92;
}

.friend-chat-page__dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 6px);
  z-index: 50;
  min-width: 13rem;
  padding: 0.35rem 0;
  background: #1c1b1b;
  border: 1px solid rgba(74, 71, 51, 0.55);
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
}

.friend-chat-page__menu-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 0.65rem 0.95rem;
  font-size: 0.84rem;
  color: #e8e8e8;
  background: transparent;
  border: none;
  cursor: pointer;
}

.friend-chat-page__menu-item:hover {
  background: rgba(247, 230, 40, 0.08);
}

.user-public__nav {
  display: flex;
  align-items: center;
  margin-bottom: 1.25rem;
}

/* Mateix patró que `map-tr3__back` / seients: botó rodó, només icona. */
.user-public__back-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border-radius: 9999px;
  background: rgba(42, 42, 42, 0.9);
  border: 1px solid rgba(74, 71, 51, 0.35);
  color: #f7e628;
  cursor: pointer;
  transition: opacity 0.2s ease;
}

.user-public__back-btn:hover {
  opacity: 0.88;
}

.user-public__back-btn .material-symbols-outlined {
  font-size: 1.35rem;
  line-height: 1;
}
.user-public__skeleton {
  padding: 1rem 0;
}
.user-public__sk-avatar {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: linear-gradient(90deg, #222 25%, #2a2a2a 50%, #222 75%);
  background-size: 200% 100%;
  animation: user-public-shimmer 1.2s ease-in-out infinite;
  margin-bottom: 1rem;
}
.user-public__sk-line {
  height: 14px;
  border-radius: 6px;
  background: #222;
  margin-bottom: 0.65rem;
  animation: user-public-shimmer 1.2s ease-in-out infinite;
}
.user-public__sk-line--lg {
  width: 70%;
}
.user-public__sk-line--sm {
  width: 40%;
  height: 12px;
}
@keyframes user-public-shimmer {
  0% {
    opacity: 0.55;
  }
  50% {
    opacity: 1;
  }
  100% {
    opacity: 0.55;
  }
}
.user-public__card {
  background: linear-gradient(165deg, rgba(247, 230, 40, 0.1) 0%, rgba(18, 18, 18, 0.95) 42%);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 1.35rem 1.25rem 1.5rem;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
}

.user-public__hero {
  display: flex;
  gap: 1.1rem;
  align-items: flex-start;
  margin-bottom: 1.25rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.user-public__avatar {
  flex-shrink: 0;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: linear-gradient(145deg, var(--accent) 0%, #5c5600 100%);
  color: var(--accent-on);
  font-size: 1.65rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.02em;
  box-shadow: 0 4px 16px rgba(247, 230, 40, 0.22);
}
.user-public__hero-text {
  min-width: 0;
  flex: 1;
}
.user-public__kicker {
  margin: 0 0 0.35rem;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #888;
}
.user-public__h1 {
  font-family: Epilogue, system-ui, sans-serif;
  color: #f8f8f8;
  font-size: 1.45rem;
  font-weight: 800;
  margin: 0 0 0.25rem;
  line-height: 1.2;
  word-break: break-word;
}
.user-public__user {
  color: #9a9a9a;
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
}
.user-public__chip {
  display: inline-block;
  margin: 0;
  padding: 0.25rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #ccc7ac;
  background: rgba(247, 230, 40, 0.12);
  border-radius: 6px;
  border: 1px solid rgba(247, 230, 40, 0.35);
}
.user-public__section {
  margin: 0;
}
.user-public__lead {
  margin: 0 0 1rem;
  font-size: 0.92rem;
  color: #b8b8b8;
  line-height: 1.45;
}
.user-public__muted {
  color: #9a9a9a;
  font-size: 0.92rem;
  line-height: 1.45;
  margin: 0;
}
.user-public__err {
  color: #ff6b6b;
  font-size: 0.95rem;
  line-height: 1.4;
}
.user-public__ok {
  color: #7bed9f;
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.45;
}
.user-public__invite {
  color: #eee;
  margin: 0 0 0.85rem;
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.4;
}
.user-public__actions {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  align-items: flex-start;
}
.user-public__row {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.user-public__btn {
  padding: 0.55rem 1.15rem;
  background: var(--accent);
  border: none;
  border-radius: 9999px;
  color: var(--accent-on);
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.user-public__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.user-public__btn--ghost {
  background: #2c2c2c;
  color: #eee;
  border: 1px solid #444;
}
</style>
