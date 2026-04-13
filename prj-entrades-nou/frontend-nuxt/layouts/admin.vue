<template>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <p class="admin-sidebar__title">Administració</p>
      <NuxtLink to="/" class="admin-sidebar__back">← Tornar a l’app</NuxtLink>
      <nav aria-label="Panell admin">
        <NuxtLink to="/admin">Dashboard</NuxtLink>
        <NuxtLink to="/admin/events">Esdeveniments</NuxtLink>
        <NuxtLink to="/admin/reports">Informes</NuxtLink>
        <NuxtLink to="/admin/users">Usuaris</NuxtLink>
        <NuxtLink to="/validator/scan">Control d’accés / escàner</NuxtLink>
      </nav>
    </aside>
    <div class="admin-main">
      <slot />
    </div>
  </div>
</template>

<script setup>
</script>
