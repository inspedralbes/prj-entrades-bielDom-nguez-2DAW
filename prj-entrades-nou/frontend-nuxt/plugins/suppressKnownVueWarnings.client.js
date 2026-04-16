/**
 * Redueix soroll a la consola (Vue 3 / Nuxt): avís experimental de <Suspense>.
 * Algunes versions encaminen l’avís per `console.warn` en lloc de `warnHandler`.
 */
export default defineNuxtPlugin({
  name: 'suppress-known-vue-warnings',
  enforce: 'pre',
  setup (nuxtApp) {
    const prevWarn = console.warn;
    console.warn = function (...args) {
      const first = args[0];
      if (typeof first === 'string') {
        if (first.indexOf('<Suspense>') !== -1 && first.indexOf('experimental') !== -1) {
          return;
        }
      }
      prevWarn.apply(console, args);
    };

    const prevHandler = nuxtApp.vueApp.config.warnHandler;
    nuxtApp.vueApp.config.warnHandler = (msg, instance, trace) => {
      if (typeof msg === 'string' && msg.indexOf('<Suspense>') !== -1) {
        return;
      }
      if (typeof prevHandler === 'function') {
        prevHandler(msg, instance, trace);
      } else {
        prevWarn(msg, trace);
      }
    };
  },
});
