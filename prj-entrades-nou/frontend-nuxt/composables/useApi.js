import { resolvePublicApiBaseUrl } from '~/utils/apiBase';

/**
 * Client HTTP cap a l’API Laravel (NUXT_PUBLIC_API_URL).
 */
export function useApi () {
  const config = useRuntimeConfig();

  async function fetchApi (path, options = {}) {
    const base = resolvePublicApiBaseUrl(config.public.apiUrl);
    const url = base + path;
    const merged = { timeout: 20000, ...options };
    return await $fetch(url, merged);
  }

  return {
    fetchApi,
  };
}
