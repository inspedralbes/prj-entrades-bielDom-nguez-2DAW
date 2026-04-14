<template>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <p class="admin-sidebar__title">Administració</p>
      <nav aria-label="Panell admin">
        <NuxtLink prefetch to="/admin">Dashboard</NuxtLink>
        <NuxtLink prefetch to="/admin/logs">Registre admin</NuxtLink>
        <NuxtLink prefetch to="/admin/events">Esdeveniments</NuxtLink>
        <NuxtLink prefetch to="/admin/analytics">Analítiques</NuxtLink>
        <NuxtLink prefetch to="/admin/users">Usuaris</NuxtLink>
        <NuxtLink prefetch to="/admin/profile">Perfil</NuxtLink>
      </nav>
      <button type="button" class="admin-sidebar__logout" @click="logoutAdmin">
        Tancar sessió
      </button>
    </aside>
    <div class="admin-main">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '~/stores/auth';

function logoutAdmin () {
  const auth = useAuthStore();
  auth.clearSession();
  navigateTo('/');
}
</script>

<style scoped>
.admin-sidebar__logout {
  margin-top: 1.25rem;
  width: 100%;
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  border: 1px solid #555;
  background: transparent;
  color: #ccc;
  font-size: 0.9rem;
  cursor: pointer;
  font-weight: 600;
}
.admin-sidebar__logout:hover {
  border-color: #ff0055;
  color: #ff0055;
}
.admin-sidebar__logout:focus-visible {
  outline: 2px solid #ff0055;
  outline-offset: 2px;
}
</style>
