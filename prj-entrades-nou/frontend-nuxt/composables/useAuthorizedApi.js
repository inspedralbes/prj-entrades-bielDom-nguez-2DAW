import { computed } from 'vue';
import { useAuthStore } from '~/stores/auth';

/**
 * Crides a l’API amb Bearer (T029) + text SVG del QR.
 */
export function useAuthorizedApi () {
  const config = useRuntimeConfig();
  const auth = useAuthStore();

  const base = computed(() => (config.public.apiUrl || '').replace(/\/$/, ''));

  function authHeaders (extra = {}) {
    const h = { ...extra };
    if (auth.token) {
      h.Authorization = `Bearer ${auth.token}`;
    }
    return h;
  }

  async function getJson (path) {
    return await $fetch(`${base.value}${path}`, {
      headers: authHeaders({ Accept: 'application/json' }),
    });
  }

  async function postJson (path, body) {
    return await $fetch(`${base.value}${path}`, {
      method: 'POST',
      headers: authHeaders({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
      body,
    });
  }

  async function deleteJson (path) {
    return await $fetch(`${base.value}${path}`, {
      method: 'DELETE',
      headers: authHeaders({ Accept: 'application/json' }),
    });
  }

  async function patchJson (path, body) {
    return await $fetch(`${base.value}${path}`, {
      method: 'PATCH',
      headers: authHeaders({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
      body,
    });
  }

  /**
   * @returns {Promise<string>} Marcatge SVG
   */
  async function getTicketQrSvg (ticketId) {
    const res = await fetch(
      `${base.value}/api/tickets/${encodeURIComponent(ticketId)}/qr`,
      {
        headers: authHeaders({ Accept: 'image/svg+xml' }),
      },
    );
    if (!res.ok) {
      const err = new Error(`QR ${res.status}`);
      err.status = res.status;
      throw err;
    }
    return await res.text();
  }

  return {
    base,
    getJson,
    postJson,
    deleteJson,
    patchJson,
    getTicketQrSvg,
    authHeaders,
  };
}
