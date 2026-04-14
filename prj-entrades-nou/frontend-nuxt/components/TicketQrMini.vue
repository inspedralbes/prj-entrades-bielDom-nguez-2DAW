<template>
  <div class="ticket-qr-mini" aria-hidden="true">
    <div v-if="loading" class="ticket-qr-mini__ph">…</div>
    <div v-else-if="error" class="ticket-qr-mini__ph">▦</div>
    <div v-else class="ticket-qr-mini__svg" v-html="svg" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

const props = defineProps({
  ticketId: {
    type: String,
    required: true,
  },
});

const { getTicketQrSvg } = useAuthorizedApi();
const loading = ref(true);
const error = ref(false);
const svg = ref('');

onMounted(async () => {
  loading.value = true;
  error.value = false;
  try {
    svg.value = await getTicketQrSvg(props.ticketId);
  } catch (e) {
    error.value = true;
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.ticket-qr-mini {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ticket-qr-mini__ph {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #111;
  color: var(--accent);
  font-size: 1.25rem;
}
.ticket-qr-mini__svg :deep(svg) {
  width: 52px;
  height: 52px;
  display: block;
}
</style>
