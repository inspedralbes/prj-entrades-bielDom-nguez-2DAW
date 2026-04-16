<template>
  <AuthEmailPage mode="register" />
</template>

<script setup>
import AuthEmailPage from '../components/AuthEmailPage.vue'

definePageMeta({
  layout: false,
  middleware: 'guest',
})
</script>
