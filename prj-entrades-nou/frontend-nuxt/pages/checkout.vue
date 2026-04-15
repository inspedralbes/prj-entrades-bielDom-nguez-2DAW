<script setup>
/**
 * Ruta /checkout: el flux de pagament dedicat encara no està implementat.
 * Redirecció a «Les meves entrades» (també definida a routeRules de nuxt.config).
 */
definePageMeta({
  layout: 'default',
  middleware: 'auth',
});

await navigateTo('/tickets', { replace: true });
</script>

<template>
  <p class="checkout-fallback">Redirigint…</p>
</template>

<style scoped>
.checkout-fallback {
  padding: 1.5rem;
  color: #959178;
  margin: 0;
}
</style>
