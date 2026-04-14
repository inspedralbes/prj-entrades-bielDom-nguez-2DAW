<template>
  <div class="adm-rep">
    <h1 class="adm-rep__h1">Informes</h1>

    <section class="adm-rep__panel">
      <h2 class="adm-rep__h2">Filtres vendes</h2>
      <div class="adm-rep__grid">
        <label class="adm-rep__lbl">Des de</label>
        <input v-model="salesFrom" type="date" class="adm-rep__input">
        <label class="adm-rep__lbl">Fins a</label>
        <input v-model="salesTo" type="date" class="adm-rep__input">
        <label class="adm-rep__lbl">Agregació</label>
        <select v-model="salesBucket" class="adm-rep__input">
          <option value="day">Dia</option>
          <option value="hour">Hora</option>
        </select>
        <label class="adm-rep__lbl">Esdeveniment (opcional)</label>
        <select v-model="salesEventId" class="adm-rep__input">
          <option value="">Tots</option>
          <option v-for="ev in eventOptions" :key="ev.id" :value="String(ev.id)">{{ ev.id }} — {{ ev.name }}</option>
        </select>
      </div>
      <p v-if="salesErr" class="adm-rep__err">{{ salesErr }}</p>
      <button type="button" class="adm-rep__btn" :disabled="salesPending" @click="loadSales">Carregar vendes</button>

      <div v-if="salesSeries.length > 0" class="adm-rep__chart">
        <svg :viewBox="`0 0 ${chartW} ${chartH}`" class="adm-rep__svg" aria-label="Gràfic de vendes">
          <polyline
            :points="salesPolylinePoints"
            fill="none"
            stroke="#ff0055"
            stroke-width="2"
          />
          <g v-for="(pt, pi) in salesPointLabels" :key="pi">
            <circle :cx="pt.x" :cy="pt.y" r="3" fill="#ff0055" />
          </g>
        </svg>
        <ul class="adm-rep__legend">
          <li v-for="(s, si) in salesSeries" :key="si">{{ s.bucket }}: €{{ s.amount_eur }}</li>
        </ul>
      </div>
    </section>

    <section class="adm-rep__panel">
      <h2 class="adm-rep__h2">Ocupació per esdeveniment</h2>
      <div class="adm-rep__row">
        <label class="adm-rep__lbl" for="occ-ev">Esdeveniment</label>
        <select id="occ-ev" v-model="occEventId" class="adm-rep__input">
          <option value="">—</option>
          <option v-for="ev in eventOptions" :key="'o-' + ev.id" :value="String(ev.id)">{{ ev.id }} — {{ ev.name }}</option>
        </select>
        <button type="button" class="adm-rep__btn" :disabled="occPending || occEventId === ''" @click="loadOccupancy">
          Carregar
        </button>
      </div>
      <p v-if="occErr" class="adm-rep__err">{{ occErr }}</p>
      <div v-else-if="occData" class="adm-rep__occ">
        <div
          class="adm-rep__donut"
          :style="{ background: occDonutGradient }"
          role="img"
          :aria-label="`Ocupació ${occData.occupancy_percent} per cent`"
        />
        <dl class="adm-rep__dl">
          <div class="adm-rep__drow">
            <dt>Capacitat</dt>
            <dd>{{ occData.capacity }}</dd>
          </div>
          <div class="adm-rep__drow">
            <dt>Venuts</dt>
            <dd>{{ occData.sold }}</dd>
          </div>
          <div class="adm-rep__drow">
            <dt>Lliures</dt>
            <dd>{{ occData.remaining }}</dd>
          </div>
          <div class="adm-rep__drow">
            <dt>Ocupació</dt>
            <dd>{{ occData.occupancy_percent }}%</dd>
          </div>
        </dl>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthorizedApi } from '~/composables/useAuthorizedApi';

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
});

const { getJson } = useAuthorizedApi();

const chartW = 640;
const chartH = 220;
const pad = 24;

const eventOptions = ref([]);
const salesFrom = ref('');
const salesTo = ref('');
const salesBucket = ref('day');
const salesEventId = ref('');
const salesPending = ref(false);
const salesErr = ref('');
const salesPayload = ref(null);

const occEventId = ref('');
const occPending = ref(false);
const occErr = ref('');
const occData = ref(null);

const salesSeries = computed(() => {
  const p = salesPayload.value;
  if (!p || !p.series) {
    return [];
  }
  const s = p.series;
  const out = [];
  for (let i = 0; i < s.length; i++) {
    out.push(s[i]);
  }
  return out;
});

const salesMaxAmount = computed(() => {
  const s = salesSeries.value;
  let m = 0;
  for (let i = 0; i < s.length; i++) {
    const v = Number(s[i].amount_eur);
    if (v > m) {
      m = v;
    }
  }
  if (m <= 0) {
    return 1;
  }
  return m;
});

const salesPolylinePoints = computed(() => {
  const s = salesSeries.value;
  const n = s.length;
  if (n === 0) {
    return '';
  }
  const innerW = chartW - pad * 2;
  const innerH = chartH - pad * 2;
  const maxY = salesMaxAmount.value;
  const parts = [];
  for (let i = 0; i < n; i++) {
    let xFrac = 0;
    if (n === 1) {
      xFrac = innerW / 2;
    } else {
      xFrac = (innerW * i) / (n - 1);
    }
    const x = pad + xFrac;
    const amt = Number(s[i].amount_eur);
    let y = pad + innerH;
    if (maxY > 0) {
      y = pad + innerH - (innerH * amt) / maxY;
    }
    parts.push(`${x},${y}`);
  }
  return parts.join(' ');
});

