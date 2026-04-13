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
            class="mt-1 block w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="errors.email ? 'border-red-500' : 'border-gray-300'"
          />
          <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Contrasenya</label>
          <input
            v-model="form.password"
            type="password"
            class="mt-1 block w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="errors.password ? 'border-red-500' : 'border-gray-300'"
          />
          <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
        </div>

        <template v-if="isRegister">
          <div>
            <label class="block text-sm font-medium text-gray-700">Confirmar contrasenya</label>
            <input
              v-model="form.password_confirmation"
              type="password"
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
import { resolvePublicApiBaseUrl } from '~/utils/apiBase'

const router = useRouter()
const route = useRoute()
const config = useRuntimeConfig()

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

const handleSubmit = async () => {
  loading.value = true
  error.value = ''
  errors.value = {}

  try {
    const endpoint = isRegister.value ? '/api/auth/register' : '/api/auth/login'
    const body = isRegister.value ? {
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    } : {
      email: form.value.email,
      password: form.value.password,
    }

    const base = resolvePublicApiBaseUrl(config.public?.apiUrl || 'http://localhost:8000').replace(/\/$/, '')
    const res = await $fetch(`${base}${endpoint}`, {
      method: 'POST',
      body,
    })

    if (res.token) {
      const tokenCookie = useCookie('auth_token')
      tokenCookie.value = res.token
      
      const auth = useAuthStore()
      auth.setSession({ token: res.token, user: res.user })
      
      const redirect = route.query.redirect || '/tickets'
      router.push(redirect)
    }
  } catch (err) {
    if (err.data?.errors) {
      errors.value = err.data.errors
    } else if (err.data?.message) {
      error.value = err.data.message
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