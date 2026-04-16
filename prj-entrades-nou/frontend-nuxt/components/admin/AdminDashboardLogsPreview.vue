<template>
  <NuxtLink
    to="/admin/logs"
    class="adm-dash__panel adm-dash__panel--click"
  >
    <h2 class="adm-dash__h2">Darrers canvis (admin)</h2>
    <p class="adm-dash__muted">Obre la pàgina de registre complet (10 per pàgina).</p>
    <ul v-if="rows.length > 0" class="adm-dash__log-list">
      <li v-for="row in rows" :key="'l' + row.id" class="adm-dash__log-item">
        <span class="adm-dash__log-main">{{ row.date }} {{ row.time }} — {{ row.admin_name }}</span>
        <span class="adm-dash__log-ip">IP {{ row.ip_address }}</span>
        <span class="adm-dash__log-sum">{{ row.summary }}</span>
      </li>
    </ul>
    <p v-else class="adm-dash__muted">Encara no hi ha registres d’auditoria.</p>
  </NuxtLink>
</template>

<script setup>
defineProps({
  rows: {
    type: Array,
    default () {
      return [];
    },
  },
});
</script>

<style scoped>
.adm-dash__h2 {
  margin: 0 0 1rem;
  font-family: Epilogue, system-ui, sans-serif;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #ccc7ac;
}

.adm-dash__panel {
  margin-bottom: 1.5rem;
  padding: 2rem;
  background: #1c1b1b;
  border: 1px solid rgba(74, 71, 51, 0.2);
  border-radius: 1rem;
}

.adm-dash__panel--click {
  display: block;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.adm-dash__panel--click:hover {
  border-color: rgba(247, 230, 40, 0.35);
  background: #201f1f;
}

.adm-dash__panel--click:focus-visible {
  outline: 2px solid #f7e628;
  outline-offset: 2px;
}

.adm-dash__log-list {
  list-style: none;
  margin: 0.75rem 0 0;
  padding: 0;
  max-height: 220px;
  overflow-y: auto;
  font-family: ui-monospace, 'Cascadia Code', monospace;
  font-size: 0.7rem;
}

.adm-dash__log-item {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.65rem 0;
  border-bottom: 1px solid rgba(74, 71, 51, 0.35);
  color: #c8c4b0;
}

.adm-dash__log-main {
  color: #e5e2e1;
  font-weight: 600;
}

.adm-dash__log-ip {
  font-size: 0.68rem;
  color: #ccc7ac;
}

.adm-dash__log-sum {
  color: #e5e2e1;
}

.adm-dash__muted {
  font-size: 0.75rem;
  color: #ccc7ac;
  margin: 0.75rem 0 0;
  letter-spacing: 0.04em;
}
</style>
