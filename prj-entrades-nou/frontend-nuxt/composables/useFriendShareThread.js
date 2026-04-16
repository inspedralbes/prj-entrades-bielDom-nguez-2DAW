//================================ NAMESPACES
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';
import { useSocialThreadMutesStore } from '~/stores/socialThreadMutes';

//================================ FUNCIONS PÚBLIQUES

/**
 * Càrrega del fil de compartició amb un usuari i subscripció a esdeveniments socket.
 */
export function useFriendShareThread (props, emit) {
  const { getJson } = useAuthorizedApi();

  const loading = ref(true);
  const err = ref('');
  const messages = ref([]);
  const scrollBox = ref(null);

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

  return {
    loading,
    err,
    messages,
    scrollBox,
  };
}
