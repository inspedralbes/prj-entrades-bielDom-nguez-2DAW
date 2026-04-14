<template>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <p class="admin-sidebar__title">Administració</p>
      <NuxtLink to="/" class="admin-sidebar__back">← Tornar a l’app</NuxtLink>
      <nav aria-label="Panell admin">
        <NuxtLink prefetch to="/admin">Dashboard</NuxtLink>
        <NuxtLink prefetch to="/admin/logs">Registre admin</NuxtLink>
        <NuxtLink prefetch to="/admin/events">Esdeveniments</NuxtLink>
        <NuxtLink prefetch to="/admin/reports">Informes</NuxtLink>
        <NuxtLink prefetch to="/admin/users">Usuaris</NuxtLink>
      </nav>
    </aside>
    <div class="admin-main">
      <slot />
    </div>
  </div>
</template>

<script setup>
</script>
