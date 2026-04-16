//================================ NAMESPACES
// Flux cinema-seats + holds: consumidor principal `pages/events/[eventId]/seats.vue`.
// Les crides HTTP comparteixen patró amb `usePrivateSeatmapSocket`; no cal capa extra si només hi ha aquest flux.
import { computed, ref } from 'vue';
import { useAuthStore } from '~/stores/auth';
import { useInteractiveSeatmapStore } from '~/stores/interactiveSeatmap';

//================================ FUNCIONS PÚBLIQUES

/**
 * Flux de reserva (hold API + socket) i checkout cinema-seats per la pàgina de seients.
 */
export function useCinemaSeatCheckoutFlow ({
  eventId,
  router,
  postJson,
  emitSeatHoldIntent,
  emitSeatHoldRollback,
  maxSeats,
}) {
  const auth = useAuthStore();
  const seatmapStore = useInteractiveSeatmapStore();

  const holdMessage = ref('');
  const checkoutPending = ref(false);
  const pendingSeatSync = ref({});

  const pendingSeatSyncCount = computed(() => {
    const o = pendingSeatSync.value;
    const keys = Object.keys(o);
    let n = 0;
    for (let i = 0; i < keys.length; i++) {
      if (o[keys[i]] === true) {
        n += 1;
      }
    }
    return n;
  });

  function setPendingSeat (seatId, v) {
    const next = {};
    const keys = Object.keys(pendingSeatSync.value);
    for (let i = 0; i < keys.length; i++) {
      next[keys[i]] = pendingSeatSync.value[keys[i]];
    }
    if (v) {
      next[seatId] = true;
    } else {
      delete next[seatId];
    }
    pendingSeatSync.value = next;
  }

  async function onSeatClick ({ seatId }) {
    holdMessage.value = '';
    if (pendingSeatSync.value[seatId]) {
      return;
    }
    if (seatmapStore.soldBySeatId[seatId]) {
      return;
    }

    const uid = auth.user && auth.user.id !== undefined ? String(auth.user.id) : '';

    if (seatmapStore.selectedSeatIds.indexOf(seatId) >= 0) {
      setPendingSeat(seatId, true);
      seatmapStore.optimisticRelease(seatId);
      try {
        await postJson(`/api/events/${eventId.value}/seat-holds/release`, { seat_id: seatId });
      } catch (err) {
        seatmapStore.restoreAfterFailedRelease(seatId, uid);
        let msg = 'No s\'ha pogut alliberar el seient.';
        if (err && err.data && err.data.message) {
          msg = err.data.message;
        }
        holdMessage.value = msg;
      } finally {
        setPendingSeat(seatId, false);
      }
      return;
    }

    if (seatmapStore.selectedSeatIds.length >= maxSeats) {
      holdMessage.value = `Màxim ${maxSeats} entrades per persona.`;
      return;
    }

    const held = seatmapStore.heldBySeatId[seatId];
    if (held !== undefined && held !== '' && held !== uid) {
      holdMessage.value = 'Aquest seient està reservat per un altre usuari.';
      return;
    }

    setPendingSeat(seatId, true);
    seatmapStore.optimisticReserve(seatId, uid);
    emitSeatHoldIntent(seatId);
    try {
      await postJson(`/api/events/${eventId.value}/seat-holds`, { seat_id: seatId });
    } catch (err) {
      emitSeatHoldRollback(seatId);
      seatmapStore.revertOptimisticReserve(seatId);
      let msg = 'No s\'ha pogut reservar el seient.';
      if (err && err.data && err.data.message) {
        msg = err.data.message;
      }
      holdMessage.value = msg;
    } finally {
      setPendingSeat(seatId, false);
    }
  }

  async function goToCheckout () {
    if (seatmapStore.selectedSeatIds.length === 0) {
      return;
    }
    if (checkoutPending.value) {
      return;
    }
    holdMessage.value = '';
    checkoutPending.value = true;
    const keys = [];
    const sel = seatmapStore.selectedSeatIds;
    for (let i = 0; i < sel.length; i++) {
      keys.push(sel[i]);
    }
    try {
      const evNum = parseInt(String(eventId.value), 10);
      if (Number.isNaN(evNum)) {
        holdMessage.value = 'Esdeveniment invàlid.';
        return;
      }
      const created = await postJson('/api/orders/cinema-seats', {
        event_id: evNum,
        seat_keys: keys,
      });
      await postJson(`/api/orders/${created.order_id}/confirm-payment`, {});
      await router.push({
        path: '/tickets',
        query: {
          eventId: String(eventId.value),
          orderId: String(created.order_id),
          new: '1',
        },
      });
    } catch (err) {
      let msg = 'No s\'ha pogut iniciar la compra.';
      if (err && err.data && err.data.message) {
        msg = err.data.message;
      }
      holdMessage.value = msg;
    } finally {
      checkoutPending.value = false;
    }
  }

  return {
    holdMessage,
    checkoutPending,
    pendingSeatSyncCount,
    onSeatClick,
    goToCheckout,
  };
}
