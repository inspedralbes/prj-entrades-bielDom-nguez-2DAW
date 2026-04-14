<template>
  <div class="app-shell">
    <header class="app-header">
      <NuxtLink prefetch to="/" class="app-logo" aria-label="Inici">Entrades</NuxtLink>
      <nav class="app-nav app-nav--desktop" aria-label="Navegació principal">
        <NuxtLink prefetch to="/">Inici</NuxtLink>
        <NuxtLink prefetch to="/search">Cercar</NuxtLink>
        <NuxtLink prefetch to="/tickets">Entrades</NuxtLink>
        <NuxtLink prefetch to="/saved">Guardats</NuxtLink>
        <NuxtLink prefetch to="/social" class="app-nav__link--social">
          Social
          <span v-if="socialUnread > 0" class="app-nav__badge" aria-label="Notificacions sense llegir">{{ socialUnreadLabel }}</span>
        </NuxtLink>
        <ClientOnly>
          <NuxtLink v-if="showAdminLink" prefetch to="/admin" class="app-nav__link--admin">Administració</NuxtLink>
        </ClientOnly>
        <NuxtLink prefetch to="/profile">Perfil</NuxtLink>
      </nav>
    </header>

    <main class="app-main">
      <slot />
    </main>

    <footer class="app-footer app-footer--mobile" aria-label="Navegació mòbil">
      <nav class="app-nav app-nav--mobile">
        <NuxtLink prefetch to="/">
          <span class="app-nav__ico" aria-hidden="true">⌂</span>
          Inici
        </NuxtLink>
        <NuxtLink prefetch to="/search">
          <span class="app-nav__ico" aria-hidden="true">⌕</span>
          Cercar
        </NuxtLink>
        <NuxtLink prefetch to="/tickets">
          <span class="app-nav__ico" aria-hidden="true">▦</span>
          Entrades
        </NuxtLink>
        <NuxtLink prefetch to="/saved">
          <span class="app-nav__ico" aria-hidden="true">♥</span>
          Guardats
        </NuxtLink>
        <NuxtLink prefetch to="/social" class="app-nav__link--social">
          <span class="app-nav__ico" aria-hidden="true">◎</span>
          Social
          <span v-if="socialUnread > 0" class="app-nav__badge app-nav__badge--footer" aria-label="Notificacions sense llegir">{{ socialUnreadLabel }}</span>
        </NuxtLink>
        <ClientOnly>
          <NuxtLink v-if="showAdminLink" prefetch to="/admin" class="app-nav__link--admin">
            <span class="app-nav__ico" aria-hidden="true">⚙</span>
            Admin
          </NuxtLink>
        </ClientOnly>
        <NuxtLink prefetch to="/profile">
          <span class="app-nav__ico" aria-hidden="true">☺</span>
          Perfil
        </NuxtLink>
      </nav>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

const auth = useAuthStore();

const showAdminLink = computed(() => {
  const roles = auth.user && Array.isArray(auth.user.roles) ? auth.user.roles : [];
  for (let i = 0; i < roles.length; i++) {
    if (roles[i] === 'admin') {
      return true;
    }
  }
  return false;
});

const route = useRoute();
const { getJson } = useAuthorizedApi();
const socialUnread = ref(0);

const socialUnreadLabel = computed(() => {
  if (socialUnread.value > 9) {
    return '9+';
  }
  return String(socialUnread.value);
});

async function refreshSocialUnread () {
  const auth = useCookie('auth_token');
  if (!auth.value) {
    socialUnread.value = 0;
    return;
  }
  try {
    const res = await getJson('/api/notifications?limit=100');
    const list = res.notifications || [];
    let c = 0;
    for (let i = 0; i < list.length; i++) {
      if (!list[i].read_at) {
        c++;
      }
    }
    socialUnread.value = c;
  } catch {
    socialUnread.value = 0;
  }
}

function onNotifRefresh () {
  refreshSocialUnread();
}

watch(() => route.path, (newPath) => {
  if (newPath !== '/') {
    localStorage.removeItem('home_proximity');
  }
  if (newPath === '/social') {
    refreshSocialUnread();
  }
});

onMounted(() => {
  if (typeof window === 'undefined') {
    return;
  }
  refreshSocialUnread();
  window.addEventListener('app:socket-notification', onNotifRefresh);
  window.addEventListener('app:notifications-updated', onNotifRefresh);
});

onUnmounted(() => {
  if (typeof window === 'undefined') {
    return;
  }
  window.removeEventListener('app:socket-notification', onNotifRefresh);
  window.removeEventListener('app:notifications-updated', onNotifRefresh);
});
</script>

<style scoped>
.app-nav__link--social {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.app-nav__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.1rem;
  height: 1.1rem;
  padding: 0 0.25rem;
  font-size: 0.65rem;
  font-weight: 700;
  line-height: 1;
  color: #fff;
  background: #ff0055;
  border-radius: 999px;
}
.app-nav__badge--footer {
  position: absolute;
  top: -2px;
  right: -4px;
}
.app-nav--mobile .app-nav__link--social {
  position: relative;
}
.app-nav__link--admin {
  font-weight: 700;
  color: #ff0055;
}
</style>
