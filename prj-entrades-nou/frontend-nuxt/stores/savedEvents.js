import { defineStore } from 'pinia';
import { ref } from 'vue';

/**
 * Estat local dels esdeveniments marcats com a guardats (UI immediata).
 * La persistència a l’API es pot afegir des de la pàgina o una acció dedicada.
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
    hydrateFromApiList,
    mergeFromApiList,
  };
});
