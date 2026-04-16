//================================ IMPORTS ============

import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

/**
 * Preferits: UI optimista (cor) i sincronització amb l’API en segon pla (mateix patró que seients: estat immediat + servidor).
 */
export const useSavedEventsStore = defineStore('savedEvents', () => {
  /** @type {import('vue').Ref<Record<string, true>>} */
  const savedEventIds = ref({});

  /**
   * @param {string|number} eventId
   */
  function isSaved (eventId) {
    if (eventId === null || eventId === undefined) {
      return false;
    }
    const k = String(eventId);
    if (savedEventIds.value[k] === true) {
      return true;
    }
    return false;
  }

  /**
   * @param {string|number} eventId
   * @param {boolean} value
   */
  function setSaved (eventId, value) {
    const k = String(eventId);
    const next = {};
    const cur = savedEventIds.value;
    const keys = Object.keys(cur);
    for (let i = 0; i < keys.length; i++) {
      const key = keys[i];
      next[key] = cur[key];
    }
    if (value) {
      next[k] = true;
    } else {
      delete next[k];
    }
    savedEventIds.value = next;
  }

  /**
   * @param {string|number} eventId
   */
  function toggle (eventId) {
    if (isSaved(eventId)) {
      setSaved(eventId, false);
    } else {
      setSaved(eventId, true);
    }
  }

  /**
   * Preferit: canvi visual immediat; POST/DELETE a l’API després; rollback si falla.
   *
   * @param {string|number} eventId
   */
  async function toggleFavorite (eventId) {
    const auth = useAuthStore();
    if (!auth.token) {
      return;
    }
    if (eventId === null || eventId === undefined) {
      return;
    }
    const wasSaved = isSaved(eventId);
    if (wasSaved) {
      setSaved(eventId, false);
    } else {
      setSaved(eventId, true);
    }

    const { postJson, deleteJson } = useAuthorizedApi();
    try {
      if (wasSaved) {
        await deleteJson(`/api/saved-events/${encodeURIComponent(eventId)}`);
      } else {
        await postJson('/api/saved-events', { event_id: Number(eventId) });
      }
    } catch (e) {
      if (wasSaved) {
        setSaved(eventId, true);
      } else {
        setSaved(eventId, false);
      }
      console.error(e);
      throw e;
    }
  }

  /**
   * Carrega des del servidor i reemplaça l’estat local (p. ex. llistat inicial).
   */
  async function fetchFromServer () {
    const auth = useAuthStore();
    if (!auth.token) {
      hydrateFromApiList([]);
      return;
    }
    const { getJson } = useAuthorizedApi();
    try {
      const data = await getJson('/api/saved-events');
      hydrateFromApiList(data.events || []);
    } catch (e) {
      console.error(e);
    }
  }

  /**
   * GET i merge amb marques locals (p. ex. detall d’esdeveniment després de login pendent).
   */
  async function mergeFromServer () {
    const auth = useAuthStore();
    if (!auth.token) {
      return;
    }
    const { getJson } = useAuthorizedApi();
    try {
      const data = await getJson('/api/saved-events');
      mergeFromApiList(data.events || []);
    } catch (e) {
      console.error(e);
    }
  }

  function clear () {
    savedEventIds.value = {};
  }

  /**
   * Reemplaça l’estat amb la llista retornada per l’API (font de veritat quan toqui).
   *
   * @param {Array<{ id?: string|number }>} events
   */
  function hydrateFromApiList (events) {
    const next = {};
    const n = events.length;
    for (let i = 0; i < n; i++) {
      const ev = events[i];
      const id = ev.id;
      if (id !== null && id !== undefined) {
        next[String(id)] = true;
      }
    }
    savedEventIds.value = next;
  }

  /**
   * Afegeix els IDs que ve del servidor sense esborrar marques locals (clic immediat sense API).
   *
   * @param {Array<{ id?: string|number }>} events
   */
  function mergeFromApiList (events) {
    const next = {};
    const cur = savedEventIds.value;
    const curKeys = Object.keys(cur);
    for (let i = 0; i < curKeys.length; i++) {
      const key = curKeys[i];
      next[key] = cur[key];
    }
    const n = events.length;
    for (let i = 0; i < n; i++) {
      const ev = events[i];
      const id = ev.id;
      if (id !== null && id !== undefined) {
        next[String(id)] = true;
      }
    }
    savedEventIds.value = next;
  }

  return {
    savedEventIds,
    isSaved,
    setSaved,
    toggle,
    toggleFavorite,
    fetchFromServer,
    mergeFromServer,
    clear,
    hydrateFromApiList,
    mergeFromApiList,
  };
});
