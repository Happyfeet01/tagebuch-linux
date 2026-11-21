<script setup lang="ts">
import { ref } from 'vue'
import { readDiaryBackup } from '../diaryBackup'
import { api, type Entry } from '../api'
import { decryptPayload, encryptPayload, isEncryptionEnabled, isUnlocked } from '../crypto'
import { availableImportTime, importKey, parseDiarium } from '../diariumImport'

import { confirmedHtml, readAppleFolder, readJournalHtml, type HtmlEntry } from '../journalHtml'
const pending = ref<HtmlEntry[]>([])
const emit = defineEmits<{ imported: [] }>()
const rows = ref<Entry[]>([])
const busy = ref(false)
const message = ref('')

async function choose(event: Event) {
	const input = event.target as HTMLInputElement
	const files = Array.from(input.files ?? [])
	const file = files[0]
	input.value = ''
	rows.value = []
	pending.value = []
	message.value = ''
	if (!file) return
	busy.value = true
	try {
		const name = file.name.toLowerCase()
		if (!input.webkitdirectory && name.endsWith('.diary')) {
			rows.value = await readDiaryBackup(file)
		} else if (input.webkitdirectory || /\.(zip|html?)$/.test(name)) {
			const html = input.webkitdirectory ? await readAppleFolder(files) : await readJournalHtml(file)
			if (html.some(entry => !entry.date)) {
				pending.value = html
				message.value = 'Bitte ergänze fehlende Datumsangaben vor dem Import.'
				return
			}
			rows.value = confirmedHtml(html)
		} else if (name.endsWith('.json') || file.type === 'application/json') {
			rows.value = parseDiarium(await file.text())
		} else {
			throw new Error('Bitte wähle eine Diarium-Sicherung (.diary), JSON, HTML oder ein ZIP mit HTML-Einträgen.')
		}
		message.value = rows.value.length + ' Einträge gefunden.'
	} catch (error) {
		message.value = error instanceof SyntaxError ? 'Die JSON-Datei konnte nicht gelesen werden.' : String(error)
	} finally {
		busy.value = false
	}
}

function confirmDates() {
	try {
		rows.value = confirmedHtml(pending.value)
		pending.value = []
		message.value = rows.value.length + ' Einträge bereit.'
	} catch { message.value = 'Bitte prüfe das Datum der Einträge.' }
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
		<h3>Tagebuch importieren</h3>
		<p>Wähle Diarium (.diary oder JSON) oder datierte HTML-Einträge aus einem Apple-Journal- oder Pixel-Journal-Export (ZIP oder HTML). Bei HTML werden Titel, Datum und Text übernommen. Die Datei wird im Browser gelesen. Bei Diarium werden Datum, Uhrzeit, Titel, Text, Tags, Sterne und Koordinaten übernommen. Anhänge, Vorlagen, Personen, Tracker und Ereignisse bleiben in der Exportquelle.</p>
		<p>Verschiedene Zeitstempel innerhalb einer Minute bleiben erhalten. Bei identischer Zeit bleiben unterschiedliche Inhalte durch Sekundenbruchteile unterscheidbar.</p>
		<input type="file" aria-label="Tagebuchdatei auswählen" :disabled="busy" @change="choose">
		<label>Apple-Journal-Ordner auswählen
			<input type="file" webkitdirectory multiple :disabled="busy" @change="choose">
		</label>
		<form v-if="pending.length" @submit.prevent="confirmDates">
			<label v-for="entry in pending.filter(item => !item.date.includes('T'))" :key="pending.indexOf(entry)">
				{{ entry.heading || entry.source }} <input type="date" v-model="entry.date" required>
			</label>
			<button type="submit">Datumsangaben bestätigen</button>
		</form>
		<button type="button" :disabled="busy || !rows.length" @click="importEntries">Einträge importieren</button>
		<p role="status">{{ message }}</p>
	</section>
</template>
