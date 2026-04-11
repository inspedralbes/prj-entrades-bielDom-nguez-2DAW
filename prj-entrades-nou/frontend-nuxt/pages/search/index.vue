<template>
  <div class="page-prose">
    <h1>Cercar esdeveniments</h1>
    <p>Llista amb filtres (T047). El mapa amb marcadors és a la vista mapa.</p>
    <p>
      <NuxtLink to="/search/map" class="link">Obrir mapa</NuxtLink>
    </p>
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'default',
});
</script>

<style scoped>
.link {
  color: #ff0055;
}
</style>
