import { defineStore } from 'pinia';

/**
 * Estat dels seients del mapa interactiu (sold / holds Redis / selecció).
 * Les mutacions a held/sold fan còpia d’objecte per assegurar re-render (D3 + Vue).
 */
export const useInteractiveSeatmapStore = defineStore('interactiveSeatmap', {
  state: () => ({
    /** @type {Record<string, boolean>} */
    soldBySeatId: {},
    /** @type {Record<string, string>} seatId -> userId */
    heldBySeatId: {},
    /** @type {string[]} */
    selectedSeatIds: [],
    currentUserId: null,
    /** Només aplica esdeveniments socket del mapa actiu */
    activeEventId: null,
  }),

  actions: {
    _cloneHeld () {
      const out = {};
      const keys = Object.keys(this.heldBySeatId);
      for (let i = 0; i < keys.length; i++) {
        const k = keys[i];
        out[k] = this.heldBySeatId[k];
      }
      return out;
    },

    _cloneSold () {
      const out = {};
      const keys = Object.keys(this.soldBySeatId);
      for (let i = 0; i < keys.length; i++) {
        const k = keys[i];
        out[k] = this.soldBySeatId[k];
      }
      return out;
    },

    _setHeld (seatId, userIdStr) {
      const sid = String(seatId);
      const h = this._cloneHeld();
      h[sid] = String(userIdStr);
      this.heldBySeatId = h;
    },

    _deleteHeld (seatId) {
      const sid = String(seatId);
      const h = this._cloneHeld();
      delete h[sid];
      this.heldBySeatId = h;
    },

    _setSold (seatId) {
      const sid = String(seatId);
      const s = this._cloneSold();
      s[sid] = true;
      this.soldBySeatId = s;
    },

    bootstrapFromApi (payload, eventIdStr) {
      const nextEid = eventIdStr !== undefined && eventIdStr !== null ? String(eventIdStr) : null;
      const prevEid = this.activeEventId;
      this.activeEventId = nextEid;
      // Només buidem la selecció si canvia l’esdeveniment; una segona càrrega del mateix mapa no ha d’esborrar la selecció local.
      const prevStr = prevEid !== null && prevEid !== undefined ? String(prevEid) : '';
      const nextStr = nextEid !== null && nextEid !== undefined ? String(nextEid) : '';
      if (prevStr !== nextStr) {
        this.selectedSeatIds = [];
      }
      const sold = payload.seat_layout || {};
      const soldOut = {};
      const soldKeys = Object.keys(sold);
      for (let i = 0; i < soldKeys.length; i++) {
        const k = soldKeys[i];
        soldOut[k] = true;
      }
      this.soldBySeatId = soldOut;

      const holds = payload.redis_holds || {};
      const heldOut = {};
      const hk = Object.keys(holds);
      for (let j = 0; j < hk.length; j++) {
        const rawKey = hk[j];
        const id = String(rawKey);
        heldOut[id] = String(holds[rawKey]);
      }
      this.heldBySeatId = heldOut;
    },

    setCurrentUserId (uid) {
      this.currentUserId = uid !== null && uid !== undefined ? String(uid) : null;
    },

    /**
     * Alinear amb la ruta abans que arribi bootstrapFromApi.
     * Important: si canvia l’esdeveniment respecte al mapa actiu, buidem la selecció (cada event té selecció pròpia).
     */
    syncRouteEventId (eventIdStr) {
      if (eventIdStr === undefined || eventIdStr === null || eventIdStr === '') {
        return;
      }
      const next = String(eventIdStr);
      const prev = this.activeEventId !== null && this.activeEventId !== undefined ? String(this.activeEventId) : '';
      if (prev !== '' && prev !== next) {
        this.selectedSeatIds = [];
      }
      this.activeEventId = next;
    },

    /**
     * Reserva immediata al mapa (abans que Redis respongui): sensació temps real.
     */
    optimisticReserve (seatId, userIdStr) {
      this._setHeld(seatId, userIdStr);
      this.addSelectedSeat(seatId);
    },

    /**
     * Reverteix optimisticReserve si l’API falla.
     */
    revertOptimisticReserve (seatId) {
      this._deleteHeld(seatId);
      this.removeSelectedSeat(seatId);
    },

    /**
     * Alliberament immediat visual (abans de POST release).
     */
    optimisticRelease (seatId) {
      this._deleteHeld(seatId);
      this.removeSelectedSeat(seatId);
    },

    /**
     * Si release API falla, torna l’estat local com abans.
     */
    restoreAfterFailedRelease (seatId, userIdStr) {
      this._setHeld(seatId, userIdStr);
      this.addSelectedSeat(seatId);
    },

    applySocketUpdate (payload) {
      const eid = payload.eventId !== undefined ? String(payload.eventId) : '';
      const activeStr = this.activeEventId !== null && this.activeEventId !== undefined ? String(this.activeEventId) : '';
      if (activeStr !== '' && eid !== '' && eid !== activeStr) {
        return;
      }
      const seatId = payload.seatId !== undefined && payload.seatId !== null ? String(payload.seatId) : '';
      const status = payload.status;
      if (seatId === '' || !status) {
        return;
      }
      if (status === 'sold') {
        this._setSold(seatId);
        this._deleteHeld(seatId);
        this.removeSelectedSeat(seatId);
        return;
      }
      if (status === 'held') {
        const uid = payload.userId !== undefined && payload.userId !== null ? String(payload.userId) : '';
        if (uid !== '') {
          const my = this.currentUserId !== null && this.currentUserId !== undefined ? String(this.currentUserId) : '';
          if (my !== '' && uid !== my && this.selectedSeatIds.indexOf(seatId) >= 0) {
            this.removeSelectedSeat(seatId);
          }
          this._setHeld(seatId, uid);
        }
        return;
      }
      if (status === 'available') {
        this._deleteHeld(seatId);
      }
    },

    addSelectedSeat (seatId) {
      const sid = String(seatId);
      if (this.selectedSeatIds.indexOf(sid) >= 0) {
        return;
      }
      if (this.selectedSeatIds.length >= 6) {
        return;
      }
      this.selectedSeatIds.push(sid);
    },

    removeSelectedSeat (seatId) {
      const sid = String(seatId);
      const idx = this.selectedSeatIds.indexOf(sid);
      if (idx >= 0) {
        this.selectedSeatIds.splice(idx, 1);
      }
    },

    clearSelection () {
      this.selectedSeatIds = [];
    },
  },
});
