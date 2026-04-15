<template>
  <div class="admin-shell">
    <aside class="admin-sidebar admin-sidebar--stack">
      <p class="admin-sidebar__title">Administració</p>
      <nav aria-label="Panell admin">
        <NuxtLink prefetch to="/admin" class="admin-sidebar__link">
          <span class="admin-sidebar__ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="3" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.35" />
              <rect x="13" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.35" />
              <rect x="3" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.35" />
              <rect x="13" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.35" />
            </svg>
          </span>
          Dashboard
        </NuxtLink>
        <NuxtLink prefetch to="/admin/logs" class="admin-sidebar__link">
          <span class="admin-sidebar__ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M8 5h12M8 12h12M8 19h8" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" />
              <path d="M4 5h.01M4 12h.01M4 19h.01" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
            </svg>
          </span>
          Registre admin
        </NuxtLink>
        <NuxtLink
          prefetch
          to="/admin/events"
          class="admin-sidebar__link"
          :class="{ 'router-link-active': isAdminEventsNavActive }"
        >
          <span class="admin-sidebar__ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7 3v3M17 3v3M4.5 8h15" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" />
              <rect x="4" y="5" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.35" />
            </svg>
          </span>
          Esdeveniments
        </NuxtLink>
        <NuxtLink prefetch to="/admin/analytics" class="admin-sidebar__link">
          <span class="admin-sidebar__ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 19V5M4 19h16" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" />
              <path d="M8 15v-4M12 15V9M16 15v-6M20 15v-3" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" />
            </svg>
          </span>
          Analítiques
        </NuxtLink>
        <NuxtLink prefetch to="/admin/users" class="admin-sidebar__link">
          <span class="admin-sidebar__ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="9" cy="8" r="3.25" stroke="currentColor" stroke-width="1.35" />
              <circle cx="17" cy="8" r="3.25" stroke="currentColor" stroke-width="1.35" />
              <path
                d="M4 20v-.6a4.8 4.8 0 0 1 4.8-4.8h.4M14.8 20v-.6a4.8 4.8 0 0 1 4.8-4.8h.4"
                stroke="currentColor"
                stroke-width="1.35"
                stroke-linecap="round"
              />
            </svg>
          </span>
          Usuaris
        </NuxtLink>
        <NuxtLink prefetch to="/admin/profile" class="admin-sidebar__link">
          <span class="admin-sidebar__ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="9" r="3.5" stroke="currentColor" stroke-width="1.35" />
              <path d="M6 19.5c0-3.5 2.5-5.5 6-5.5s6 2 6 5.5" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" />
            </svg>
          </span>
          Perfil
        </NuxtLink>
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
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '~/stores/auth';

const route = useRoute();

const isAdminEventsNavActive = computed(() => {
  const p = route.path;
  if (p === '/admin/events') {
    return true;
  }
  if (p.startsWith('/admin/events/')) {
    return true;
  }
  return false;
});

function logoutAdmin () {
  const auth = useAuthStore();
  auth.clearSession();
  navigateTo('/');
}
</script>

<style scoped>
.admin-sidebar__logout {
  margin-top: auto;
  width: 100%;
  padding: 1rem 1rem;
  border-radius: 9999px;
  border: none;
  background: #f7e628;
  color: #1f1c00;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.85rem;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  cursor: pointer;
  transition: opacity 0.2s ease;
}
.admin-sidebar__logout:hover {
  opacity: 0.9;
}
.admin-sidebar__logout:focus-visible {
  outline: 2px solid #ffee32;
  outline-offset: 2px;
}
</style>
