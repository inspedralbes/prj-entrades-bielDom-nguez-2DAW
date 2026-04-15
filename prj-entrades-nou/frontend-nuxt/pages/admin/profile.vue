<template>
  <div class="adm-prof">
    <div class="adm-prof__column">
      <header class="admin-page-hero admin-page-hero--spaced">
        <h1 class="admin-page-title">
          Perfil
        </h1>
        <p class="admin-page-lead">
          Dades del compte, correu i canvi de contrasenya des del panell TR3.
        </p>
      </header>
      <p v-if="roleLine" class="adm-prof__roles">
        Rol: {{ roleLine }}
      </p>
      <UserProfileEditor :show-logout="false" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import UserProfileEditor from '~/components/UserProfileEditor.vue';
import { useAuthStore } from '~/stores/auth.js';

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
/* Centrat horitzontal i vertical dins l’àrea admin; títols via `.admin-page-*` globals (app.css). */
.adm-prof {
  box-sizing: border-box;
  width: 100%;
  min-width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 6rem);
  min-height: calc(100dvh - 6rem);
  padding: 0.5rem 0 2rem;
}

.adm-prof__column {
  width: 100%;
  max-width: 28rem;
  display: flex;
  flex-direction: column;
  align-items: stretch;
}

.adm-prof__roles {
  margin: 0 0 1.25rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #ffee32;
  background: #1c1b1b;
  border: 1px solid rgba(74, 71, 51, 0.35);
  border-radius: 9999px;
  padding: 0.55rem 0.9rem;
  align-self: center;
}

.adm-prof__column :deep(.user-profile-editor) {
  max-width: none;
}
</style>
