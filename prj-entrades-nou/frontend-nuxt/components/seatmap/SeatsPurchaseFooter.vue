<template>
  <footer class="ticket-footer" aria-label="Resum de compra">
    <div class="ticket-footer__left">
      <p v-if="unitPrice > 0" class="ticket-footer__muted">
        Preu per entrada: €{{ unitPrice.toFixed(2) }}
      </p>
      <p class="ticket-footer__count">
        {{ selectedCount }}
        <span v-if="selectedCount === 1">entrada</span>
        <span v-else>entrades</span>
        <span v-if="selectedCount > 0" class="ticket-footer__hint"> (màx. {{ maxSeats }} per persona)</span>
      </p>
      <p class="ticket-footer__total">
        Total: €{{ totalPrice.toFixed(2) }}
      </p>
    </div>
    <button
      type="button"
      class="ticket-footer__cta"
      :disabled="selectedCount === 0 || pendingSeatSyncCount > 0 || checkoutPending"
      @click="onCheckoutClick"
    >
      <span v-if="checkoutPending">Preparant compra…</span>
      <span v-else>Comprar · €{{ totalPrice.toFixed(2) }}</span>
    </button>
  </footer>
</template>

<script setup>
defineProps({
  unitPrice: {
    type: Number,
    required: true,
  },
  selectedCount: {
    type: Number,
    required: true,
  },
  maxSeats: {
    type: Number,
    required: true,
  },
  totalPrice: {
    type: Number,
    required: true,
  },
  checkoutPending: {
    type: Boolean,
    required: true,
  },
  pendingSeatSyncCount: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(['checkout']);

function onCheckoutClick () {
  emit('checkout');
}
</script>

<style scoped>
.ticket-footer {
  position: fixed;
  left: 0;
  right: 0;
  z-index: 45;
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 1rem;
  padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
  background: #0d0d0d;
  border-top: 1px solid #333;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.45);
  bottom: 0;
}

@media (max-width: 899px) {
  .ticket-footer {
    bottom: var(--footer-stack);
  }
}

.ticket-footer__left {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.15rem;
}
.ticket-footer__muted {
  margin: 0;
  font-size: 0.75rem;
  color: #888;
}
.ticket-footer__count {
  margin: 0;
  font-size: 0.9rem;
  color: #e5e5e5;
}
.ticket-footer__hint {
  font-size: 0.75rem;
  color: #666;
}
.ticket-footer__total {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
}
.ticket-footer__cta {
  align-self: center;
  flex-shrink: 0;
  padding: 0.85rem 1.25rem;
  background: var(--accent);
  color: var(--accent-on);
  border: none;
  border-radius: 9999px;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.9rem;
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
}
.ticket-footer__cta:disabled {
  background: #444;
  cursor: not-allowed;
}
.ticket-footer__cta:not(:disabled):hover {
  background: var(--accent-dim);
}
</style>
