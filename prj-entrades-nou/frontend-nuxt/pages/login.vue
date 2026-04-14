<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-md">
      <h1 class="text-2xl font-bold text-center mb-6">{{ isRegister ? 'Registre' : 'Inici de sessió' }}</h1>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-md">
          <p class="text-sm text-red-600 text-center">{{ error }}</p>
        </div>

        <template v-if="isRegister">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nom</label>
            <input
              v-model="form.name"
              type="text"
              autocomplete="name"
              class="mt-1 block w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              :class="errors.name ? 'border-red-500' : 'border-gray-300'"
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
          </div>
        </template>

        <div>
          <label class="block text-sm font-medium text-gray-700">Correu electrònic</label>
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            class="mt-1 block w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="errors.email ? 'border-red-500' : 'border-gray-300'"
          />
          <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Contrasenya</label>
          <input
            id="login-password"
            v-model="form.password"
            name="password"
            type="password"
            :autocomplete="isRegister ? 'new-password' : 'current-password'"
            :minlength="isRegister ? 8 : undefined"
            class="mt-1 block w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="errors.password ? 'border-red-500' : 'border-gray-300'"
          />
          <p v-if="isRegister" class="mt-1 text-xs text-gray-500">Mínim 8 caràcters.</p>
          <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
        </div>

        <template v-if="isRegister">
          <div>
            <label class="block text-sm font-medium text-gray-700">Confirmar contrasenya</label>
            <input
              id="login-password-confirmation"
              v-model="form.password_confirmation"
              name="password_confirmation"
              type="password"
              autocomplete="new-password"
              minlength="8"
              class="mt-1 block w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              :class="errors.password_confirmation ? 'border-red-500' : 'border-gray-300'"
            />
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ errors.password_confirmation }}</p>
          </div>
        </template>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {{ loading ? 'Carregant...' : (isRegister ? 'Crear compte' : 'Iniciar sessió') }}
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        {{ isRegister ? 'Ja tens compte?' : 'No tens compte?' }}
        <button @click="toggleMode" class="ml-1 text-blue-600 hover:underline font-medium">
          {{ isRegister ? 'Inicia sessió' : 'Registra-t' }}
        </button>
      </p>
    </div>
  </div>
</template>

<script setup>
const router = useRouter()
const route = useRoute()
const apiUrl = useRuntimeConfig().public?.apiUrl || 'http://localhost:8000'

const isRegister = ref(false)
const loading = ref(false)
const error = ref('')
const errors = ref({})

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const toggleMode = () => {
  isRegister.value = !isRegister.value
  error.value = ''
  errors.value = {}
}

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

definePageMeta({
  layout: 'default',
})
</script>