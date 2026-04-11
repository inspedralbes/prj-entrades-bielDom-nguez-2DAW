<template>
  <div class="social">
    <h1 class="social__h1">Social</h1>

    <section class="social__block">
      <h2 class="social__h2">Amics</h2>
      <p v-if="loading" class="social__muted">Carregant…</p>
      <ul v-else-if="friends.length" class="social__list">
        <li v-for="f in friends" :key="f.id" class="social__row">
          <span class="social__name">@{{ f.username }}</span>
          <span class="social__sub">{{ f.name }}</span>
        </li>
      </ul>
      <p v-else class="social__muted">Encara no tens amics acceptats.</p>
    </section>

    <section class="social__block">
      <h2 class="social__h2">Convidar per ID d’usuari</h2>
      <form class="social__form" @submit.prevent="sendInvite">
        <label class="social__label">
          ID usuari destinatari
          <input v-model.number="inviteUserId" type="number" min="1" class="social__input" required>
        </label>
        <button type="submit" class="social__btn" :disabled="inviteSending">
          Enviar invitació
        </button>
      </form>
      <p v-if="inviteMsg" class="social__ok">{{ inviteMsg }}</p>
      <p v-if="inviteErr" class="social__err">{{ inviteErr }}</p>
    </section>

    <section class="social__block">
      <h2 class="social__h2">Invitacions</h2>
      <ul v-if="invites.length" class="social__list">
        <li v-for="inv in invites" :key="inv.id" class="social__invite">
          <span>{{ inv.status }} · {{ inviteDirection(inv) }}</span>
          <template v-if="inv.status === 'pending' && canAcceptInvite(inv)">
            <button type="button" class="social__btn social__btn--sm" @click="respond(inv.id, 'accept')">
              Acceptar
            </button>
            <button type="button" class="social__btn social__btn--sm social__btn--ghost" @click="respond(inv.id, 'reject')">
              Rebutjar
            </button>
          </template>
        </li>
      </ul>
      <p v-else class="social__muted">Sense invitacions recents.</p>
    </section>

    <p class="social__hint">
      Per enviar una entrada a un amic, ves a <NuxtLink to="/tickets" class="social__a">Les meves entrades</NuxtLink>
      i obre el modal «Enviar entrada».
    </p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

const auth = useAuthStore();
const { getJson, postJson, patchJson } = useAuthorizedApi();

function inviteDirection (inv) {
  const me = auth.user?.id;
  if (!me) {
    return '';
  }
  return String(inv.sender_id) === String(me) ? 'enviada' : 'rebuda';
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

const loading = ref(true);
const friends = ref([]);
const invites = ref([]);
const inviteUserId = ref(null);
const inviteSending = ref(false);
const inviteMsg = ref('');
const inviteErr = ref('');

async function load () {
  loading.value = true;
  inviteErr.value = '';
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

async function sendInvite () {
  inviteSending.value = true;
  inviteMsg.value = '';
  inviteErr.value = '';
  try {
    await postJson('/api/social/friend-invites', { receiver_id: inviteUserId.value });
    inviteMsg.value = 'Invitació enviada.';
    await load();
  } catch (e) {
    inviteErr.value = e?.data?.message || e?.message || 'Error en enviar.';
  } finally {
    inviteSending.value = false;
  }
}

async function respond (inviteId, action) {
  try {
    await patchJson(`/api/social/friend-invites/${inviteId}`, { action });
    await load();
  } catch (e) {
    console.error(e);
  }
}

onMounted(load);
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
.social__block {
  margin-bottom: 1.75rem;
}
.social__list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.social__row {
  padding: 0.5rem 0;
  border-bottom: 1px solid #222;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.social__name {
  font-weight: 600;
}
.social__sub {
  font-size: 0.85rem;
  color: #888;
}
.social__form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 16rem;
}
.social__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: #aaa;
}
.social__input {
  padding: 0.45rem 0.6rem;
  border-radius: 6px;
  border: 1px solid #333;
  background: #111;
  color: #fff;
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
  margin-right: 0.35rem;
}
.social__btn--ghost {
  background: #333;
}
.social__invite {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #222;
  font-size: 0.9rem;
}
.social__muted {
  color: #888;
}
.social__ok {
  color: #7bed9f;
  margin: 0.5rem 0 0;
}
.social__err {
  color: #ff6b6b;
  margin: 0.5rem 0 0;
}
.social__hint {
  font-size: 0.85rem;
  color: #666;
}
.social__a {
  color: #ff0055;
}
</style>
