/**
 * Redueix soroll a la consola (Vue 3 / Nuxt): avís experimental de <Suspense>.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const prev = nuxtApp.vueApp.config.warnHandler;
  nuxtApp.vueApp.config.warnHandler = (msg, instance, trace) => {
    if (typeof msg === 'string' && msg.includes('<Suspense>')) {
      return;
    }
    if (typeof prev === 'function') {
      prev(msg, instance, trace);
    } else {
      console.warn(msg, trace);
    }
  };
});
