import { io } from 'socket.io-client';
import { useAuthStore } from '~/stores/auth';

/**
 * Dades del panell admin: resum REST + esdeveniments Socket.IO al namespace públic (room admin:dashboard).
 */
export function useAdminDashboard () {
  const config = useRuntimeConfig();
  const auth = useAuthStore();

  let socket;

  function connectSocket (onMetrics) {
    if (!import.meta.client || !auth.token) {
      return () => {};
    }
    const url = (config.public.socketUrl || '').replace(/\/$/, '');
    if (!url) {
      return () => {};
    }
    const authPayload = {};
    if (auth.token) {
      authPayload.token = `Bearer ${auth.token}`;
    }

    socket = io(url, {
      transports: ['websocket', 'polling'],
      auth: authPayload,
    });
    socket.on('connect', () => {
      socket.emit('join:admin-dashboard');
    });
    socket.on('admin:metrics', (payload) => {
      onMetrics(payload);
    });

    return () => {
      if (socket) {
        socket.disconnect();
        socket = null;
      }
    };
  }

  return { connectSocket };
}
