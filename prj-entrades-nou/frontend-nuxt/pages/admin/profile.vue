<template>
  <div class="admin-profile-page">
    <header class="admin-page-hero admin-page-hero--spaced">
      <h1 class="admin-page-title">
        Perfil de l’administrador
      </h1>
      <p class="admin-page-lead">
        Dades del compte, correu i canvi de contrasenya des del panell TR3.
      </p>
    </header>
    <p v-if="roleLine" class="admin-profile-page__roles">
      Rol: {{ roleLine }}
    </p>
    <UserProfileEditor />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import UserProfileEditor from '../../components/UserProfileEditor.vue';
import { useAuthStore } from '../../stores/auth.js';

definePageMeta({
  name: 'admin-profile',
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const auth = useAuthStore();

function roleLabelCa (raw) {
  const s = String(raw);
  if (s === 'admin') {
    return 'Administrador';
  }
  if (s === 'user') {
    return 'Usuari';
  }
  return s;
}

const roleLine = computed(() => {
  const u = auth.user;
  if (!u) {
    return '';
  }
  let raw = '';
  if (typeof u.role === 'string' && u.role.length > 0) {
    raw = u.role;
  } else if (Array.isArray(u.roles) && u.roles.length > 0) {
    raw = String(u.roles[0]);
  }
  if (raw.length === 0) {
    return '';
  }
  return roleLabelCa(raw);
});
</script>

<style scoped>
.admin-profile-page {
  padding: 0 0 2rem;
  max-width: 28rem;
}
.admin-profile-page__roles {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: #888;
}
</style>
