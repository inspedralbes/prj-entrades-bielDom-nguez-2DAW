<template>
  <div>
    <h1 style="margin: 0 0 0.75rem; color: #ff0055; font-size: 1.35rem;">Esdeveniments</h1>
    <p style="color: #aaa;">CRUD i import Discovery Feed (T052).</p>
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});
</script>
