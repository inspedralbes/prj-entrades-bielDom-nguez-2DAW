//================================ IMPORTS ============

import { defineStore } from 'pinia';

const MAX_SEATS = 6;

/**
 * Estat pre-confirmació de seients (FR-010) + temporitzador i sessió anònima (T023/T024).
 */
export const useHoldStore = defineStore('hold', {
  state: () => ({
    /** @type {number[]} */
    selectedSeatIds: [],
    holdId: null,
    holdExpiresAt: null,
    eventId: null,
    anonymousSessionId: null,
    contentionMessage: null,
  }),

  getters: {
    selectionCount: (s) => s.selectedSeatIds.length,
    hasActiveHold: (s) => s.holdId != null && s.holdExpiresAt != null,
  },

  actions: {
    ensureAnonymousSession () {
      if (this.anonymousSessionId) {
        return;
      }
      if (import.meta.client) {
        let id = sessionStorage.getItem('anon_session_id');
        if (!id) {
          id = crypto.randomUUID();
          sessionStorage.setItem('anon_session_id', id);
        }
        this.anonymousSessionId = id;
      }
    },

    /**
     * @param {number} seatId
     * @param {{ availableIds?: Set<number> }} [opts]
     */
    toggleSeatId (seatId, opts = {}) {
      const id = Number(seatId);
      const idx = this.selectedSeatIds.indexOf(id);
      if (idx >= 0) {
        this.selectedSeatIds.splice(idx, 1);
        return;
      }
      if (this.selectedSeatIds.length >= MAX_SEATS) {
        return;
      }
      if (opts.availableIds && !opts.availableIds.has(id)) {
        return;
      }
      this.selectedSeatIds.push(id);
    },

    clearSelection () {
      this.selectedSeatIds = [];
    },

    /**
     * @param {{ holdId: string, expiresAt: string, eventId: string|number }} p
     */
    setHoldResult (p) {
      this.holdId = p.holdId;
      this.holdExpiresAt = p.expiresAt;
      this.eventId = p.eventId != null ? String(p.eventId) : null;
      this.contentionMessage = null;
    },

    /**
     * @param {{ expiresAt: string }} p
     */
    applyResync (p) {
      if (p.expiresAt) {
        this.holdExpiresAt = p.expiresAt;
      }
    },

    setExpiresAt (iso) {
      this.holdExpiresAt = iso;
    },

    setContention (message) {
      this.contentionMessage = message || 'Aquest seient acaba de ser seleccionat per un altre usuari';
    },

    clearContention () {
      this.contentionMessage = null;
    },

    clearHoldTimerOnly () {
      this.holdId = null;
      this.holdExpiresAt = null;
    },

    resetForEvent () {
      this.selectedSeatIds = [];
      this.holdId = null;
      this.holdExpiresAt = null;
      this.contentionMessage = null;
    },

    clear () {
      this.selectedSeatIds = [];
      this.holdId = null;
      this.holdExpiresAt = null;
      this.eventId = null;
      this.contentionMessage = null;
    },
  },
});
