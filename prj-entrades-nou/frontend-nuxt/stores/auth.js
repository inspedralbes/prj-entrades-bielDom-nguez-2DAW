//================================ IMPORTS ============

import { defineStore } from 'pinia';
import { useCookie } from '#app';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null,
    user: null,
  }),
  actions: {
    init() {
      const tokenCookie = useCookie('auth_token')
      if (tokenCookie.value) {
        this.token = tokenCookie.value
      }
    },
    setSession ({ token, user }) {
      this.token = token;
      this.user = user;
      const tokenCookie = useCookie('auth_token')
      tokenCookie.value = token
    },
    clearSession () {
      this.token = null;
      this.user = null;
      const tokenCookie = useCookie('auth_token')
      tokenCookie.value = null
    },
  },
});