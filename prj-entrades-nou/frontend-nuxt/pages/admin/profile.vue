<template>
  <div class="adm-prof">
    <h1 class="adm-prof__h1">Perfil de l’administrador</h1>
    <p v-if="roleLine" class="adm-prof__roles">
      Rol: {{ roleLine }}
    </p>
    <p v-if="error" class="adm-prof__err">{{ error }}</p>
    <p v-else-if="loading" class="adm-prof__muted">Carregant…</p>
    <form v-else class="adm-prof__form" @submit.prevent="saveProfile">
      <label class="adm-prof__label">
        Nom
        <input v-model="name" type="text" class="adm-prof__input" required autocomplete="name">
      </label>
      <label class="adm-prof__label">
        Correu electrònic
        <input v-model="email" type="email" class="adm-prof__input" required autocomplete="email">
      </label>

      <div class="adm-prof__section">
        <p class="adm-prof__section-title">Canvi de contrasenya</p>
        <p class="adm-prof__hint">Deixa els tres camps buits si no vols canviar la contrasenya.</p>
        <label class="adm-prof__label">
          Contrasenya actual
          <div class="adm-prof__password-wrap">
            <input
              v-model="currentPassword"
              :type="currentPasswordFieldType"
              class="adm-prof__input adm-prof__input--with-toggle"
              autocomplete="current-password"
            >
            <button
              type="button"
              class="adm-prof__eye"
              :aria-pressed="currentPasswordVisible"
              :aria-label="currentPasswordToggleAriaLabel"
              @click="toggleCurrentPasswordVisible"
            >
              <span class="adm-prof__eye-icon" aria-hidden="true">
                <svg
                  v-if="!currentPasswordVisible"
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="1.5"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.182 4.182L9.88 9.88" />
                </svg>
              </span>
            </button>
          </div>
        </label>
        <label class="adm-prof__label">
          Nova contrasenya
          <input v-model="newPassword" type="password" class="adm-prof__input" autocomplete="new-password">
        </label>
        <label class="adm-prof__label">
          Confirma la nova contrasenya
          <input v-model="newPasswordConfirmation" type="password" class="adm-prof__input" autocomplete="new-password">
        </label>
      </div>

      <p v-if="fieldErrors.general" class="adm-prof__err">{{ fieldErrors.general }}</p>
      <p v-if="fieldErrors.name" class="adm-prof__err">{{ fieldErrors.name }}</p>
      <p v-if="fieldErrors.email" class="adm-prof__err">{{ fieldErrors.email }}</p>
      <p v-if="fieldErrors.current_password" class="adm-prof__err">{{ fieldErrors.current_password }}</p>
      <p v-if="fieldErrors.password" class="adm-prof__err">{{ fieldErrors.password }}</p>

      <button type="submit" class="adm-prof__btn adm-prof__btn--primary" :disabled="saving">
        {{ saving ? 'Desant…' : 'Desar els canvis' }}
      </button>
      <p v-if="savedMsg" class="adm-prof__ok">{{ savedMsg }}</p>
    </form>

    <div v-if="!loading" class="adm-prof__actions">
      <button type="button" class="adm-prof__btn adm-prof__btn--secondary" @click="logout">
        Tancar la sessió
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const auth = useAuthStore();
const { getJson, patchJson } = useAuthorizedApi();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const savedMsg = ref('');
const name = ref('');
const email = ref('');
const currentPassword = ref('');
const currentPasswordVisible = ref(false);
const newPassword = ref('');
const newPasswordConfirmation = ref('');
const fieldErrors = ref({
  general: '',
  name: '',
  email: '',
  current_password: '',
  password: '',
});

function roleLabelCa (raw) {
  const s = String(raw);
  if (s === 'admin') {
    return 'Administrador';
  }
  if (s === 'user') {
    return 'Usuari';
  }
  return s;
}

const roleLine = computed(() => {
  const u = auth.user;
  if (!u) {
    return '';
  }
  let raw = '';
  if (typeof u.role === 'string' && u.role.length > 0) {
    raw = u.role;
  } else if (Array.isArray(u.roles) && u.roles.length > 0) {
    raw = String(u.roles[0]);
  }
  if (raw.length === 0) {
    return '';
  }
  return roleLabelCa(raw);
});

const currentPasswordFieldType = computed(() => {
  if (currentPasswordVisible.value) {
    return 'text';
  }
  return 'password';
});

const currentPasswordToggleAriaLabel = computed(() => {
  if (currentPasswordVisible.value) {
    return 'Amagar la contrasenya';
  }
  return 'Mostrar la contrasenya';
});

function toggleCurrentPasswordVisible () {
  currentPasswordVisible.value = !currentPasswordVisible.value;
}

function clearFieldErrors () {
  fieldErrors.value = {
    general: '',
    name: '',
    email: '',
    current_password: '',
    password: '',
  };
}

