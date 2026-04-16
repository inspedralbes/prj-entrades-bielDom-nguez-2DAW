//================================ IMPORTS ============

import { defineStore } from 'pinia';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

/**
 * Silencis de toasts de fil compartit (event/entrada) per peer; el xat segueix en temps real.
 */
export const useSocialThreadMutesStore = defineStore('socialThreadMutes', {
  state: () => ({
    /** clau: peer_user_id com a string */
    mutedByPeer: {},
    loaded: false,
  }),
  actions: {
    async fetchAll () {
      const { getJson } = useAuthorizedApi();
      try {
        const res = await getJson('/api/social/thread-notification-mutes');
        const ids = res.muted_peer_ids;
        const next = {};
        if (Array.isArray(ids)) {
          let i = 0;
          for (; i < ids.length; i += 1) {
            next[String(ids[i])] = true;
          }
        }
        this.mutedByPeer = next;
        this.loaded = true;
      } catch {
        this.loaded = true;
      }
    },
    setPeerMuted (peerId, muted) {
      const k = String(peerId);
      if (muted) {
        this.mutedByPeer[k] = true;
        return;
      }
      const next = {};
      const keys = Object.keys(this.mutedByPeer);
      let i = 0;
      for (; i < keys.length; i += 1) {
        if (keys[i] !== k) {
          next[keys[i]] = this.mutedByPeer[keys[i]];
        }
      }
      this.mutedByPeer = next;
    },
    isMuted (peerId) {
      return !!this.mutedByPeer[String(peerId)];
    },
  },
});
