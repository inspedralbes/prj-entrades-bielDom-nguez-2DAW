<script setup>
/**
 * Ruta legacy: el mapa interactiu viu a `/events/:id/seats`.
 */
const route = useRoute();
const rawId = route.params.eventId;
const eventId = Array.isArray(rawId) ? rawId[0] : rawId;

const fr = route.query.from;
if (fr !== undefined && fr !== null && String(fr).trim() !== '') {
  await navigateTo(
    { path: `/events/${eventId}/seats`, query: { from: String(fr).trim() } },
    { replace: true },
  );
} else {
  await navigateTo(`/events/${eventId}/seats`, { replace: true });
}
</script>

<template>
  <div />
</template>
