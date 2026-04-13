import { defineStore } from 'pinia';
import { useCookie } from '#app';

export const useSocketStore = defineStore('socket', {
  state: () => ({
    socket: null,
    connected: false,
    eventRooms: [],
  }),

  actions: {
    connect() {
      if (typeof window === 'undefined' || this.socket) return;
      
      const config = useRuntimeConfig();
      const socketUrl = config.public.socketUrl || 'http://localhost:3001';
      
      try {
        this.socket = window.io(socketUrl, {
          transports: ['websocket', 'polling'],
          autoConnect: true,
        });

        this.socket.on('connect', () => {
          this.connected = true;
          console.log('[socket] connected');
          
          this.eventRooms.forEach(room => {
            this.socket.emit('join', room);
          });
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

    disconnect() {
      if (this.socket) {
        this.socket.disconnect();
        this.socket = null;
        this.connected = false;
      }
    },

    joinEventRoom(eventId) {
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

    leaveEventRoom(eventId) {
      const room = `event:${eventId}`;
      this.eventRooms = this.eventRooms.filter(r => r !== room);
      if (this.socket && this.connected) {
        this.socket.emit('leave', room);
      }
    },

    on(event, callback) {
      if (this.socket) {
        this.socket.on(event, callback);
      }
    },

    off(event, callback) {
      if (this.socket) {
        this.socket.off(event, callback);
      }
    },
  },
});