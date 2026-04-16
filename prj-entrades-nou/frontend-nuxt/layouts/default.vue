<template>
  <div class="app-shell">
    <header class="app-header">
      <NuxtLink prefetch to="/" class="app-logo" aria-label="Inici">Entrades</NuxtLink>
      <nav class="app-nav app-nav--desktop" aria-label="Navegació principal">
        <NuxtLink prefetch to="/" :class="{ 'app-nav__link--context-active': isFooterContextActive('home') }">Inici</NuxtLink>
        <NuxtLink
          prefetch
          to="/search"
          :class="{ 'app-nav__link--context-active': isFooterContextActive('search') }"
        >
          Cercar
        </NuxtLink>
        <NuxtLink prefetch to="/tickets" :class="{ 'app-nav__link--context-active': isFooterContextActive('tickets') }">Entrades</NuxtLink>
        <NuxtLink prefetch to="/saved" :class="{ 'app-nav__link--context-active': isFooterContextActive('saved') }">Guardats</NuxtLink>
        <NuxtLink prefetch to="/social" class="app-nav__link--social" :class="{ 'app-nav__link--context-active': isFooterContextActive('social') }">
          Social
          <span v-if="socialUnread > 0" class="app-nav__badge" aria-label="Notificacions sense llegir">{{ socialUnreadLabel }}</span>
        </NuxtLink>
        <ClientOnly>
          <NuxtLink v-if="showAdminLink" prefetch to="/admin" class="app-nav__link--admin">Administració</NuxtLink>
        </ClientOnly>
        <NuxtLink prefetch to="/profile" :class="{ 'app-nav__link--context-active': isFooterContextActive('profile') }">Perfil</NuxtLink>
      </nav>
    </header>

    <main
      class="app-main"
      :class="{ 'app-main--map-fill': isSearchMapRoute, 'app-main--seat-fill': isEventSeatsRoute }"
    >
      <slot />
    </main>

    <ClientOnly>
      <SocialToastStack />
    </ClientOnly>

    <footer class="app-footer app-footer--mobile" aria-label="Navegació mòbil">
      <nav class="app-nav app-nav--mobile">
        <NuxtLink prefetch to="/" class="app-nav__tab" :class="{ 'app-nav__tab--context-active': isFooterContextActive('home') }">
          <span class="app-nav__ico material-symbols-rounded" aria-hidden="true">home</span>
          <span class="app-nav__lab">Inici</span>
        </NuxtLink>
        <NuxtLink
          prefetch
          to="/search"
          class="app-nav__tab"
          :class="{ 'app-nav__tab--context-active': isFooterContextActive('search') }"
        >
          <span class="app-nav__ico material-symbols-rounded" aria-hidden="true">explore</span>
          <span class="app-nav__lab">Cercar</span>
        </NuxtLink>
        <NuxtLink prefetch to="/tickets" class="app-nav__tab" :class="{ 'app-nav__tab--context-active': isFooterContextActive('tickets') }">
          <span class="app-nav__ico material-symbols-rounded" aria-hidden="true">confirmation_number</span>
          <span class="app-nav__lab">Entrades</span>
        </NuxtLink>
        <NuxtLink prefetch to="/saved" class="app-nav__tab" :class="{ 'app-nav__tab--context-active': isFooterContextActive('saved') }">
          <span class="app-nav__ico material-symbols-rounded" aria-hidden="true">bookmark</span>
          <span class="app-nav__lab">Guardats</span>
        </NuxtLink>
        <NuxtLink prefetch to="/social" class="app-nav__tab app-nav__link--social" :class="{ 'app-nav__tab--context-active': isFooterContextActive('social') }">
          <span class="app-nav__ico material-symbols-rounded" aria-hidden="true">groups</span>
          <span class="app-nav__lab">Social</span>
          <span v-if="socialUnread > 0" class="app-nav__badge app-nav__badge--footer" aria-label="Notificacions sense llegir">{{ socialUnreadLabel }}</span>
        </NuxtLink>
        <ClientOnly>
          <NuxtLink v-if="showAdminLink" prefetch to="/admin" class="app-nav__tab app-nav__link--admin">
            <span class="app-nav__ico material-symbols-rounded" aria-hidden="true">settings</span>
            <span class="app-nav__lab">Admin</span>
          </NuxtLink>
        </ClientOnly>
        <NuxtLink prefetch to="/profile" class="app-nav__tab" :class="{ 'app-nav__tab--context-active': isFooterContextActive('profile') }">
          <span class="app-nav__ico material-symbols-rounded" aria-hidden="true">person</span>
          <span class="app-nav__lab">Perfil</span>
        </NuxtLink>
      </nav>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import SocialToastStack from '~/components/SocialToastStack.vue';
