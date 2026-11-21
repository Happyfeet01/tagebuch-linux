<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { formatDate, type Entry } from '../api'
import { diariumCsv } from '../diariumCsv'

const props = defineProps<{ entries: Entry[] }>()
const selected = ref(new Set<Entry>())
const chosen = computed(() => props.entries.filter(entry => selected.value.has(entry)))
const busy = ref(false)
const error = ref('')
const url = ref('')
let revision = 0

function clearDownload() {
	revision++
	if (url.value) URL.revokeObjectURL(url.value)
	url.value = ''
	error.value = ''
}

watch(chosen, clearDownload, { flush: 'sync' })
onBeforeUnmount(clearDownload)

async function prepare() {
	clearDownload()
	const current = revision
	busy.value = true
	try {
		const csv = await diariumCsv(chosen.value)
		if (current === revision) {
			url.value = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
		}
	} catch (e) {
		if (current === revision) error.value = e instanceof Error ? e.message : 'Export fehlgeschlagen.'
	} finally {
		busy.value = false
	}
}
</script>

<template>
	<section>
		<h2>Für Diarium exportieren</h2>
		<p>Die CSV enthält deine ausgewählten Einträge im Klartext. Anhänge und Tracker sind nicht enthalten.</p>
		<p v-if="!entries.length">Keine Einträge vorhanden.</p>
		<template v-else>
			<button type="button" @click="selected = new Set(entries)">Alle auswählen</button>
			<button type="button" :disabled="!chosen.length" @click="selected = new Set()">Auswahl aufheben</button>
			<label v-for="entry in entries" :key="entry.id ?? entry.date + (entry.time ?? '')">
				<input v-model="selected" type="checkbox" :value="entry">
				{{ formatDate(entry.date, entry.time) }} · {{ entry.encrypted ? 'Verschlüsselter Eintrag' : entry.heading || entry.body.split('\n')[0] || '(ohne Text)' }}
			</label>
			<button v-if="!url" type="button" :disabled="busy || !chosen.length" @click="prepare">
				CSV erstellen ({{ chosen.length }})
			</button>
			<a v-else :href="url" download="diarium-eintraege.csv">CSV herunterladen</a>
			<p v-if="error" role="alert">{{ error }}</p>
			<p>In Diarium unter Einstellungen → Datenbank → Aus anderer App migrieren → CSV importieren.</p>
		</template>
	</section>
</template>

<style scoped>
label {
	display: block;
	padding: 4px 0;
	overflow-wrap: anywhere;
}
</style>
