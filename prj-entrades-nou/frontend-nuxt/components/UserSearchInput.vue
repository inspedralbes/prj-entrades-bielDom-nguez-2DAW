<template>
  <div class="user-search-field">
    <label v-if="srLabel" class="user-search-field__sr-only" :for="resolvedInputId">{{ srLabel }}</label>
    <div class="user-search-field__shell">
      <span class="user-search-field__icon material-symbols-rounded" aria-hidden="true">search</span>
      <input
        :id="resolvedInputId"
        :value="modelValue"
        type="search"
        class="user-search-field__input"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        @input="onInput"
        @focus="onFocus"
        @blur="onBlur"
      >
      <button
        v-if="showClearBtn"
        type="button"
        class="user-search-field__clear"
        :aria-label="clearLabel"
        @click.prevent="onClear"
      >
        <span class="user-search-field__clear-ico material-symbols-rounded" aria-hidden="true">close</span>
      </button>
    </div>
    <slot />
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  inputId: {
    type: String,
    default: '',
  },
  srLabel: {
    type: String,
    default: '',
  },
  autocomplete: {
    type: String,
    default: 'off',
  },
  showClear: {
    type: Boolean,
    default: true,
  },
  clearLabel: {
    type: String,
    default: 'Esborrar cerca',
  },
});

const emit = defineEmits(['update:modelValue', 'input', 'clear', 'focus', 'blur']);

const resolvedInputId = computed(() => {
  if (props.inputId && props.inputId !== '') {
    return props.inputId;
  }
  return 'user-search-field-input';
});

const showClearBtn = computed(() => {
  if (!props.showClear) {
    return false;
  }
  if (typeof props.modelValue !== 'string') {
    return false;
  }
  return props.modelValue.trim() !== '';
});

function onInput (event) {
  const value = event.target.value;
  emit('update:modelValue', value);
  emit('input', event);
}

function onFocus () {
  emit('focus');
}

function onBlur () {
  emit('blur');
}

function onClear () {
  emit('update:modelValue', '');
  emit('clear');
}
</script>

<style scoped>
.user-search-field {
  position: relative;
}

.user-search-field__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.user-search-field__shell {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  border: 1px solid #1f1f1f;
  background: #0b0b0b;
  border-radius: 9999px;
  min-height: 3.15rem;
  padding: 0 0.55rem 0 0.9rem;
  box-sizing: border-box;
}

.user-search-field__icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  width: 2rem;
  height: 2rem;
  color: #5f5f5f;
  font-size: 1.5rem;
  line-height: 1;
}

.user-search-field__input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: #fff;
  font-size: 1rem;
  outline: none;
}

/* Evita la X nativa del navegador (usem botó TR3 groc) */
.user-search-field__input::-webkit-search-decoration,
.user-search-field__input::-webkit-search-cancel-button {
  -webkit-appearance: none;
  appearance: none;
}

.user-search-field__input::placeholder {
  color: #4f4f4f;
}

.user-search-field__clear {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.35rem;
  height: 2.35rem;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 9999px;
  background: transparent;
  color: #f7e628;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.12s ease;
}

.user-search-field__clear:hover {
  background: rgba(247, 230, 40, 0.14);
}

.user-search-field__clear:active {
  transform: scale(0.94);
}

.user-search-field__clear-ico {
  font-size: 1.45rem;
  line-height: 1;
  color: inherit;
}
</style>
