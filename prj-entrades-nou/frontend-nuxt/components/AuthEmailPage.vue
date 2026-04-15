<template>
  <div class="login-page" :class="{ 'login-page--login-mode': !isRegister }">
    <div class="login-page__backdrop" aria-hidden="true">
      <div class="login-page__glow login-page__glow--tl" />
      <div class="login-page__glow login-page__glow--br" />
    </div>

    <main class="login-page__main">
      <header class="login-page__brand">
        <h1 class="login-page__logo">PULSE</h1>
        <div class="login-page__logo-bar" />
      </header>

      <section class="login-page__intro">
        <h2 class="login-page__title">
          {{ isRegister ? 'Crea el teu compte' : 'Benvingut de nou' }}
        </h2>
        <p class="login-page__lead">
          Accedeix al cor de l’experiència
        </p>
      </section>

      <form class="login-page__form" @submit.prevent="handleSubmit">
        <div v-if="error" class="login-page__banner" role="alert">
          <p class="login-page__banner-text">{{ error }}</p>
        </div>

        <template v-if="isRegister">
          <div class="login-page__field">
            <label class="login-page__label" :for="fieldId('name')">Nom</label>
            <div class="login-page__input-wrap">
              <span class="material-symbols-outlined login-page__ico" aria-hidden="true">person</span>
              <input
                :id="fieldId('name')"
                v-model="form.name"
                type="text"
                autocomplete="name"
                class="login-page__input"
                :class="{ 'login-page__input--err': errors.name }"
                placeholder="El teu nom"
              />
            </div>
            <p v-if="errors.name" class="login-page__err">{{ errors.name }}</p>
          </div>
        </template>

        <div class="login-page__field">
          <label class="login-page__label" :for="fieldId('email')">Correu electrònic</label>
          <div class="login-page__input-wrap">
            <span class="material-symbols-outlined login-page__ico" aria-hidden="true">mail</span>
            <input
              :id="fieldId('email')"
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="login-page__input"
              :class="{ 'login-page__input--err': errors.email }"
              placeholder="nom@domini.com"
            />
          </div>
          <p v-if="errors.email" class="login-page__err">{{ errors.email }}</p>
        </div>

        <div class="login-page__field">
          <label class="login-page__label" :for="fieldId('password')">Contrasenya</label>
          <div class="login-page__input-wrap">
            <span class="material-symbols-outlined login-page__ico" aria-hidden="true">lock</span>
            <input
              :id="fieldId('password')"
              v-model="form.password"
              name="password"
              :type="showPassword ? 'text' : 'password'"
              :autocomplete="isRegister ? 'new-password' : 'current-password'"
              :minlength="isRegister ? 8 : undefined"
              class="login-page__input login-page__input--pwd"
              :class="{ 'login-page__input--err': errors.password }"
              placeholder="••••••••"
            />
            <button
              class="login-page__pwd-toggle"
              type="button"
              :aria-label="showPassword ? 'Amaga la contrasenya' : 'Mostra la contrasenya'"
              @click="showPassword = !showPassword"
            >
              <span class="material-symbols-outlined" aria-hidden="true">{{ showPasswordIcon }}</span>
            </button>
          </div>
          <p v-if="isRegister" class="login-page__hint">Mínim 8 caràcters.</p>
          <p v-if="errors.password" class="login-page__err">{{ errors.password }}</p>
        </div>

        <template v-if="isRegister">
          <div class="login-page__field">
            <label class="login-page__label" :for="fieldId('password_confirmation')">Confirmar contrasenya</label>
            <div class="login-page__input-wrap">
              <span class="material-symbols-outlined login-page__ico" aria-hidden="true">lock</span>
              <input
                :id="fieldId('password_confirmation')"
                v-model="form.password_confirmation"
                name="password_confirmation"
                :type="showPasswordConfirm ? 'text' : 'password'"
                autocomplete="new-password"
                minlength="8"
                class="login-page__input login-page__input--pwd"
                :class="{ 'login-page__input--err': errors.password_confirmation }"
                placeholder="••••••••"
              />
              <button
                class="login-page__pwd-toggle"
                type="button"
                :aria-label="showPasswordConfirm ? 'Amaga la confirmació' : 'Mostra la confirmació'"
                @click="showPasswordConfirm = !showPasswordConfirm"
              >
                <span class="material-symbols-outlined" aria-hidden="true">{{ showPasswordConfirmIcon }}</span>
              </button>
            </div>
            <p v-if="errors.password_confirmation" class="login-page__err">{{ errors.password_confirmation }}</p>
          </div>
        </template>

        <button
          type="submit"
          class="login-page__submit"
          :disabled="loading"
        >
          {{ submitLabel }}
        </button>
      </form>

      <div class="login-page__divider">
        <div class="login-page__divider-line" />
        <span class="login-page__divider-text">Accés segur</span>
        <div class="login-page__divider-line" />
      </div>

      <footer class="login-page__footer">
        <p v-if="!isRegister" class="login-page__footer-text">
          Ets nou a la plataforma?
          <NuxtLink class="login-page__link-cta" :to="{ path: '/register', query: route.query }">
            Registra’t
          </NuxtLink>
        </p>
        <p v-else class="login-page__footer-text">
          Ja tens compte?
          <NuxtLink class="login-page__link-cta" :to="{ path: '/login', query: route.query }">
            Inicia sessió
          </NuxtLink>
        </p>
      </footer>
    </main>

    <div class="login-page__watermark" aria-hidden="true">
      <span class="login-page__watermark-text">PULSE</span>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../stores/auth.js'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator (value) {
      return value === 'login' || value === 'register'
    },
  },
})

