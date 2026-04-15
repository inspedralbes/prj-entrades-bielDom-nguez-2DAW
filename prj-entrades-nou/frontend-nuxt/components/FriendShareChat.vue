<template>
  <section class="friend-chat" aria-label="Xat compartit amb aquest amic">
    <div ref="scrollBox" class="friend-chat__scroll">
      <p v-if="loading" class="friend-chat__state">Carregant…</p>
      <p v-else-if="err !== ''" class="friend-chat__err">{{ err }}</p>
      <div v-else-if="messages.length === 0" class="friend-chat__empty">
        <p class="friend-chat__empty-title">
          Cap contingut encara
        </p>
        <p class="friend-chat__empty-text">
          Aquí apareixeran els esdeveniments i les entrades que compartiu. Només lectura: no es pot escriure text.
        </p>
      </div>
      <div v-else class="friend-chat__list">
        <div
          v-for="m in messages"
          :key="m.id"
          class="friend-chat__row"
          :class="rowClass(m)"
        >
          <div class="friend-chat__lane">
            <span class="friend-chat__speaker">{{ speakerLabel(m) }}</span>
            <div class="friend-chat__bubble-wrap">
              <div class="friend-chat__bubble" :class="bubbleClass(m)">
                <span class="friend-chat__kind">{{ kindLabel(m) }}</span>
                <p class="friend-chat__time">{{ formatWhen(m.created_at) }}</p>
                <template v-if="m.type === 'event_shared'">
                  <p class="friend-chat__title">{{ eventTitle(m) }}</p>
                  <p v-if="eventVenueLine(m) !== ''" class="friend-chat__sub">{{ eventVenueLine(m) }}</p>
                  <NuxtLink
                    class="friend-chat__cta"
                    :to="eventDetailHref(m)"
                  >
                    Veure esdeveniment
                  </NuxtLink>
                </template>
                <template v-else-if="m.type === 'ticket_shared'">
                  <p class="friend-chat__title">{{ ticketTitle(m) }}</p>
                  <p v-if="ticketSub(m) !== ''" class="friend-chat__sub">{{ ticketSub(m) }}</p>
                  <NuxtLink
                    class="friend-chat__cta"
                    :to="ticketDetailHref(m)"
                  >
                    Veure entrada
                  </NuxtLink>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useSocialThreadMutesStore } from '~/stores/socialThreadMutes';

