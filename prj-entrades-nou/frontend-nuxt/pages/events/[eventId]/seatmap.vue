<script setup>
/**
 * Ruta legacy: el mapa interactiu viu a `/events/:id/seats`.
 */
const route = useRoute();
const rawId = route.params.eventId;
const eventId = Array.isArray(rawId) ? rawId[0] : rawId;

await navigateTo(`/events/${eventId}/seats`, { replace: true });
</script>

<template>
  <div />
</template>