const router = useRouter()
const route = useRoute()
const apiUrl = useRuntimeConfig().public?.apiUrl || 'http://localhost:8000'

const headTitle = computed(() => {
  if (props.mode === 'register') {
    return 'PULSE | Registre'
  }
  return 'PULSE | Inici de sessió'
})

useHead({
  title: headTitle,
  link: [
    {
      rel: 'stylesheet',
      href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0',
    },
  ],
})

const isRegister = computed(() => {
  return props.mode === 'register'
})

function fieldId (suffix) {
  if (props.mode === 'register') {
    return `register-${suffix}`
  }
  return `login-${suffix}`
}

const loading = ref(false)
const error = ref('')
const errors = ref({})
const showPassword = ref(false)
const showPasswordConfirm = ref(false)

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const showPasswordIcon = computed(() => {
  if (showPassword.value) {
    return 'visibility'
  }
  return 'visibility_off'
})

const showPasswordConfirmIcon = computed(() => {
  if (showPasswordConfirm.value) {
    return 'visibility'
  }
  return 'visibility_off'
})

const submitLabel = computed(() => {
  if (loading.value) {
    return 'Carregant...'
  }
  if (isRegister.value) {
    return 'Crear compte'
  }
  return 'Iniciar sessió'
})

/**
 * Laravel retorna errors per camp com a array de strings; la plantilla necessita un string per missatge.
 */
function normalizeLaravelErrors (raw) {
  const out = {}
  if (!raw || typeof raw !== 'object') {
    return out
  }
  const keys = Object.keys(raw)
  for (let i = 0; i < keys.length; i = i + 1) {
    const k = keys[i]
    const v = raw[k]
    if (Array.isArray(v) && v.length > 0 && typeof v[0] === 'string') {
      out[k] = v[0]
      continue
    }
    if (typeof v === 'string') {
      out[k] = v
    }
  }
  return out
}

function extract422Payload (err) {
  if (err && err.data && typeof err.data === 'object') {
    return err.data
  }
  if (err && err.response && err.response._data && typeof err.response._data === 'object') {
    return err.response._data
  }
  return null
}

let unlockBodyScroll = null

onMounted(() => {
  if (props.mode !== 'login') {
    return
  }
  const html = document.documentElement
  const body = document.body
  const prevHtmlOverflow = html.style.overflow
  const prevBodyOverflow = body.style.overflow
  const prevHtmlHeight = html.style.height
  const prevBodyHeight = body.style.height
  const prevHtmlOverscroll = html.style.overscrollBehavior
  const prevBodyOverscroll = body.style.overscrollBehavior
  html.style.overflow = 'hidden'
  body.style.overflow = 'hidden'
  html.style.height = '100%'
  body.style.height = '100%'
  html.style.overscrollBehavior = 'none'
  body.style.overscrollBehavior = 'none'
  unlockBodyScroll = () => {
    html.style.overflow = prevHtmlOverflow
    body.style.overflow = prevBodyOverflow
    html.style.height = prevHtmlHeight
    body.style.height = prevBodyHeight
    html.style.overscrollBehavior = prevHtmlOverscroll
    body.style.overscrollBehavior = prevBodyOverscroll
  }
})

onUnmounted(() => {
  if (unlockBodyScroll !== null) {
    unlockBodyScroll()
    unlockBodyScroll = null
  }
})

