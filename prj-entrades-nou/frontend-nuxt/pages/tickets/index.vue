<template>
  <main style="min-height: 100vh; background: #000; color: #fff; padding: 2rem;">
    <h1 style="color: #ff0055;">Entrades</h1>
    <p>Ruta protegida (middleware <code>auth</code>).</p>
  </main>
</template>

<script setup>
definePageMeta({
  middleware: 'auth',
});
</script>
