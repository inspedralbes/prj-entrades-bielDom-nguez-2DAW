//================================ IMPORTS ============
// (Sense imports ESM inicials; intercepta console.warn.)

/**
 * Redueix soroll a la consola (Vue 3 / Nuxt): avís experimental de <Suspense>,
 * deprecació de google.maps.Marker (encara vàlid; migració opcional).
 */
function concatArgStrings (args) {
  let combined = '';
  let i = 0;
  for (; i < args.length; i = i + 1) {
    const a = args[i];
    if (typeof a === 'string') {
      combined = combined + a;
    } else if (a !== null && a !== undefined) {
      combined = combined + String(a);
    }
    combined = combined + ' ';
  }
  return combined;
}

function shouldSuppressConsoleWarn (args) {
  const t = concatArgStrings(args);
  if (t.indexOf('<Suspense>') !== -1 && t.indexOf('experimental') !== -1) {
    return true;
  }
  if (t.indexOf('google.maps.Marker is deprecated') !== -1) {
    return true;
  }
  if (t.indexOf('AdvancedMarkerElement') !== -1 && t.indexOf('deprecated') !== -1) {
    return true;
  }
  return false;
}

function shouldSuppressVueWarnMsg (msg) {
  if (typeof msg !== 'string') {
    return false;
  }
  if (msg.indexOf('<Suspense>') !== -1) {
    return true;
  }
  if (msg.indexOf('google.maps.Marker is deprecated') !== -1) {
    return true;
  }
  if (msg.indexOf('AdvancedMarkerElement') !== -1 && msg.indexOf('deprecated') !== -1) {
    return true;
  }
  return false;
}

export default defineNuxtPlugin({
  name: 'suppress-known-vue-warnings',
  enforce: 'pre',
  setup (nuxtApp) {
    const prevWarn = console.warn;
    console.warn = function (...args) {
      if (shouldSuppressConsoleWarn(args)) {
        return;
      }
      prevWarn.apply(console, args);
    };

    const prevHandler = nuxtApp.vueApp.config.warnHandler;
    nuxtApp.vueApp.config.warnHandler = (msg, instance, trace) => {
      if (shouldSuppressVueWarnMsg(msg)) {
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
