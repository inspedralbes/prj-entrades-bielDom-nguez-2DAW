import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null,
    user: null,
  }),
  actions: {
    setSession ({ token, user }) {
      this.token = token;
      this.user = user;
    },
    clearSession () {
      this.token = null;
      this.user = null;
    },
  },
});
