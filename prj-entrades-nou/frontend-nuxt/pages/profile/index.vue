<template>
  <div class="profile-page user-page">
    <header class="user-page-hero user-page-hero--spaced">
      <h1 class="user-page-title">
        El meu perfil
      </h1>
      <p class="user-page-lead">
        Dades del compte, contrasenya i sessió.
      </p>
    </header>
    <UserProfileEditor />
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'default',
  middleware: 'auth',
});
</script>

<style scoped>
.profile-page {
  max-width: 28rem;
  margin: 0 auto;
  padding-bottom: 2rem;
}
</style>
