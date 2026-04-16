<template>
  <div class="user-profile-editor">
    <p v-if="error" class="user-profile-editor__err">{{ error }}</p>
    <p v-else-if="loading" class="user-profile-editor__muted">Carregant…</p>
    <form v-else class="user-profile-editor__form" @submit.prevent="saveProfile">
      <section class="user-profile-editor__section">
        <div class="user-profile-editor__section-head">
          <h2 class="user-profile-editor__section-title">
            Dades del compte
          </h2>
          <div class="user-profile-editor__section-bar" aria-hidden="true" />
        </div>

        <div class="user-profile-editor__field">
          <label class="user-profile-editor__label-text" for="profile-username">Nom d’usuari</label>
          <div class="user-profile-editor__input-wrap">
            <span class="material-symbols-outlined user-profile-editor__ico" aria-hidden="true">badge</span>
            <input
              id="profile-username"
              v-model="username"
              type="text"
              class="user-profile-editor__input user-profile-editor__input--ico"
              required
              autocomplete="username"
            >
          </div>
        </div>

        <div class="user-profile-editor__field">
          <label class="user-profile-editor__label-text" for="profile-email">Correu electrònic</label>
          <div class="user-profile-editor__input-wrap">
            <span class="material-symbols-outlined user-profile-editor__ico" aria-hidden="true">mail</span>
            <input
              id="profile-email"
              v-model="email"
              type="email"
              class="user-profile-editor__input user-profile-editor__input--ico"
              required
              autocomplete="email"
            >
          </div>
        </div>
      </section>

      <section class="user-profile-editor__section">
        <div class="user-profile-editor__section-head">
          <h2 class="user-profile-editor__section-title">
            Seguretat
          </h2>
          <div class="user-profile-editor__section-bar" aria-hidden="true" />
        </div>
        <p class="user-profile-editor__hint">Deixa els tres camps buits si no vols canviar la contrasenya.</p>

        <div class="user-profile-editor__field">
          <label class="user-profile-editor__label-text" for="profile-current-password">Contrasenya actual</label>
          <div class="user-profile-editor__input-wrap">
            <span class="material-symbols-outlined user-profile-editor__ico" aria-hidden="true">lock</span>
            <input
              id="profile-current-password"
              v-model="currentPassword"
              :type="currentPasswordFieldType"
              class="user-profile-editor__input user-profile-editor__input--ico user-profile-editor__input--pwd"
              autocomplete="current-password"
            >
            <button
              type="button"
              class="user-profile-editor__pwd-toggle"
              :aria-pressed="currentPasswordVisible"
              :aria-label="currentPasswordToggleAriaLabel"
              @click="toggleCurrentPasswordVisible"
            >
              <span class="material-symbols-outlined" aria-hidden="true">{{ currentPasswordToggleIcon }}</span>
            </button>
          </div>
        </div>

        <div class="user-profile-editor__field">
          <label class="user-profile-editor__label-text" for="profile-new-password">Nova contrasenya</label>
          <div class="user-profile-editor__input-wrap">
            <span class="material-symbols-outlined user-profile-editor__ico" aria-hidden="true">lock</span>
            <input
              id="profile-new-password"
              v-model="newPassword"
              :type="newPasswordFieldType"
              class="user-profile-editor__input user-profile-editor__input--ico user-profile-editor__input--pwd"
              autocomplete="new-password"
            >
            <button
              type="button"
              class="user-profile-editor__pwd-toggle"
              :aria-pressed="newPasswordVisible"
              aria-label="Mostrar o amagar la nova contrasenya"
              @click="toggleNewPasswordVisible"
            >
              <span class="material-symbols-outlined" aria-hidden="true">{{ newPasswordToggleIcon }}</span>
            </button>
          </div>
        </div>

        <div class="user-profile-editor__field">
          <label class="user-profile-editor__label-text" for="profile-confirm-password">Confirma la nova contrasenya</label>
          <div class="user-profile-editor__input-wrap">
            <span class="material-symbols-outlined user-profile-editor__ico" aria-hidden="true">lock</span>
            <input
              id="profile-confirm-password"
              v-model="newPasswordConfirmation"
              :type="confirmPasswordFieldType"
              class="user-profile-editor__input user-profile-editor__input--ico user-profile-editor__input--pwd"
              autocomplete="new-password"
            >
            <button
              type="button"
              class="user-profile-editor__pwd-toggle"
              :aria-pressed="newPasswordConfirmVisible"
              aria-label="Mostrar o amagar la confirmació"
              @click="toggleNewPasswordConfirmVisible"
            >
              <span class="material-symbols-outlined" aria-hidden="true">{{ newPasswordConfirmToggleIcon }}</span>
            </button>
          </div>
        </div>
      </section>

      <div class="user-profile-editor__form-footer">
        <p v-if="fieldErrors.general" class="user-profile-editor__err">{{ fieldErrors.general }}</p>
        <p v-if="fieldErrors.username" class="user-profile-editor__err">{{ fieldErrors.username }}</p>
        <p v-if="fieldErrors.email" class="user-profile-editor__err">{{ fieldErrors.email }}</p>
        <p v-if="fieldErrors.current_password" class="user-profile-editor__err">{{ fieldErrors.current_password }}</p>
        <p v-if="fieldErrors.password" class="user-profile-editor__err">{{ fieldErrors.password }}</p>

        <div class="user-profile-editor__btn-row">
          <button type="submit" class="user-profile-editor__btn user-profile-editor__btn--primary" :disabled="saving">{{ saving ? 'Desant…' : 'Desar els canvis' }}</button>
          <button
            v-if="showLogout"
            type="button"
            class="user-profile-editor__btn user-profile-editor__btn--secondary"
            @click="logout"
          >
            Tancar la sessió
          </button>
        </div>
        <p v-if="savedMsg" class="user-profile-editor__ok">{{ savedMsg }}</p>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { useAuthorizedApi } from '../composables/useAuthorizedApi.js';

