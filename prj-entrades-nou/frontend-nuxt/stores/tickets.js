import { defineStore } from 'pinia';

/**
 * Estat d’entrades reforçat per Socket privat (T033): validació a porta → marca visual ràpida.
 */
export const useTicketsStore = defineStore('tickets', {
  state: () => ({
    /** @type {Record<string, string>} ticketId → estat forçat (p. ex. utilitzada) */
    statusById: {},
  }),
  actions: {
    applyValidatedPayload (payload) {
      if (!payload?.ticketId) {
        return;
      }
      this.statusById[String(payload.ticketId)] = 'utilitzada';
    },
    effectiveStatus (ticketId, serverStatus) {
      const o = this.statusById[String(ticketId)];
      return o || serverStatus;
    },
  },
});