import { usePrivateTicketSocket } from '~/composables/usePrivateTicketSocket';
import { useAuthStore } from '~/stores/auth';
import { rolesIncludeAdmin } from '~/utils/userRoles';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useSocialThreadMutesStore } from '~/stores/socialThreadMutes';

const auth = useAuthStore();
const socialThreadMutes = useSocialThreadMutesStore();
usePrivateTicketSocket();

const showAdminLink = computed(() => {
  return rolesIncludeAdmin(auth.user && auth.user.roles ? auth.user.roles : []);
});

const route = useRoute();

/** Ruta mapa de cerca: ocupar tot l’alçària útil sense scroll; footer fix com la resta. */
const isSearchMapRoute = computed(() => {
  return route.path === '/search/map';
});

function normalizeFooterFromQuery () {
  const q = route.query.from;
  if (q === undefined || q === null) {
    return '';
  }
  return String(q).toLowerCase().trim();
}

/** Detall o seients d’esdeveniment: el tab «pare» ve de ?from= (p. ex. cercar, inici). */
function isEventDetailContextPath (path) {
  if (/^\/events\/[^/]+$/.test(path)) {
    return true;
  }
  if (/^\/events\/[^/]+\/seats$/.test(path)) {
    return true;
  }
  return false;
}

function isFooterContextActive (slug) {
  const path = route.path;
  const from = normalizeFooterFromQuery();

  if (slug === 'home') {
    if (path.startsWith('/users/')) {
      return false;
    }
    if (path === '/' || path === '') {
      return true;
    }
    if (isEventDetailContextPath(path) && from === 'home') {
      return true;
    }
    return false;
  }

  if (slug === 'search') {
    if (path === '/search') {
      return true;
    }
    if (path.startsWith('/search/')) {
      return true;
    }
    if (isEventDetailContextPath(path) && from === 'search') {
      return true;
    }
    return false;
  }

  if (slug === 'tickets') {
    if (path === '/tickets') {
      return true;
    }
    if (path.startsWith('/tickets/')) {
      return true;
    }
    if (isEventDetailContextPath(path) && from === 'tickets') {
      return true;
    }
    return false;
  }

  if (slug === 'saved') {
    if (path === '/saved') {
      return true;
    }
    if (isEventDetailContextPath(path) && from === 'saved') {
      return true;
    }
    return false;
  }

  if (slug === 'social') {
    if (path === '/social') {
      return true;
    }
    if (path.startsWith('/social/')) {
      return true;
    }
    if (path.startsWith('/users/')) {
      return true;
    }
    if (isEventDetailContextPath(path) && from === 'social') {
      return true;
    }
    return false;
  }

  if (slug === 'profile') {
    if (path === '/profile') {
      return true;
    }
    if (isEventDetailContextPath(path) && from === 'profile') {
      return true;
    }
    return false;
  }

  return false;
}

/** Selecció de seients: mateixa idea — mapa + barra compra sense scroll de pàgina. */
const isEventSeatsRoute = computed(() => {
  return /^\/events\/[^/]+\/seats$/.test(route.path);
});

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
});

function loadThreadMutesIfAuthed () {
  if (auth.token) {
    socialThreadMutes.fetchAll();
  }
}

onMounted(() => {
  if (typeof window === 'undefined') {
    return;
  }
  refreshSocialUnread();
  loadThreadMutesIfAuthed();
  window.addEventListener('app:socket-notification', onNotifRefresh);
  window.addEventListener('app:notifications-updated', onNotifRefresh);
});

watch(
  () => auth.token,
  () => {
    loadThreadMutesIfAuthed();
  },
);

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
  color: var(--accent-on);
  background: var(--accent);
  border-radius: 999px;
}
.app-nav__badge--footer {
  position: absolute;
  top: 2px;
  right: 4px;
  z-index: 2;
}
.app-nav__link--admin {
  font-weight: 700;
}
</style>
