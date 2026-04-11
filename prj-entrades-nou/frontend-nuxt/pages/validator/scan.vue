<template>
  <main class="validator">
    <header class="validator__head">
      <h1 class="validator__title">Validació</h1>
      <NuxtLink to="/" class="validator__back">← Inici</NuxtLink>
    </header>

    <p v-if="roleBlock" class="validator__err">{{ roleBlock }}</p>

    <template v-else>
      <div id="validator-reader" class="validator__reader" />

      <p class="validator__muted">
        Escaneja el QR amb la càmera o enganxa el JWT de l’entrada.
      </p>

      <textarea
        v-model="manualToken"
        class="validator__manual"
        rows="4"
        placeholder="JWT del QR (mode manual / proves)"
        autocomplete="off"
      />

      <div class="validator__actions">
        <button
          type="button"
          class="validator__btn"
          :disabled="submitting"
          @click="submitManual"
        >
          Validar token
        </button>
      </div>

      <p v-if="successMsg" class="validator__ok">{{ successMsg }}</p>
      <p v-if="errorMsg" class="validator__err">{{ errorMsg }}</p>
    </template>
  </main>
</template>

<script setup>
import { Html5Qrcode } from 'html5-qrcode';
import { onMounted, onUnmounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useAuthStore } from '~/stores/auth';

definePageMeta({
  middleware: ['auth', 'validator'],
});

const auth = useAuthStore();
const { postJson } = useAuthorizedApi();

const roleBlock = ref('');
const manualToken = ref('');
const submitting = ref(false);
const successMsg = ref('');
const errorMsg = ref('');

let html5Qr = null;
let scanning = false;

function clearFeedback () {
  successMsg.value = '';
  errorMsg.value = '';
}

function setErrorFromException (e) {
  const status = e?.statusCode ?? e?.status ?? e?.response?.status;
  const msg = e?.data?.message ?? e?.data?.error;
  if (status === 403) {
    errorMsg.value = 'No tens permís de validador.';
    return;
  }
  if (status === 400 && msg) {
    errorMsg.value = msg;
    return;
  }
  if (status === 401) {
    errorMsg.value = 'Sessió caducada; torna a iniciar sessió.';
    return;
  }
  errorMsg.value =
    'No s’ha pogut contactar amb el servidor. Comprova la connexió i torna-ho a provar.';
}

async function runScan (raw) {
  const token = String(raw || '').trim();
  if (!token) {
    errorMsg.value = 'Cal un token d’entrada.';
    return;
  }
  submitting.value = true;
  clearFeedback();
  try {
    const res = await postJson('/api/validation/scan', { token });
    successMsg.value = `Entrada validada (${res.ticket_id || ''}).`;
  } catch (e) {
    setErrorFromException(e);
  } finally {
    submitting.value = false;
  }
}

async function submitManual () {
  await runScan(manualToken.value);
}

async function onQrDecoded (text) {
  if (scanning || submitting.value) {
    return;
  }
  scanning = true;
  try {
    await runScan(text);
    try {
      await html5Qr.pause(true);
    } catch {
      //
    }
  } finally {
    scanning = false;
  }
}

onMounted(async () => {
  const roles = auth.user?.roles || [];
  if (!roles.includes('validator')) {
    roleBlock.value = 'Aquesta pantalla és només per al rol validador.';
    return;
  }

  try {
    html5Qr = new Html5Qrcode('validator-reader');
    await html5Qr.start(
      { facingMode: 'environment' },
      { fps: 8, qrbox: { width: 240, height: 240 } },
      (decodedText) => {
        onQrDecoded(decodedText);
      },
      () => {},
    );
  } catch {
    errorMsg.value =
      'No s’ha pogut iniciar la càmera. Pots enganxar el JWT manualment.';
  }
});

onUnmounted(async () => {
  if (html5Qr) {
    try {
      await html5Qr.stop();
      html5Qr.clear();
    } catch {
      //
    }
    html5Qr = null;
  }
});
</script>

<style scoped>
.validator {
  min-height: 100vh;
  background: #070707;
  color: #f2f2f2;
  padding: 1.25rem;
  max-width: 28rem;
  margin: 0 auto;
}
.validator__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
.validator__title {
  margin: 0;
  font-size: 1.35rem;
  color: #00d4aa;
}
.validator__back {
  color: #888;
  text-decoration: none;
  font-size: 0.9rem;
}
.validator__reader {
  width: 100%;
  min-height: 220px;
  background: #111;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 0.75rem;
}
.validator__muted {
  color: #888;
  font-size: 0.9rem;
  margin: 0 0 0.75rem;
}
.validator__manual {
  width: 100%;
  box-sizing: border-box;
  border-radius: 8px;
  border: 1px solid #333;
  background: #0e0e0e;
  color: #eee;
  padding: 0.5rem 0.65rem;
  font-family: ui-monospace, monospace;
  font-size: 0.75rem;
  margin-bottom: 0.75rem;
}
.validator__actions {
  margin-bottom: 0.75rem;
}
.validator__btn {
  width: 100%;
  padding: 0.65rem 1rem;
  border: none;
  border-radius: 8px;
  background: #00d4aa;
  color: #031;
  font-weight: 600;
  cursor: pointer;
}
.validator__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.validator__ok {
  color: #7bed9f;
  margin: 0.5rem 0 0;
}
.validator__err {
  color: #ff6b6b;
  margin: 0.5rem 0 0;
}
</style>
