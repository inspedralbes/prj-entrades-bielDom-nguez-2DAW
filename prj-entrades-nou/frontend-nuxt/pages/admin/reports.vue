<template>
  <span />
</template>

<script setup>
definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

await navigateTo('/admin/analytics', { replace: true });
</script>