const props = defineProps({
  peerId: {
    type: String,
    required: true,
  },
  peerUsername: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['meta']);

const { getJson } = useAuthorizedApi();

const loading = ref(true);
const err = ref('');
const messages = ref([]);
const scrollBox = ref(null);

function onThreadSocket (ev) {
  const d = ev.detail;
  if (!d || d.peerUserId === undefined || d.peerUserId === null) {
    return;
  }
  if (String(d.peerUserId) !== String(props.peerId)) {
    return;
  }
  loadThread();
}

function rowClass (m) {
  const p = m.payload;
  if (!p || typeof p !== 'object') {
    return 'friend-chat__row--in';
  }
  if (p.direction === 'sent') {
    return 'friend-chat__row--out';
  }
  return 'friend-chat__row--in';
}

function bubbleClass (m) {
  const p = m.payload;
  if (!p || typeof p !== 'object') {
    return 'friend-chat__bubble--in';
  }
  if (p.direction === 'sent') {
    return 'friend-chat__bubble--out';
  }
  return 'friend-chat__bubble--in';
}

function kindLabel (m) {
  if (m.type === 'ticket_shared') {
    return 'Entrada';
  }
  if (m.type === 'event_shared') {
    return 'Esdeveniment';
  }
  return 'Compartit';
}

function speakerLabel (m) {
  const p = m.payload;
  if (p && p.direction === 'sent') {
    return 'Tu';
  }
  let u = props.peerUsername;
  if (p && typeof p.actor_username === 'string' && p.actor_username.trim() !== '') {
    u = p.actor_username.trim();
  }
  if (u === '') {
    return '@amic';
  }
  return '@' + u;
}

function eventTitle (m) {
  const p = m.payload;
  if (!p || typeof p.event_name !== 'string') {
    return 'Esdeveniment';
  }
  if (p.event_name.trim() === '') {
    return 'Esdeveniment';
  }
  return p.event_name;
}

function eventVenueLine (m) {
  const p = m.payload;
  if (!p) {
    return '';
  }
  const parts = [];
  if (typeof p.venue_name === 'string' && p.venue_name.trim() !== '') {
    parts.push(p.venue_name.trim());
  }
  if (typeof p.venue_city === 'string' && p.venue_city.trim() !== '') {
    parts.push(p.venue_city.trim());
  }
  if (parts.length === 0) {
    return '';
  }
  let out = parts[0];
  let i = 1;
  for (; i < parts.length; i += 1) {
    out = out + ' · ' + parts[i];
  }
  return out;
}

function eventDetailHref (m) {
  const p = m.payload;
  let id = '';
  if (p && p.event_id !== undefined && p.event_id !== null) {
    id = String(p.event_id);
  }
  return '/events/' + encodeURIComponent(id) + '?from=social';
}

function ticketTitle (m) {
  const p = m.payload;
  if (!p) {
    return 'Entrada';
  }
  if (typeof p.description === 'string' && p.description.trim() !== '') {
    return p.description;
  }
  if (typeof p.event_name === 'string' && p.event_name.trim() !== '') {
    return p.event_name;
  }
  return 'Entrada';
}

function ticketSub (m) {
  const p = m.payload;
  if (!p) {
    return '';
  }
  if (typeof p.venue_name === 'string' && p.venue_name.trim() !== '') {
    return p.venue_name;
  }
  return '';
}

function ticketDetailHref (m) {
  const p = m.payload;
  let id = '';
  if (p && p.ticket_id !== undefined && p.ticket_id !== null) {
    id = String(p.ticket_id);
  }
  return '/tickets/' + encodeURIComponent(id);
}

function formatWhen (iso) {
  if (!iso || typeof iso !== 'string') {
    return '';
  }
  let d = null;
  try {
    d = new Date(iso);
  } catch {
    return '';
  }
  if (Number.isNaN(d.getTime())) {
    return '';
  }
  const now = new Date();
  const pad = (n) => {
    if (n < 10) {
      return '0' + String(n);
    }
    return String(n);
  };
  const day = pad(d.getDate());
  const mo = pad(d.getMonth() + 1);
  const h = pad(d.getHours());
  const mi = pad(d.getMinutes());
  let out = day + '/' + mo;
  if (d.getFullYear() !== now.getFullYear()) {
    out = out + '/' + String(d.getFullYear());
  }
  out = out + ' · ' + h + ':' + mi;
  return out;
}

async function loadThread () {
  err.value = '';
  loading.value = true;
  try {
    const res = await getJson('/api/social/users/' + encodeURIComponent(props.peerId) + '/share-thread');
    const raw = res.messages;
    if (!Array.isArray(raw)) {
      messages.value = [];
    } else {
      messages.value = raw;
    }
    let muted = false;
    if (res.thread_notifications_muted === true) {
      muted = true;
    }
    emit('meta', { thread_notifications_muted: muted });
    const st = useSocialThreadMutesStore();
    st.setPeerMuted(String(props.peerId), muted);
    await nextTick();
    scrollToBottom();
  } catch (e) {
    messages.value = [];
    err.value = 'No s\'ha pogut carregar el fil.';
  } finally {
    loading.value = false;
  }
}

function scrollToBottom () {
  const el = scrollBox.value;
  if (!el) {
    return;
  }
  el.scrollTop = el.scrollHeight;
}

watch(
  () => props.peerId,
  () => {
    loadThread();
  },
);

onMounted(() => {
  loadThread();
  if (typeof window !== 'undefined') {
    window.addEventListener('app:social-share-thread', onThreadSocket);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('app:social-share-thread', onThreadSocket);
  }
});
</script>

<style scoped>
.friend-chat {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  width: 100%;
  margin: 0;
  padding: 0;
}

.friend-chat__scroll {
  flex: 1 1 auto;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: 0.5rem 0.35rem 1rem;
  -webkit-overflow-scrolling: touch;
  background:
    radial-gradient(ellipse 120% 80% at 50% 0%, rgba(247, 230, 40, 0.04) 0%, transparent 55%),
    repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(255, 255, 255, 0.02) 2px,
      rgba(255, 255, 255, 0.02) 3px
    ),
    #0b0b0b;
  border-radius: 0 0 14px 14px;
}

