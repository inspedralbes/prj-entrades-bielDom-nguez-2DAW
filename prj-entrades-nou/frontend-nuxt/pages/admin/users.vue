<template>
  <div>
    <h1 style="margin: 0 0 0.75rem; color: #ff0055; font-size: 1.35rem;">Usuaris i tickets</h1>
    <p style="color: #aaa;">Llistats (T052).</p>
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});
</script>