const handleSubmit = async () => {
  loading.value = true
  error.value = ''
  errors.value = {}

  try {
    const endpoint = isRegister.value ? '/api/auth/register' : '/api/auth/login'
    let body
    if (isRegister.value) {
      const pw = String(form.value.password ?? '')
      const pwc = String(form.value.password_confirmation ?? '')
      if (pw.length < 8) {
        errors.value = {
          password: 'La contrasenya ha de tenir com a mínim 8 caràcters.',
        }
        loading.value = false
        return
      }
      if (pwc.length < 8) {
        errors.value = {
          password_confirmation: 'La confirmació ha de tenir com a mínim 8 caràcters.',
        }
        loading.value = false
        return
      }
      if (pw !== pwc) {
        errors.value = {
          password_confirmation: 'La confirmació de la contrasenya no coincideix.',
        }
        loading.value = false
        return
      }
      body = {
        name: (form.value.name || '').trim(),
        email: (form.value.email || '').trim(),
        password: pw,
        password_confirmation: pwc,
      }
    } else {
      body = {
        email: (form.value.email || '').trim(),
        password: String(form.value.password ?? '').trim(),
      }
    }

    const res = await $fetch(`${apiUrl}${endpoint}`, {
      method: 'POST',
      body,
      headers: {
        Accept: 'application/json',
      },
    })

    if (res.token) {
      const tokenCookie = useCookie('auth_token')
      tokenCookie.value = res.token

      const auth = useAuthStore()
      auth.setSession({ token: res.token, user: res.user })

      let redirect = route.query.redirect
      if (typeof redirect !== 'string' || redirect.length === 0) {
        let isAdmin = false
        const r = res.user && res.user.roles
        if (r && Array.isArray(r)) {
          let i = 0
          for (; i < r.length; i++) {
            if (r[i] === 'admin') {
              isAdmin = true
              break
            }
          }
        }
        if (isAdmin) {
          redirect = '/admin'
        } else {
          redirect = '/tickets'
        }
      }
      router.push(redirect)
    }
  } catch (err) {
    const payload = extract422Payload(err)
    if (payload && payload.errors) {
      errors.value = normalizeLaravelErrors(payload.errors)
      const ek = Object.keys(errors.value)
      if (ek.length === 0 && payload.message && typeof payload.message === 'string') {
        error.value = payload.message
      }
    } else if (payload && payload.message) {
      error.value = payload.message
    } else {
      error.value = 'Error de connexió. Torna a intentar.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  --login-bg: #131313;
  --login-on-bg: #e5e2e1;
  --login-on-surface: #e5e2e1;
  --login-on-surface-variant: #ccc7ac;
  --login-surface-lowest: #0e0e0e;
  --login-outline-variant: #4a4733;
  --login-primary-container: #f7e628;
  --login-on-primary-fixed: #1f1c00;
  --login-primary-fixed-dim: #d9c900;
  --login-error: #ffb4ab;

  box-sizing: border-box;
  position: relative;
  min-height: max(884px, 100dvh);
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow-x: hidden;
  background: var(--login-bg);
  color: var(--login-on-bg);
  font-family: Inter, system-ui, sans-serif;
}

.login-page *,
.login-page *::before,
.login-page *::after {
  box-sizing: border-box;
}

.login-page__backdrop {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}

.login-page__glow {
  position: absolute;
  width: 40%;
  height: 40%;
  border-radius: 9999px;
  filter: blur(120px);
}

.login-page__glow--tl {
  top: -10%;
  left: -10%;
  background: rgba(247, 230, 40, 0.05);
}

.login-page__glow--br {
  bottom: -10%;
  right: -10%;
  background: rgba(247, 230, 40, 0.1);
}

.login-page__main {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 28rem;
  padding: 3rem 1.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 40px;
}

.login-page__brand {
  margin-bottom: 0;
  text-align: center;
}

.login-page__logo {
  margin: 0 0 0.5rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 2.25rem;
  font-weight: 900;
  font-style: italic;
  letter-spacing: -0.04em;
  text-transform: uppercase;
  color: var(--login-primary-container);
}

.login-page__logo-bar {
  height: 4px;
  width: 3rem;
  margin: 0 auto;
  border-radius: 9999px;
  background: var(--login-primary-container);
}

.login-page__intro {
  width: 100%;
  margin-bottom: 0;
  text-align: center;
}

.login-page__title {
  margin: 0 0 0.5rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.875rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--login-on-surface);
}

.login-page__lead {
  margin: 0;
  font-size: 0.7rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--login-on-surface-variant);
  line-height: 1.5;
}

.login-page__form {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.login-page__banner {
  padding: 0.75rem 1rem;
  border-radius: 1rem;
  border: 1px solid rgba(255, 180, 171, 0.35);
  background: rgba(147, 0, 10, 0.25);
}

.login-page__banner-text {
  margin: 0;
  font-size: 0.875rem;
  text-align: center;
  color: var(--login-error);
}

.login-page__field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.login-page__label {
  display: block;
  padding: 0 1rem;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--login-on-surface-variant);
}

