<template>
  <header class="seats-header">
    <NuxtLink
      :to="to"
      class="seats-back-btn"
      aria-label="Tornar a l'esdeveniment"
    >
      <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
    </NuxtLink>
  </header>
</template>

<script setup>
defineProps({
  to: {
    type: [String, Object],
    required: true,
  },
});
</script>

<style scoped>
.seats-header {
  display: flex;
  align-items: center;
  margin-bottom: 0.65rem;
}

.seats-back-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: rgba(42, 42, 42, 0.9);
  border: 1px solid rgba(74, 71, 51, 0.35);
  color: #ffee32;
  text-decoration: none;
  transition: opacity 0.2s ease;
}

.seats-back-btn:hover {
  opacity: 0.88;
}

.seats-back-btn .material-symbols-outlined {
  font-size: 1.35rem;
  line-height: 1;
}
</style>
