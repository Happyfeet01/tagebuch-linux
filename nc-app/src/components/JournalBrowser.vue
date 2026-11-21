<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import type { Entry } from '../api'
import { decryptPayload } from '../crypto'
import AttachmentTile from './AttachmentTile.vue'
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
const photos = computed(() => filtered.value.flatMap(entry => entry.attachments.map(path => ({ entry, path }))))
function refreshMap() {
	if (!map || !markers) return
	markers.clearLayers()
	const bounds = L.latLngBounds([])
	for (const entry of filtered.value) {
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
onMounted(async () => {
	rows.value = await Promise.all(props.entries.map(async entry => {
		const payload = entry.encrypted ? await decryptPayload(entry.body) : null
		return payload ? { ...entry, ...payload } : entry
	}))
	refreshMap()
})
onBeforeUnmount(() => map?.remove())
</script>
<template><section>
	<h2>Orte und Anhänge</h2>
	<div class="filters" @input="nextTick(refreshMap)"><label>Von <input v-model="from" type="date"></label>
	<label>Bis <input v-model="to" type="date"></label><label>Ort <input v-model="place" type="search"></label></div>
	<details @toggle="event => { if ((event.target as HTMLDetailsElement).open) { showMap(); nextTick(() => map?.invalidateSize()) } }">
		<summary>Karte mit OpenStreetMap laden</summary><div ref="mapElement" class="map"></div>
	</details>
	<p>{{ filtered.length }} Einträge · {{ photos.length }} Anhänge</p>
	<button v-for="e in filtered" :key="e.id" type="button" @click="emit('open', e)">{{ e.date }} · {{ e.location?.name || e.heading || e.time || 'Eintrag' }}</button>
	<div class="gallery"><button v-for="f in photos" :key="f.entry.id + f.path" type="button" @click="emit('open', f.entry)">
		<AttachmentTile :date="f.entry.date" :path="f.path" /> · {{ f.entry.date }}
	</button></div>
</section></template>
<style scoped>.map{height:300px}.filters,.gallery{display:flex;flex-wrap:wrap;gap:8px}.gallery button{max-width:180px;height:auto;white-space:normal}</style>
