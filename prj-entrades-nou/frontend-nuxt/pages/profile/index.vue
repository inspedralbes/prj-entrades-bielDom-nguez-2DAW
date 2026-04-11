<template>
  <div class="profile">
    <h1 class="profile__h1">Perfil</h1>
    <p v-if="error" class="profile__err">{{ error }}</p>
    <p v-else-if="loading" class="profile__muted">Carregant…</p>
    <form v-else class="profile__form" @submit.prevent="saveProfile">
      <label class="profile__label">
        Nom
        <input v-model="name" type="text" class="profile__input" required>
      </label>
      <label class="profile__label">
        Usuari
        <input v-model="username" type="text" class="profile__input" required>
      </label>
      <label class="profile__label">
        Correu
        <input :value="email" type="email" class="profile__input" disabled>
      </label>
      <label class="profile__toggle">
        <input v-model="geminiOn" type="checkbox">
        Personalització amb Gemini (recomanacions)
      </label>
      <button type="submit" class="profile__btn" :disabled="saving">
        Desar
      </button>
      <p v-if="savedMsg" class="profile__ok">{{ savedMsg }}</p>
    </form>
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
const { getJson, patchJson } = useAuthorizedApi();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const savedMsg = ref('');
const name = ref('');
const username = ref('');
const email = ref('');
const geminiOn = ref(true);

async function load () {
  loading.value = true;
  error.value = '';
  try {
    const p = await getJson('/api/user/profile');
    name.value = p.name || '';
    username.value = p.username || '';
    email.value = p.email || '';
    geminiOn.value = !!p.gemini_personalization_enabled;
  } catch (e) {
    error.value = 'No s’ha pogut carregar el perfil.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function saveProfile () {
  saving.value = true;
  savedMsg.value = '';
  try {
    const p = await patchJson('/api/user/profile', {
      name: name.value,
      username: username.value,
    });
    auth.setSession({ token: auth.token, user: { ...auth.user, name: p.name, username: p.username } });
    await patchJson('/api/user/settings', {
      gemini_personalization_enabled: geminiOn.value,
    });
    savedMsg.value = 'Desat.';
  } catch (e) {
    error.value = 'Error en desar.';
    console.error(e);
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.profile {
  padding: 0 1rem 2rem;
  max-width: 28rem;
  margin: 0 auto;
}
.profile__h1 {
  color: #ff0055;
  font-size: 1.35rem;
}
.profile__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 1rem;
}
.profile__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.9rem;
  color: #aaa;
}
.profile__input {
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  border: 1px solid #333;
  background: #111;
  color: #fff;
}
.profile__input:disabled {
  opacity: 0.5;
}
.profile__toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #ccc;
}
.profile__btn {
  align-self: flex-start;
  padding: 0.55rem 1.2rem;
  background: #ff0055;
  border: none;
  border-radius: 6px;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
.profile__muted {
  color: #888;
}
.profile__err {
  color: #ff6b6b;
}
.profile__ok {
  color: #7bed9f;
  margin: 0;
}
</style>
