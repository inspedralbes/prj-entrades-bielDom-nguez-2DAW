<template>
  <div>
    <h1 style="margin: 0 0 0.75rem; color: #ff0055; font-size: 1.35rem;">Dashboard</h1>
    <p style="color: #aaa; max-width: 40rem;">
      Resum i mètriques en temps real (T052). Aquí es connectarà Socket <code>admin:metrics</code>.
    </p>
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});
</script>
