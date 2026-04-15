<template>
  <AuthEmailPage mode="login" />
</template>

<script setup>
import AuthEmailPage from '../components/AuthEmailPage.vue'

// Sense layout perquè la pantalla d’auth és pantalla completa (sense app-shell).
// `layout: 'auth'` depèn del registre de layouts a `.nuxt`; en alguns entorns falla i deixa la pàgina en negre.
definePageMeta({
  layout: false,
})
</script>
