<template>
  <article class="ec3">
    <div class="ec3__inner">
      <div class="ec3__media">
        <div
          class="ec3__img-wrap"
          :class="{ 'ec3__img-wrap--empty': !imgSrc }"
        >
          <img
            v-if="imgSrc"
            class="ec3__img"
            :src="imgSrc"
            :alt="imgAlt"
            loading="lazy"
            decoding="async"
            width="384"
            height="384"
          >
        </div>
        <button
          v-if="showHeart"
          type="button"
          class="ec3__fav"
          :aria-pressed="heartFilled"
          :aria-label="favAriaLabel"
          @click.prevent="onFavClick"
        >
          <span
            class="material-symbols-rounded ec3__fav-ico"
            :class="{ 'ec3__fav-ico--fill': heartFilled }"
            aria-hidden="true"
          >favorite</span>
        </button>
      </div>

      <div class="ec3__body">
        <div class="ec3__top">
          <div class="ec3__row-kicker">
            <span class="ec3__kicker">{{ kickerText }}</span>
            <span class="ec3__price">{{ priceText }}</span>
          </div>
          <h3 class="ec3__title">
            {{ event.name }}
          </h3>
          <div class="ec3__when">
            <span class="material-symbols-rounded ec3__when-ico" aria-hidden="true">calendar_today</span>
            <span class="ec3__when-txt">{{ whenText }}</span>
          </div>
        </div>
        <div class="ec3__actions">
          <NuxtLink
            :to="detailHref"
            class="ec3__btn ec3__btn--ghost"
          >
            Detalls
          </NuxtLink>
          <NuxtLink
            :to="seatsHref"
            class="ec3__btn ec3__btn--primary"
          >
            Comprar
          </NuxtLink>
        </div>
      </div>
    </div>
  </article>
</template>

<script setup>
import '~/assets/css/event-card-tr3.css';
import { useEventCardTr3 } from '~/composables/useEventCardTr3';

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  showHeart: {
    type: Boolean,
    default: true,
  },
  heartFilled: {
    type: Boolean,
    default: false,
  },
  linkFrom: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['favorite-click']);

const {
  imgSrc,
  imgAlt,
  detailHref,
  seatsHref,
  kickerText,
  priceText,
  whenText,
  favAriaLabel,
} = useEventCardTr3(props);

function onFavClick () {
  emit('favorite-click');
}
</script>
