/**
 * Client HTTP cap a l’API Laravel (NUXT_PUBLIC_API_URL).
 */
export function useApi () {
  const config = useRuntimeConfig();

  async function fetchApi (path, options = {}) {
    const base = config.public.apiUrl || '';
    const url = base.replace(/\/$/, '') + path;
    return await $fetch(url, options);
  }

  return {
    fetchApi,
  };
}
