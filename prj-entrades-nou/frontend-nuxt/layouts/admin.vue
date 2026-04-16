<template>
  <div class="admin-shell">
    <AdminSidebar />
    <div class="admin-main">
      <slot />
    </div>
  </div>
</template>

<script setup>
import AdminSidebar from '~/components/admin/AdminSidebar.vue';
</script>