defineProps({
  /** Al panell admin es mostra sense tancar sessió (ja hi ha el botó al menú lateral). */
  showLogout: {
    type: Boolean,
    default: true,
  },
});

const auth = useAuthStore();
const { getJson, patchJson } = useAuthorizedApi();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const savedMsg = ref('');
const username = ref('');
const email = ref('');
const currentPassword = ref('');
const currentPasswordVisible = ref(false);
const newPassword = ref('');
const newPasswordConfirmation = ref('');
const fieldErrors = ref({
  general: '',
  username: '',
  email: '',
  current_password: '',
  password: '',
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

const newPasswordVisible = ref(false);
const newPasswordConfirmVisible = ref(false);

const newPasswordFieldType = computed(() => {
  if (newPasswordVisible.value) {
    return 'text';
  }
  return 'password';
});

const confirmPasswordFieldType = computed(() => {
  if (newPasswordConfirmVisible.value) {
    return 'text';
  }
  return 'password';
});

const currentPasswordToggleIcon = computed(() => {
  if (currentPasswordVisible.value) {
    return 'visibility';
  }
  return 'visibility_off';
});

const newPasswordToggleIcon = computed(() => {
  if (newPasswordVisible.value) {
    return 'visibility';
  }
  return 'visibility_off';
});

const newPasswordConfirmToggleIcon = computed(() => {
  if (newPasswordConfirmVisible.value) {
    return 'visibility';
  }
  return 'visibility_off';
});

function toggleNewPasswordVisible () {
  newPasswordVisible.value = !newPasswordVisible.value;
}

function toggleNewPasswordConfirmVisible () {
  newPasswordConfirmVisible.value = !newPasswordConfirmVisible.value;
}

function clearFieldErrors () {
  fieldErrors.value = {
    general: '',
    username: '',
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
  for (let i = 0; i < keys.length; i = i + 1) {
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
    username.value = p.username || '';
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
    username: username.value.trim(),
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
.user-profile-editor {
  padding: 0;
  max-width: 28rem;
}

.user-profile-editor .material-symbols-outlined {
  font-variation-settings:
    'FILL' 0,
    'wght' 400,
    'GRAD' 0,
    'opsz' 24;
}

.user-profile-editor__form {
  display: flex;
  flex-direction: column;
  gap: 0;
  margin-top: 1rem;
}

/* Sense caixes: mateix criteri que login/registre (fons pàgina únic) */
.user-profile-editor__section {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  margin: 0;
  padding: 0;
  background: none;
  border: none;
}

.user-profile-editor__section + .user-profile-editor__section {
  margin-top: 1.75rem;
}

/* Capçalera de secció: títol + barra groga immediatament sota, alineada amb les etiquetes (1rem) */
.user-profile-editor__section-head {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.45rem;
  width: 100%;
  box-sizing: border-box;
}

.user-profile-editor__section-title {
  margin: 0;
  padding: 0 1rem;
  width: 100%;
  box-sizing: border-box;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.7rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #f7e628;
  font-weight: 800;
  line-height: 1.25;
}

/* Barra tipus PULSE: mateix inici que el text del títol (padding 1rem) */
.user-profile-editor__section-bar {
  width: 3rem;
  height: 4px;
  margin: 0 0 0 1rem;
  border-radius: 9999px;
  background: #f7e628;
  flex-shrink: 0;
}

.user-profile-editor__hint {
  margin: 0;
  padding: 0 1rem;
  font-size: 0.75rem;
  line-height: 1.45;
  color: rgba(204, 199, 172, 0.75);
}

.user-profile-editor__field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* Mateix patró que `.login-page__label` */
.user-profile-editor__label-text {
  display: block;
  padding: 0 1rem;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(204, 199, 172, 0.85);
}

.user-profile-editor__input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.user-profile-editor__ico {
  position: absolute;
  left: 1.25rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 1.25rem;
  color: rgba(204, 199, 172, 0.75);
  pointer-events: none;
  z-index: 1;
}

.user-profile-editor__input {
  height: 3.5rem;
  width: 100%;
  box-sizing: border-box;
  padding: 0 1rem 0 1rem;
  border-radius: 1rem;
  border: 1px solid rgba(74, 71, 51, 0.2);
  background: #0e0e0e;
  color: #e5e2e1;
  font-family: Inter, system-ui, sans-serif;
  font-size: 1rem;
  transition: border-color 0.2s ease;
}

.user-profile-editor__input--ico {
  padding-left: 3.5rem;
}

.user-profile-editor__input--pwd {
  padding-right: 3.5rem;
}

.user-profile-editor__input:focus {
  outline: none;
  border-color: #f7e628;
  box-shadow: none;
}

.user-profile-editor__pwd-toggle {
  position: absolute;
  right: 1.25rem;
  top: 50%;
  transform: translateY(-50%);
  padding: 0;
  border: none;
  background: transparent;
  color: rgba(204, 199, 172, 0.85);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-profile-editor__pwd-toggle:hover {
  color: #e5e2e1;
}

.user-profile-editor__pwd-toggle:focus-visible {
  outline: 2px solid #f7e628;
  outline-offset: 2px;
  border-radius: 4px;
}

.user-profile-editor__pwd-toggle .material-symbols-outlined {
  font-size: 1.25rem;
}

/* Espai generós entre últim camp i botons; i entre els dos botons */
.user-profile-editor__form-footer {
  margin-top: 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.user-profile-editor__btn-row {
  display: flex;
  flex-direction: column;
  gap: 1.85rem;
}

.user-profile-editor__btn {
  width: 100%;
  min-height: 4rem;
  padding: 0 1rem;
  font-weight: 700;
  cursor: pointer;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-size: 0.8rem;
  transition:
    transform 0.15s ease,
    opacity 0.2s ease,
    background 0.2s ease;
}

.user-profile-editor__btn--primary {
  border: none;
  border-radius: 9999px;
  background: #f7e628;
  color: #1f1c00;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.05rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  box-shadow: 0 16px 36px rgba(247, 230, 40, 0.12);
}

.user-profile-editor__btn--primary:hover:not(:disabled) {
  transform: scale(1.02);
}

.user-profile-editor__btn--primary:active:not(:disabled) {
  transform: scale(0.98);
}

.user-profile-editor__btn--secondary {
  border-radius: 9999px;
  border: 1px solid rgba(74, 71, 51, 0.2);
  background: #353534;
  color: #fff;
  font-family: Epilogue, system-ui, sans-serif;
  font-weight: 800;
  font-size: 0.78rem;
}

.user-profile-editor__btn--secondary:hover {
  background: #3a3939;
}

.user-profile-editor__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.user-profile-editor__muted {
  color: var(--fg-muted, #ccc7ac);
}

.user-profile-editor__err {
  color: #ffb4ab;
  margin: 0;
  padding: 0 0.25rem;
  font-size: 0.875rem;
}

.user-profile-editor__ok {
  color: #7bed9f;
  margin: 0.35rem 0 0;
}
</style>