function applyServerErrors (errors) {
  clearFieldErrors();
  if (!errors || typeof errors !== 'object') {
    return;
  }
  const keys = Object.keys(errors);
  let i = 0;
  for (; i < keys.length; i = i + 1) {
    const k = keys[i];
    const arr = errors[k];
    if (Array.isArray(arr) && arr.length > 0 && typeof arr[0] === 'string') {
      if (fieldErrors.value[k] !== undefined) {
        fieldErrors.value[k] = arr[0];
      } else {
        fieldErrors.value.general = arr[0];
      }
    }
  }
}

async function load () {
  loading.value = true;
  error.value = '';
  clearFieldErrors();
  try {
    const p = await getJson('/api/user/profile');
    name.value = p.name || '';
    email.value = p.email || '';
  } catch (e) {
    if (e?.status === 401) {
      navigateTo('/login');
      return;
    }
    error.value = 'No s\'ha pogut carregar el perfil.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function wantsPasswordChange () {
  const a = currentPassword.value.trim();
  const b = newPassword.value.trim();
  const c = newPasswordConfirmation.value.trim();
  if (a === '' && b === '' && c === '') {
    return false;
  }
  return true;
}

async function saveProfile () {
  saving.value = true;
  savedMsg.value = '';
  error.value = '';
  clearFieldErrors();

  if (wantsPasswordChange()) {
    if (currentPassword.value.trim() === '') {
      fieldErrors.value.current_password = 'Indica la contrasenya actual per canviar-la.';
      saving.value = false;
      return;
    }
    if (newPassword.value.trim() === '') {
      fieldErrors.value.password = 'Indica la nova contrasenya.';
      saving.value = false;
      return;
    }
    if (newPassword.value.trim().length < 8) {
      fieldErrors.value.password = 'La nova contrasenya ha de tenir com a mínim 8 caràcters.';
      saving.value = false;
      return;
    }
    if (newPassword.value.trim() !== newPasswordConfirmation.value.trim()) {
      fieldErrors.value.password = 'La confirmació no coincideix amb la nova contrasenya.';
      saving.value = false;
      return;
    }
  }

  const body = {
    name: name.value,
    email: email.value,
  };
  if (wantsPasswordChange()) {
    body.current_password = currentPassword.value;
    body.password = newPassword.value.trim();
    body.password_confirmation = newPasswordConfirmation.value.trim();
  }

  try {
    const p = await patchJson('/api/user/profile', body);
    const prevRoles = auth.user && Array.isArray(auth.user.roles) ? auth.user.roles : [];
    auth.setSession({
      token: auth.token,
      user: {
        ...auth.user,
        id: p.id,
        name: p.name,
        email: p.email,
        username: p.username,
        roles: prevRoles,
      },
    });
    savedMsg.value = 'Canvis desats.';
    currentPassword.value = '';
    currentPasswordVisible.value = false;
    newPassword.value = '';
    newPasswordConfirmation.value = '';
  } catch (e) {
    if (e?.status === 422 && e.data && e.data.errors) {
      applyServerErrors(e.data.errors);
    } else {
      error.value = 'No s\'han pogut desar els canvis. Torna a intentar.';
    }
    console.error(e);
  } finally {
    saving.value = false;
  }
}

function logout () {
  auth.clearSession();
  navigateTo('/login');
}

onMounted(load);
</script>

<style scoped>
.adm-prof {
  padding: 0 0 2rem;
  max-width: 28rem;
}
.adm-prof__h1 {
  margin: 0 0 0.5rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-prof__roles {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #888;
}
.adm-prof__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 0.5rem;
}
.adm-prof__section {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding-top: 0.5rem;
  border-top: 1px solid #333;
}
.adm-prof__section-title {
  margin: 0;
  font-size: 0.95rem;
  color: #ccc;
  font-weight: 600;
}
.adm-prof__hint {
  margin: 0;
  font-size: 0.8rem;
  color: #777;
}
.adm-prof__label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.9rem;
  color: #aaa;
}
.adm-prof__password-wrap {
  position: relative;
  display: block;
  width: 100%;
}
.adm-prof__input {
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  border: 1px solid #333;
  background: #111;
  color: #fff;
  width: 100%;
  box-sizing: border-box;
}
.adm-prof__input--with-toggle {
  padding-right: 2.75rem;
}
.adm-prof__eye {
  position: absolute;
  right: 0.25rem;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  padding: 0;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: #888;
  cursor: pointer;
}
.adm-prof__eye:hover {
  color: #ccc;
}
.adm-prof__eye:focus-visible {
  outline: 2px solid #ff0055;
  outline-offset: 2px;
}
.adm-prof__eye-icon {
  display: flex;
  line-height: 0;
}
.adm-prof__btn {
  align-self: flex-start;
  padding: 0.55rem 1.2rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}
.adm-prof__btn--primary {
  background: #ff0055;
  color: #fff;
}
.adm-prof__btn--secondary {
  background: transparent;
  color: #ccc;
  border: 1px solid #444;
}
.adm-prof__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.adm-prof__actions {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid #333;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  align-items: flex-start;
}
.adm-prof__muted {
  color: #888;
}
.adm-prof__err {
  color: #ff6b6b;
  margin: 0;
  font-size: 0.9rem;
}
.adm-prof__ok {
  color: #7bed9f;
  margin: 0;
}
</style>
