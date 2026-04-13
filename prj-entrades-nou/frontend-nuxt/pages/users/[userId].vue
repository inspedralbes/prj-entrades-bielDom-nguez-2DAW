<template>
  <main class="user-public">
    <nav class="user-public__nav" aria-label="Navegació">
      <NuxtLink to="/social" class="user-public__back">← Social</NuxtLink>
      <NuxtLink to="/" class="user-public__back user-public__back--muted">Inici</NuxtLink>
    </nav>

    <div v-if="loading" class="user-public__skeleton" aria-busy="true">
      <div class="user-public__sk-avatar" />
      <div class="user-public__sk-line user-public__sk-line--lg" />
      <div class="user-public__sk-line user-public__sk-line--sm" />
    </div>

    <p v-else-if="err" class="user-public__err" role="alert">{{ err }}</p>

    <article v-else-if="profile" class="user-public__card">
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

      <section class="user-public__section" aria-label="Accions">
        <div v-if="profile.relationship === 'self'" class="user-public__actions">
          <p class="user-public__muted">
            Aquest és el teu perfil. Pots editar el nom i l’usuari des del teu compte.
          </p>
          <NuxtLink to="/profile" class="user-public__btn user-public__btn--ghost">
            Anar al meu perfil
          </NuxtLink>
        </div>

        <div v-else class="user-public__actions">
          <p v-if="profile.relationship === 'friend'" class="user-public__ok">
            Sou amics. Pots compartir entrades des de «Les meves entrades».
          </p>

          <template v-else-if="profile.relationship === 'none'">
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
import { useRoute } from 'vue-router';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const route = useRoute();
const { getJson, postJson, patchJson } = useAuthorizedApi();

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
    return 'T’han convidat';
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
  padding: 0 1rem 2.5rem;
  max-width: 26rem;
  margin: 0 auto;
  min-height: 60vh;
}
.user-public__nav {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
.user-public__back {
  color: #ff0055;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
}
.user-public__back--muted {
  color: #888;
  font-weight: 500;
}
.user-public__back:hover {
  text-decoration: underline;
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
  background: linear-gradient(165deg, rgba(255, 0, 85, 0.08) 0%, rgba(18, 18, 18, 0.95) 42%);
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
  background: linear-gradient(145deg, #ff0055 0%, #b3003d 100%);
  color: #fff;
  font-size: 1.65rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.02em;
  box-shadow: 0 4px 16px rgba(255, 0, 85, 0.25);
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
  color: #f8f8f8;
  font-size: 1.45rem;
  font-weight: 700;
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
  color: #ffb3cc;
  background: rgba(255, 0, 85, 0.15);
  border-radius: 6px;
  border: 1px solid rgba(255, 0, 85, 0.35);
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
  background: #ff0055;
  border: none;
  border-radius: 10px;
  color: #fff;
  font-weight: 600;
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
