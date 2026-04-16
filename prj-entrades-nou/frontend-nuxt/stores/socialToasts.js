//================================ IMPORTS ============

import { defineStore } from 'pinia';

/**
 * Toasts socials (dalt a la dreta), desencadenats per Socket.IO + payload des de Laravel.
 */
export const useSocialToastsStore = defineStore('socialToasts', {
  state: () => ({
    items: [],
  }),
  actions: {
    push (body, kind) {
      if (typeof body !== 'string' || body.trim() === '') {
        return;
      }
      const id = String(Date.now()) + '-' + String(Math.random()).slice(2, 8);
      let k = 'info';
      if (kind === 'social') {
        k = 'social';
      }
      this.items.push({ id, body: body.trim(), kind: k });
      const self = this;
      const delayMs = 5600;
      if (typeof window !== 'undefined') {
        window.setTimeout(() => {
          self.dismiss(id);
        }, delayMs);
      }
      const max = 5;
      if (this.items.length > max) {
        const next = [];
        const start = this.items.length - max;
        let i = start;
        for (; i < this.items.length; i += 1) {
          next.push(this.items[i]);
        }
        this.items = next;
      }
    },
    dismiss (id) {
      const next = [];
      let i = 0;
      for (; i < this.items.length; i += 1) {
        if (this.items[i].id !== id) {
          next.push(this.items[i]);
        }
      }
      this.items = next;
    },
  },
});
