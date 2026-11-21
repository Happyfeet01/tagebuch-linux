<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, watch, ref } from 'vue'
import type { Entry } from '../api'
import { decryptPayload } from '../crypto'
import EntryList from './EntryList.vue'
import * as L from 'leaflet'
import 'leaflet/dist/leaflet.css'
const props = defineProps<{ entries: Entry[] }>()
const emit = defineEmits<{ open: [Entry] }>()
const rows = ref<Entry[]>([])
const from = ref(''), to = ref(''), place = ref('')
const mapElement = ref<HTMLElement>()
let map: L.Map | undefined
let markers: L.LayerGroup | undefined
const filtered = computed(() => rows.value.filter(e => (!from.value || e.date >= from.value) && (!to.value || e.date <= to.value)
	&& (!place.value || e.location?.name?.toLocaleLowerCase().includes(place.value.toLocaleLowerCase()))))
const page = ref(0)
const dates = computed(() => [...new Set(filtered.value.map(entry => entry.date))].sort().reverse())
const pageDates = computed(() => dates.value.slice(page.value * 4, page.value * 4 + 4))
const visible = computed(() => pageDates.value.flatMap(date => filtered.value.filter(entry => entry.date === date)))
watch(filtered, () => { page.value = 0 }, { flush: 'sync' })
watch(visible, refreshMap)
function refreshMap() {
	if (!map || !markers) return
	markers.clearLayers()
	const bounds = L.latLngBounds([])
	for (const entry of visible.value) {
		const loc = entry.location
		if (loc?.lat == null || loc.lon == null) continue
		const point = L.latLng(loc.lat, loc.lon)
		bounds.extend(point)
		L.circleMarker(point, { radius: 7 }).addTo(markers).on('click', () => emit('open', entry))
	}
	if (bounds.isValid()) map.fitBounds(bounds.pad(0.1), { maxZoom: 13 })
}
async function showMap() {
	if (map) return
	await nextTick()
	map = L.map(mapElement.value!).setView([50, 10], 5)
	L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map)
	markers = L.layerGroup().addTo(map)
	refreshMap()
}
watch(() => props.entries, async (entries, _, cleanup) => {
	let active = true
	cleanup(() => { active = false })
	const result = await Promise.all(entries.map(async entry => {
		const payload = entry.encrypted ? await decryptPayload(entry.body) : null
		return payload ? { ...entry, ...payload } : entry.encrypted ? { ...entry, body: '', heading: 'Verschlüsselter Eintrag' } : entry
	}))
	if (active) rows.value = result
}, { immediate: true })
onBeforeUnmount(() => map?.remove())
</script>
<template><section>
	<h2>Orte und Anhänge</h2>
	<div class="filters"><label>Von <input v-model="from" type="date"></label>
	<label>Bis <input v-model="to" type="date"></label><label>Ort <input v-model="place" type="search"></label></div>
	<details @toggle="event => { if ((event.target as HTMLDetailsElement).open) { showMap(); nextTick(() => map?.invalidateSize()) } }">
		<summary>Karte mit OpenStreetMap laden</summary><div ref="mapElement" class="map"></div>
	</details>
	<EntryList :entries="visible" :resolved="true" details empty-text="Keine passenden Einträge." @open="emit('open', $event)" />
	<nav v-if="dates.length > 4" aria-label="Tage durchblättern">
		<button type="button" :disabled="page === 0" @click="page--">Neuere Tage</button>
		<span>{{ page + 1 }} / {{ Math.ceil(dates.length / 4) }}</span>
		<button type="button" :disabled="(page + 1) * 4 >= dates.length" @click="page++">Ältere Tage</button>
	</nav>
</section></template>
<style scoped>
section { display: grid; gap: 12px; min-width: 0; }
.map { height: 300px; }
.filters, nav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.filters label { display: grid; min-width: 0; }
</style>
