import { watch } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase.js';

/**
 * Ping periòdic cap a /api/presence/ping per alimentar el ZSET Redis i el recompte online_users (admin).
 */
export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig();
  const auth = useAuthStore();
  const base = resolvePublicApiBaseUrl(config.public.apiUrl).replace(/\/$/, '');
  let timer = null;

  async function ping () {
    if (!auth.token) {
      return;
    }
    try {
      await $fetch(`${base}/api/presence/ping`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${auth.token}`,
          Accept: 'application/json',
        },
      });
    } catch (e) {
      console.warn('[presence-ping]', e);
    }
  }

  watch(
    () => auth.token,
    (tok) => {
      if (timer !== null) {
        clearInterval(timer);
        timer = null;
      }
      if (!tok) {
        return;
      }
      ping();
      timer = setInterval(ping, 30000);
    },
    { immediate: true },
  );

  nuxtApp.hook('app:beforeUnmount', () => {
    if (timer !== null) {
      clearInterval(timer);
      timer = null;
    }
  });
});
