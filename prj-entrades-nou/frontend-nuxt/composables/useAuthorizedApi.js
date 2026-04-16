//================================ IMPORTS ============

import { computed } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { resolvePublicApiBaseUrl } from '~/utils/apiBase';

/**
 * Crides a l’API amb Bearer (T029) + text SVG del QR.
 */
export function useAuthorizedApi () {
  const config = useRuntimeConfig();
  const auth = useAuthStore();

  const base = computed(() => resolvePublicApiBaseUrl(config.public.apiUrl));

  function authHeaders (extra = {}) {
    const h = { ...extra };
    if (auth.token) {
      h.Authorization = `Bearer ${auth.token}`;
    }
    return h;
  }

  /**
   * @param {string} path
   * @param {{ noCache?: boolean }} [options] noCache: evita resposta antiga (seatmap / holds Redis)
   */
  async function getJson (path, options) {
    const fetchOpts = {
      headers: authHeaders({ Accept: 'application/json' }),
      timeout: 20000,
    };
    if (options !== undefined && options !== null && options.noCache === true) {
      fetchOpts.cache = 'no-store';
    }
    return await $fetch(`${base.value}${path}`, fetchOpts);
  }

  async function postJson (path, body) {
    return await $fetch(`${base.value}${path}`, {
      method: 'POST',
      headers: authHeaders({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
      body,
      timeout: 20000,
    });
  }

  async function deleteJson (path) {
    return await $fetch(`${base.value}${path}`, {
      method: 'DELETE',
      headers: authHeaders({ Accept: 'application/json' }),
      timeout: 20000,
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
      timeout: 20000,
    });
  }

  /**
   * GraphQL POST a `/api/graphql` (panell admin).
   *
   * @param {string} query
   * @param {Record<string, unknown>} [variables]
   */
  /**
   * URL del endpoint GraphQL: suporta base `http://host` o `http://host/api`.
   */
  function graphqlEndpointUrl () {
    const root = (base.value || '').replace(/\/$/, '');
    if (root.endsWith('/api')) {
      return `${root}/graphql`;
    }
    return `${root}/api/graphql`;
  }

  async function postGraphql (query, variables) {
    const body = { query };
    if (variables !== undefined && variables !== null) {
      body.variables = variables;
    }
    return await $fetch(graphqlEndpointUrl(), {
      method: 'POST',
      headers: authHeaders({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
      body,
      timeout: 30000,
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
    postGraphql,
    deleteJson,
    patchJson,
    getTicketQrSvg,
    authHeaders,
  };
}
