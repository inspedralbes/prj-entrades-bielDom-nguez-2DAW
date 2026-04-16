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
        <FriendChatMessageRow
          v-for="m in messages"
          :key="m.id"
          :message="m"
          :peer-username="peerUsername"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
import FriendChatMessageRow from '~/components/social/FriendChatMessageRow.vue';
import { useFriendShareThread } from '~/composables/useFriendShareThread';

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

const { loading, err, messages, scrollBox } = useFriendShareThread(props, emit);
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
</style>
