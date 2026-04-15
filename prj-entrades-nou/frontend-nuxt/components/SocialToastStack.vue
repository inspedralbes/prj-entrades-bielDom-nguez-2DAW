<template>
  <div class="social-toast-stack" aria-live="polite">
    <div
      v-for="t in items"
      :key="t.id"
      class="social-toast"
      role="status"
    >
      <p class="social-toast__text">{{ t.body }}</p>
      <button
        type="button"
        class="social-toast__close"
        :aria-label="'Tancar'"
        @click="dismiss(t.id)"
      >
        <span class="material-symbols-outlined" aria-hidden="true">close</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { useSocialToastsStore } from '~/stores/socialToasts';

const store = useSocialToastsStore();
const { items } = storeToRefs(store);

function dismiss (id) {
  store.dismiss(id);
}
</script>

<style scoped>
.social-toast-stack {
  position: fixed;
  top: calc(0.65rem + env(safe-area-inset-top, 0px));
  right: calc(0.65rem + env(safe-area-inset-right, 0px));
  z-index: 55;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.45rem;
  max-width: min(20rem, calc(100vw - 1.3rem));
  pointer-events: none;
}

.social-toast-stack > * {
  pointer-events: auto;
}

.social-toast {
  display: flex;
  align-items: flex-start;
  gap: 0.45rem;
  padding: 0.55rem 0.45rem 0.55rem 0.75rem;
  background: rgba(26, 26, 26, 0.96);
  border: 1px solid rgba(74, 71, 51, 0.55);
  border-radius: 10px;
  box-shadow: 0 10px 32px rgba(0, 0, 0, 0.45);
  animation: social-toast-in 0.28s ease-out;
}

@keyframes social-toast-in {
  from {
    opacity: 0;
    transform: translateX(0.65rem);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.social-toast__text {
  margin: 0;
  padding-top: 0.1rem;
  font-size: 0.82rem;
  line-height: 1.35;
  color: rgba(245, 245, 245, 0.92);
  font-family: Epilogue, system-ui, sans-serif;
}

.social-toast__close {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.65rem;
  height: 1.65rem;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: rgba(255, 255, 255, 0.45);
  cursor: pointer;
}

.social-toast__close:hover {
  color: var(--accent);
  background: rgba(247, 230, 40, 0.1);
}

.social-toast__close .material-symbols-outlined {
  font-size: 1.1rem;
  line-height: 1;
}

@media (min-width: 900px) {
  .social-toast-stack {
    top: calc(4.25rem + env(safe-area-inset-top, 0px));
  }
}
</style>
