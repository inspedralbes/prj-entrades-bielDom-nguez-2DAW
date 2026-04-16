//================================ IMPORTS ============

import { defineStore } from 'pinia';
import { useCookie } from '#app';
import { resolvePublicSocketUrl } from '~/utils/apiBase';

/**
 * Store Pinia: client Socket.IO públic (rooms `event:{id}`).
 * Reconnexió: torna a fer `join` de totes les sales pendents amb bucles explícits (sense forEach/filter).
 */
export const useSocketStore = defineStore('socket', {
  state: () => ({
    socket: null,
    connected: false,
    eventRooms: [],
  }),

  actions: {
    connect () {
      if (typeof window === 'undefined' || this.socket) {
        return;
      }

      const config = useRuntimeConfig();
      const socketUrl = resolvePublicSocketUrl(config.public.socketUrl);

      try {
        this.socket = window.io(socketUrl, {
          transports: ['websocket', 'polling'],
          autoConnect: true,
        });

        this.socket.on('connect', () => {
          this.connected = true;
          console.log('[socket] connected');

          // A. Re-subscriure sales ja registrades abans de connectar.
          const rooms = this.eventRooms;
          let i = 0;
          for (; i < rooms.length; i += 1) {
            this.socket.emit('join', rooms[i]);
          }
        });

        this.socket.on('disconnect', () => {
          this.connected = false;
          console.log('[socket] disconnected');
        });

        this.socket.on('connect_error', (err) => {
          console.error('[socket] connection error:', err);
        });
      } catch (e) {
        console.error('[socket] init error:', e);
      }
    },

    disconnect () {
      if (this.socket) {
        this.socket.disconnect();
        this.socket = null;
        this.connected = false;
      }
    },

    joinEventRoom (eventId) {
      if (!this.socket) {
        this.connect();
      }
      const room = `event:${eventId}`;
      if (!this.eventRooms.includes(room)) {
        this.eventRooms.push(room);
      }
      if (this.socket && this.connected) {
        this.socket.emit('join', room);
      }
    },

    leaveEventRoom (eventId) {
      const room = `event:${eventId}`;
      const rooms = this.eventRooms;
      const remaining = [];
      let i = 0;
      for (; i < rooms.length; i += 1) {
        const r = rooms[i];
        if (r !== room) {
          remaining.push(r);
        }
      }
      this.eventRooms = remaining;
      if (this.socket && this.connected) {
        this.socket.emit('leave', room);
      }
    },

    on (event, callback) {
      if (this.socket) {
        this.socket.on(event, callback);
      }
    },

    off (event, callback) {
      if (this.socket) {
        this.socket.off(event, callback);
      }
    },
  },
});
