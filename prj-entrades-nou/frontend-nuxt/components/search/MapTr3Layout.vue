<template>
  <div class="map-tr3-page">
    <div class="map-tr3">
      <div class="map-tr3__float map-tr3__float--top">
        <NuxtLink :to="listSearchHref" class="map-tr3__back" aria-label="Tornar al llistat">
          <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
        </NuxtLink>
      </div>

      <div class="map-tr3__map-block">
        <slot name="status" />
        <div class="map-tr3__map-stack">
          <div class="map-tr3__filter-layer">
            <slot name="map" />
          </div>
          <div class="map-tr3__vignette" aria-hidden="true" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  listSearchHref: {
    type: String,
    required: true,
  },
});
</script>

<style scoped>
.map-tr3-page {
  box-sizing: border-box;
  width: 100%;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.map-tr3 {
  position: relative;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  background: #0e0e0e;
  overflow: hidden;
}

.map-tr3__float--top {
  position: absolute;
  top: 0.75rem;
  left: 0;
  z-index: 40;
  padding: 0 0.75rem;
  pointer-events: none;
}

.map-tr3__back {
  pointer-events: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: rgba(42, 42, 42, 0.9);
  border: 1px solid rgba(74, 71, 51, 0.35);
  color: #f7e628;
  text-decoration: none;
  transition: opacity 0.2s ease;
}

.map-tr3__back:hover {
  opacity: 0.88;
}

.map-tr3__map-block {
  position: relative;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.map-tr3__map-stack {
  position: relative;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.map-tr3__filter-layer {
  width: 100%;
  height: 100%;
  filter: grayscale(0.38) contrast(1.14) brightness(0.86);
}

.map-tr3__vignette {
  position: absolute;
  inset: 0;
  z-index: 3;
  pointer-events: none;
  background: linear-gradient(
    to bottom,
    rgba(19, 19, 19, 0.88) 0%,
    rgba(19, 19, 19, 0) 18%,
    rgba(19, 19, 19, 0) 78%,
    rgba(19, 19, 19, 0.92) 100%
  );
}
</style>
