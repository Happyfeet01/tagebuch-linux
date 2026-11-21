<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, watch, ref } from 'vue'
import type { Entry } from '../api'
import { decryptPayload } from '../crypto'
import { point, locationLabel, withinTenKm } from '../places'
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
interface City { id: number, name: string, country?: string, admin1?: string, latitude: number, longitude: number }
const cities = ref<City[]>([])
const city = ref<City>()
const status = ref('')
let request: AbortController | undefined
watch(place, () => {
	request?.abort()
	cities.value = []
	city.value = undefined
	status.value = ''
}, { flush: 'sync' })
async function searchCity() {
	request?.abort()
	const controller = new AbortController()
	request = controller
	status.value = 'Orte werden gesucht …'
	cities.value = []
	city.value = undefined
	try {
		const params = new URLSearchParams({ name: place.value.trim(), language: 'de', count: '8' })
		const response = await fetch('https://geocoding-api.open-meteo.com/v1/search?' + params, { signal: controller.signal, credentials: 'omit', referrerPolicy: 'no-referrer' })
		if (!response.ok) throw new Error()
		const data = await response.json()
		if (controller.signal.aborted) return
		cities.value = data.results || []
		status.value = cities.value.length ? '' : 'Kein Ort für die Umkreissuche gefunden.'
	} catch {
		if (!controller.signal.aborted) status.value = 'Die externe Ortssuche ist gerade nicht erreichbar.'
	}
}
const filtered = computed(() => rows.value.filter(entry => {
	if ((from.value && entry.date < from.value) || (to.value && entry.date > to.value)) return false
	const coords = point(entry.location)
	if (city.value) return coords && withinTenKm(coords, [city.value.latitude, city.value.longitude])
	const query = place.value.trim().toLocaleLowerCase()
	return !query || [entry.heading, locationLabel(entry.location), coords?.join(', ')].some(v => v?.toLocaleLowerCase().includes(query))
}))
const mapped = computed(() => filtered.value.flatMap(entry => {
	const coords = point(entry.location)
	return coords ? [{ entry, coords }] : []
}))
const page = ref(0)
const dates = computed(() => [...new Set(filtered.value.map(entry => entry.date))].sort().reverse())
const pageDates = computed(() => dates.value.slice(page.value * 4, page.value * 4 + 4))
const visible = computed(() => pageDates.value.flatMap(date => filtered.value.filter(entry => entry.date === date)))
watch(filtered, () => { page.value = 0 }, { flush: 'sync' })
watch(mapped, refreshMap)
function refreshMap() {
	if (!map || !markers) return
	markers.clearLayers()
	const bounds = L.latLngBounds([])
	for (const { entry, coords } of mapped.value) {
		const point = L.latLng(coords)
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
		return payload ? { ...entry, heading: payload.heading, body: payload.body, location: payload.location, tags: payload.tags, trackers: payload.trackers } : entry.encrypted ? { ...entry, body: '', heading: 'Verschlüsselter Eintrag' } : entry
	}))
	if (active) rows.value = result
}, { immediate: true })
onBeforeUnmount(() => { request?.abort(); map?.remove() })
</script>
<template><section>
	<h2>Orte und Anhänge</h2>
	<div class="filters"><label>Von <input v-model="from" type="date"></label>
	<label>Bis <input v-model="to" type="date"></label><label>Ort oder Titel <input v-model="place" type="search"></label>
	<button v-if="place.trim().length >= 2" type="button" @click="searchCity">Ortsnamen bei Open-Meteo suchen</button>
	<label v-if="cities.length">Umkreis (10 km)
		<select v-model="city"><option :value="undefined">Nur im Tagebuch suchen</option>
			<option v-for="item in cities" :key="item.id" :value="item">{{ [item.name, item.admin1, item.country].filter(Boolean).join(', ') }}</option>
		</select>
	</label></div>
	<p v-if="status" role="status">{{ status }}</p>
	<details @toggle="event => { if ((event.target as HTMLDetailsElement).open) { showMap(); nextTick(() => map?.invalidateSize()) } }">
		<summary>Karte mit OpenStreetMap laden ({{ mapped.length }} Einträge mit Koordinaten)</summary><div ref="mapElement" class="map"></div>
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