.friend-chat__state {
  margin: 0;
  padding: 1rem 0.75rem;
  font-size: 0.85rem;
  color: #9a9a9a;
  line-height: 1.45;
}

.friend-chat__err {
  margin: 0;
  padding: 1rem 0.75rem;
  font-size: 0.85rem;
  color: #ff6b6b;
}

.friend-chat__empty {
  padding: 1.5rem 1rem 2rem;
  text-align: center;
}

.friend-chat__empty-title {
  margin: 0 0 0.5rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.95rem;
  font-weight: 800;
  color: #e8e8e8;
  letter-spacing: 0.02em;
}

.friend-chat__empty-text {
  margin: 0;
  font-size: 0.82rem;
  color: #8a8a8a;
  line-height: 1.5;
  max-width: 22rem;
  margin-left: auto;
  margin-right: auto;
}

.friend-chat__list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.friend-chat__row {
  display: flex;
  width: 100%;
}

.friend-chat__row--in {
  justify-content: flex-start;
}

.friend-chat__row--out {
  justify-content: flex-end;
}

.friend-chat__lane {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  max-width: 92%;
}

.friend-chat__row--out .friend-chat__lane {
  align-items: flex-end;
}

.friend-chat__speaker {
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgba(247, 230, 40, 0.75);
  padding: 0 0.15rem;
}

.friend-chat__row--out .friend-chat__speaker {
  color: rgba(255, 255, 255, 0.45);
}

.friend-chat__bubble-wrap {
  position: relative;
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.35));
}

.friend-chat__bubble {
  position: relative;
  max-width: 100%;
  padding: 0.55rem 0.75rem 0.65rem;
  border-radius: 14px;
  box-sizing: border-box;
}

.friend-chat__bubble--in {
  background: linear-gradient(165deg, #1f1f1f 0%, #181818 100%);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-bottom-left-radius: 4px;
}

.friend-chat__bubble--in::after {
  content: '';
  position: absolute;
  left: -6px;
  bottom: 6px;
  width: 10px;
  height: 10px;
  background: #181818;
  border-left: 1px solid rgba(255, 255, 255, 0.08);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  transform: skewX(-12deg);
  border-radius: 0 0 0 3px;
}

.friend-chat__bubble--out {
  background: linear-gradient(155deg, rgba(247, 230, 40, 0.18) 0%, rgba(35, 32, 10, 0.95) 100%);
  border: 1px solid rgba(247, 230, 40, 0.42);
  border-bottom-right-radius: 4px;
}

.friend-chat__bubble--out::after {
  content: '';
  position: absolute;
  right: -6px;
  bottom: 6px;
  width: 10px;
  height: 10px;
  background: rgba(35, 32, 10, 0.95);
  border-right: 1px solid rgba(247, 230, 40, 0.35);
  border-bottom: 1px solid rgba(247, 230, 40, 0.35);
  transform: skewX(12deg);
  border-radius: 0 0 3px 0;
}

.friend-chat__kind {
  display: inline-block;
  margin-bottom: 0.35rem;
  padding: 0.12rem 0.45rem;
  font-size: 0.58rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #0a0a0a;
  background: rgba(247, 230, 40, 0.92);
  border-radius: 999px;
}

.friend-chat__bubble--out .friend-chat__kind {
  background: rgba(247, 230, 40, 0.85);
}

.friend-chat__time {
  margin: 0 0 0.35rem;
  font-size: 0.62rem;
  color: rgba(255, 255, 255, 0.35);
  letter-spacing: 0.04em;
}

.friend-chat__title {
  margin: 0 0 0.25rem;
  font-size: 0.88rem;
  font-weight: 700;
  color: #f2f2f2;
  line-height: 1.3;
  word-break: break-word;
}

.friend-chat__sub {
  margin: 0 0 0.45rem;
  font-size: 0.78rem;
  color: #a8a8a8;
  line-height: 1.35;
}

.friend-chat__cta {
  display: inline-block;
  margin-top: 0.15rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--accent);
  text-decoration: none;
}

.friend-chat__cta:hover {
  text-decoration: underline;
}
</style>
