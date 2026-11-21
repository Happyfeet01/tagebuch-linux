<script setup lang="ts">
import { ref } from 'vue'
import { api, type Entry } from '../api'
import { decryptPayload, encryptPayload, isEncryptionEnabled, isUnlocked } from '../crypto'
import { availableImportTime, importKey, parseDiarium } from '../diariumImport'

const emit = defineEmits<{ imported: [] }>()
const rows = ref<Entry[]>([])
const busy = ref(false)
const message = ref('')

async function choose(event: Event) {
	const file = (event.target as HTMLInputElement).files?.[0]
	rows.value = []
	message.value = ''
	if (!file) return
	busy.value = true
	try {
		rows.value = parseDiarium(await file.text())
		message.value = rows.value.length + ' Einträge in der Datei gefunden.'
	} catch (error) {
		message.value = error instanceof SyntaxError ? 'Bitte wähle eine Diarium-JSON-Datei.' : String(error)
	} finally {
		busy.value = false
	}
}

async function importEntries() {
	if (busy.value || !rows.value.length) return
	busy.value = true
	let saved = 0
	let skipped = 0
	const encrypt = isEncryptionEnabled()
	try {
		const existing = await api.entries()
		const occupied = new Map<string, Entry>()
		for (const entry of existing) {
			const clear = entry.encrypted ? await decryptPayload(entry.body) : null
			if (entry.encrypted && !clear) throw new Error('Bitte zuerst den Tresor entsperren.')
			occupied.set(importKey(entry), clear ? { ...entry, ...clear } : entry)
		}
		for (const row of rows.value) {
			const slot = availableImportTime(row, occupied)
			if (slot === null) { skipped++; continue }
			if (encrypt !== isEncryptionEnabled() || (encrypt && !isUnlocked())) {
				throw new Error('Bitte prüfe die Verschlüsselung und entsperre den Tresor.')
			}
			const payload = encrypt ? { ...row, body: await encryptPayload(row), heading: '', tags: [],
				trackers: {}, location: null, encrypted: 1 } : row
			await api.saveEntry({ ...payload, time: slot })
			saved++
			occupied.set(importKey({ ...row, time: slot }), row)
			message.value = saved + ' Einträge gespeichert.'
		}
		message.value = saved + ' gespeichert, ' + skipped + ' bereits vorhanden.'
		rows.value = []
	} catch (error) {
		message.value = 'Import angehalten nach ' + saved + ' gespeicherten Einträgen. '
			+ (error instanceof Error ? error.message : '') + ' Du kannst den Import erneut starten.'
	} finally {
		busy.value = false
		if (saved) emit('imported')
	}
}
</script>

<template>
	<section>
		<h3>Aus Diarium importieren</h3>
		<p>Wähle den JSON-Export aus Diarium. Übernommen werden Datum, Uhrzeit, Titel, Text, Tags, Sterne und Koordinaten. Anhänge, Personen, Tracker und Wetter bleiben in der Exportquelle.</p>
		<p>Verschiedene Zeitstempel innerhalb einer Minute bleiben erhalten. Bei identischer Zeit bleiben unterschiedliche Inhalte durch Sekundenbruchteile unterscheidbar.</p>
		<input type="file" aria-label="Diarium-JSON auswählen" accept=".json,application/json" :disabled="busy" @change="choose">
		<button type="button" :disabled="busy || !rows.length" @click="importEntries">Einträge importieren</button>
		<p role="status">{{ message }}</p>
	</section>
</template>