const salesPointLabels = computed(() => {
  const s = salesSeries.value;
  const n = s.length;
  const out = [];
  const innerW = chartW - pad * 2;
  const innerH = chartH - pad * 2;
  const maxY = salesMaxAmount.value;
  for (let i = 0; i < n; i++) {
    let xFrac = 0;
    if (n === 1) {
      xFrac = innerW / 2;
    } else {
      xFrac = (innerW * i) / (n - 1);
    }
    const x = pad + xFrac;
    const amt = Number(s[i].amount_eur);
    let y = pad + innerH;
    if (maxY > 0) {
      y = pad + innerH - (innerH * amt) / maxY;
    }
    out.push({ x, y });
  }
  return out;
});

const occDonutGradient = computed(() => {
  const o = occData.value;
  if (!o) {
    return '#333';
  }
  const pct = Number(o.occupancy_percent);
  let p = pct;
  if (p < 0) {
    p = 0;
  }
  if (p > 100) {
    p = 100;
  }
  return `conic-gradient(#ff0055 0deg ${p * 3.6}deg, #333 ${p * 3.6}deg 360deg)`;
});

function defaultDates () {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - 7);
  const iso = (d) => {
    const y = d.getFullYear();
    let m = String(d.getMonth() + 1);
    if (m.length < 2) {
      m = `0${m}`;
    }
    let day = String(d.getDate());
    if (day.length < 2) {
      day = `0${day}`;
    }
    return `${y}-${m}-${day}`;
  };
  salesTo.value = iso(end);
  salesFrom.value = iso(start);
}

async function loadEventsForSelect () {
  try {
    const res = await getJson('/api/admin/events?per_page=100&hidden=include');
    const rows = [];
    if (res && res.data && Array.isArray(res.data)) {
      const d = res.data;
      for (let i = 0; i < d.length; i++) {
        rows.push(d[i]);
      }
    }
    eventOptions.value = rows;
  } catch (e) {
    console.error(e);
  }
}

async function loadSales () {
  salesErr.value = '';
  if (!salesFrom.value || !salesTo.value) {
    salesErr.value = 'Selecciona des de / fins a.';
    return;
  }
  salesPending.value = true;
  try {
    const q = new URLSearchParams();
    q.set('from', salesFrom.value);
    q.set('to', salesTo.value);
    q.set('bucket', salesBucket.value);
    const eid = salesEventId.value.trim();
    if (eid !== '') {
      q.set('event_id', eid);
    }
    salesPayload.value = await getJson(`/api/admin/reports/sales?${q.toString()}`);
  } catch (e) {
    salesErr.value = 'No s’han pogut carregar les vendes.';
    salesPayload.value = null;
    console.error(e);
  } finally {
    salesPending.value = false;
  }
}

async function loadOccupancy () {
  occErr.value = '';
  const eid = occEventId.value.trim();
  if (eid === '') {
    occErr.value = 'Tria un esdeveniment.';
    return;
  }
  occPending.value = true;
  try {
    occData.value = await getJson(`/api/admin/reports/occupancy?event_id=${encodeURIComponent(eid)}`);
  } catch (e) {
    occErr.value = 'No s’ha pogut carregar l’ocupació.';
    occData.value = null;
    console.error(e);
  } finally {
    occPending.value = false;
  }
}

onMounted(() => {
  defaultDates();
  loadEventsForSelect();
});
</script>

<style scoped>
.adm-rep {
  max-width: 56rem;
}
.adm-rep__h1 {
  margin: 0 0 1rem;
  color: #ff0055;
  font-size: 1.35rem;
}
.adm-rep__h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  color: #bbb;
}
.adm-rep__panel {
  margin-bottom: 1.25rem;
  padding: 1rem;
  background: #111;
  border: 1px solid #2a2a2a;
  border-radius: 8px;
}
.adm-rep__grid {
  display: grid;
  grid-template-columns: 10rem 1fr;
  gap: 0.5rem 1rem;
  align-items: center;
  margin-bottom: 0.75rem;
}
.adm-rep__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}
.adm-rep__lbl {
  font-size: 0.85rem;
  color: #aaa;
}
.adm-rep__input {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #eee;
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
  max-width: 100%;
}
.adm-rep__btn {
  background: #ff0055;
  color: #fff;
  border: none;
  padding: 0.4rem 0.85rem;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}
.adm-rep__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.adm-rep__err {
  color: #ff6b6b;
  font-size: 0.9rem;
}
.adm-rep__chart {
  margin-top: 1rem;
}
.adm-rep__svg {
  width: 100%;
  height: auto;
  background: #0a0a0a;
  border-radius: 8px;
}
.adm-rep__legend {
  margin: 0.5rem 0 0;
  padding-left: 1.1rem;
  font-size: 0.8rem;
  color: #999;
  max-height: 8rem;
  overflow: auto;
}
.adm-rep__occ {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  align-items: center;
  margin-top: 0.75rem;
}
.adm-rep__donut {
  width: 140px;
  height: 140px;
  border-radius: 50%;
}
.adm-rep__dl {
  margin: 0;
}
.adm-rep__drow {
  display: flex;
  gap: 0.75rem;
  font-size: 0.9rem;
  color: #e0e0e0;
}
.adm-rep__drow dt {
  min-width: 6rem;
  color: #888;
}
.adm-rep__drow dd {
  margin: 0;
}
</style>