.login-page__input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.material-symbols-outlined {
  font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.login-page__ico {
  position: absolute;
  left: 1.25rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 1.25rem;
  color: var(--login-on-surface-variant);
  pointer-events: none;
}

.login-page__input {
  width: 100%;
  height: 3.5rem;
  padding: 0 1.5rem 0 3.5rem;
  border-radius: 1rem;
  border: 1px solid rgba(74, 71, 51, 0.2);
  background: var(--login-surface-lowest);
  color: var(--login-on-surface);
  font-family: Inter, system-ui, sans-serif;
  font-size: 1rem;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.login-page__input::placeholder {
  color: rgba(204, 199, 172, 0.4);
}

.login-page__input:focus {
  outline: none;
  border-color: var(--login-primary-container);
  box-shadow: none;
}

.login-page__input--pwd {
  padding-right: 3.5rem;
}

.login-page__input--err {
  border-color: rgba(255, 180, 171, 0.6);
}

.login-page__pwd-toggle {
  position: absolute;
  right: 1.25rem;
  top: 50%;
  transform: translateY(-50%);
  padding: 0;
  border: none;
  background: transparent;
  color: var(--login-on-surface-variant);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.login-page__pwd-toggle:hover {
  color: var(--login-on-surface);
}

.login-page__hint {
  margin: 0;
  padding: 0 1rem;
  font-size: 0.75rem;
  color: rgba(204, 199, 172, 0.65);
}

.login-page__err {
  margin: 0;
  padding: 0 1rem;
  font-size: 0.875rem;
  color: var(--login-error);
}

.login-page__submit {
  width: 100%;
  height: 4rem;
  margin-top: 0.25rem;
  border: none;
  border-radius: 9999px;
  background: var(--login-primary-container);
  color: var(--login-on-primary-fixed);
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 1.125rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  text-transform: uppercase;
  cursor: pointer;
  box-shadow: 0 20px 40px rgba(247, 230, 40, 0.1);
  transition:
    transform 0.15s ease,
    opacity 0.2s ease;
}

.login-page__submit:hover:not(:disabled) {
  transform: scale(1.02);
}

.login-page__submit:active:not(:disabled) {
  transform: scale(0.97);
}

.login-page__submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.login-page__divider {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 0;
}

.login-page__divider-line {
  flex: 1;
  height: 1px;
  background: rgba(74, 71, 51, 0.2);
}

.login-page__divider-text {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--login-on-surface-variant);
  white-space: nowrap;
}

.login-page__footer {
  margin-top: 0;
  text-align: center;
}

.login-page__footer-text {
  margin: 0;
  font-size: 0.875rem;
  color: var(--login-on-surface-variant);
}

.login-page__link-cta {
  margin-left: 0.25rem;
  font-weight: 700;
  color: var(--login-primary-container);
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: rgba(247, 230, 40, 0.3);
}

.login-page__link-cta:hover {
  text-decoration-thickness: 2px;
}

.login-page__watermark {
  position: fixed;
  bottom: 2.5rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 0;
  opacity: 0.2;
  pointer-events: none;
}

.login-page__watermark-text {
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 6.5rem;
  font-weight: 900;
  letter-spacing: -0.04em;
  line-height: 1;
  color: rgba(149, 145, 120, 0.1);
  user-select: none;
}

/* Pantalla login mòbil: sense scroll, contingut centrat al mig (viewport fix). */
@media (max-width: 639px) {
  .login-page--login-mode {
    position: fixed;
    inset: 0;
    z-index: 1;
    width: 100%;
    min-height: 100dvh;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    overscroll-behavior: none;
    justify-content: center;
    align-items: stretch;
    -webkit-overflow-scrolling: auto;
  }

  .login-page--login-mode .login-page__main {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 1.5rem 1.5rem 1.75rem;
    max-width: 100%;
    min-height: 0;
    overflow: hidden;
  }

  .login-page--login-mode .login-page__main {
    gap: 32px;
  }

  .login-page--login-mode .login-page__brand {
    margin-bottom: 0;
  }

  .login-page--login-mode .login-page__intro {
    margin-bottom: 0;
  }

  .login-page--login-mode .login-page__title {
    font-size: 1.625rem;
    font-weight: 800;
  }

  .login-page--login-mode .login-page__form {
    gap: 20px;
  }

  .login-page--login-mode .login-page__divider {
    margin: 0;
    flex-shrink: 0;
  }

  .login-page--login-mode .login-page__footer {
    margin-top: 0;
    flex-shrink: 0;
  }

  .login-page--login-mode .login-page__watermark {
    bottom: 1.25rem;
  }

  .login-page--login-mode .login-page__watermark-text {
    font-size: 4.5rem;
  }

  .login-page--login-mode .login-page__submit {
    font-size: 0.8rem;
    letter-spacing: 0.12em;
    height: 3.35rem;
  }
}
</style>
