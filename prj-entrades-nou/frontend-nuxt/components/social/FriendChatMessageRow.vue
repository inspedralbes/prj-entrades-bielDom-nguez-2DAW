<template>
  <div class="friend-chat__row" :class="friendChatRowClass(message)">
    <div class="friend-chat__lane">
      <span class="friend-chat__speaker">{{ friendChatSpeakerLabel(message, peerUsername) }}</span>
      <div class="friend-chat__bubble-wrap">
        <div class="friend-chat__bubble" :class="friendChatBubbleClass(message)">
          <span class="friend-chat__kind">{{ friendChatKindLabel(message) }}</span>
          <p class="friend-chat__time">{{ friendChatFormatWhen(message.created_at) }}</p>
          <template v-if="message.type === 'event_shared'">
            <p class="friend-chat__title">{{ friendChatEventTitle(message) }}</p>
            <p v-if="friendChatEventVenueLine(message) !== ''" class="friend-chat__sub">
              {{ friendChatEventVenueLine(message) }}
            </p>
            <NuxtLink
              class="friend-chat__cta"
              :to="friendChatEventDetailHref(message)"
            >
              Veure esdeveniment
            </NuxtLink>
          </template>
          <template v-else-if="message.type === 'ticket_shared'">
            <p class="friend-chat__title">{{ friendChatTicketTitle(message) }}</p>
            <p v-if="friendChatTicketSub(message) !== ''" class="friend-chat__sub">
              {{ friendChatTicketSub(message) }}
            </p>
            <NuxtLink
              class="friend-chat__cta"
              :to="friendChatTicketDetailHref(message)"
            >
              Veure entrada
            </NuxtLink>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  friendChatBubbleClass,
  friendChatEventDetailHref,
  friendChatEventTitle,
  friendChatEventVenueLine,
  friendChatFormatWhen,
  friendChatKindLabel,
  friendChatRowClass,
  friendChatSpeakerLabel,
  friendChatTicketDetailHref,
  friendChatTicketSub,
  friendChatTicketTitle,
} from '~/composables/friendShareThreadHelpers';

defineProps({
  message: {
    type: Object,
    required: true,
  },
  peerUsername: {
    type: String,
    default: '',
  },
});
</script>

<style scoped>
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
