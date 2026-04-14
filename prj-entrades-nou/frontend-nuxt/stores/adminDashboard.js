import { defineStore } from 'pinia';

/**
 * Mètriques Socket.IO del panell admin (`admin:metrics`) i reutilització opcional entre vistes.
 */
export const useAdminDashboardStore = defineStore('adminDashboard', {
  state: () => ({
    /** @type {unknown} */
    liveMetrics: null,
  }),
  actions: {
    setLiveMetrics (payload) {
      this.liveMetrics = payload;
    },
  },
});
