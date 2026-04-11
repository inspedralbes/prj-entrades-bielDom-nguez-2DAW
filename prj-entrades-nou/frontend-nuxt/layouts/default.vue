<template>
  <div class="app-shell">
    <header class="app-header">
      <NuxtLink to="/" class="app-logo" aria-label="Inici">Entrades</NuxtLink>
      <nav class="app-nav app-nav--desktop" aria-label="Navegació principal">
        <NuxtLink to="/">Inici</NuxtLink>
        <NuxtLink to="/search">Cercar</NuxtLink>
        <NuxtLink to="/tickets">Entrades</NuxtLink>
        <NuxtLink to="/saved">Guardats</NuxtLink>
        <NuxtLink to="/social">Social</NuxtLink>
        <NuxtLink to="/profile">Perfil</NuxtLink>
      </nav>
    </header>

    <main class="app-main">
      <slot />
    </main>

    <footer class="app-footer app-footer--mobile" aria-label="Navegació mòbil">
      <nav class="app-nav app-nav--mobile">
        <NuxtLink to="/">
          <span class="app-nav__ico" aria-hidden="true">⌂</span>
          Inici
        </NuxtLink>
        <NuxtLink to="/search">
          <span class="app-nav__ico" aria-hidden="true">⌕</span>
          Cercar
        </NuxtLink>
        <NuxtLink to="/tickets">
          <span class="app-nav__ico" aria-hidden="true">▦</span>
          Entrades
        </NuxtLink>
        <NuxtLink to="/saved">
          <span class="app-nav__ico" aria-hidden="true">♥</span>
          Guardats
        </NuxtLink>
        <NuxtLink to="/social">
          <span class="app-nav__ico" aria-hidden="true">◎</span>
          Social
        </NuxtLink>
        <NuxtLink to="/profile">
          <span class="app-nav__ico" aria-hidden="true">☺</span>
          Perfil
        </NuxtLink>
      </nav>
    </footer>
  </div>
</template>

<script setup>
</script>
