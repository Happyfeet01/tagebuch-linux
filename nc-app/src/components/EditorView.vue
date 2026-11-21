<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type Entry, type EntryLocation } from '../api'
import { attachmentName, decryptFile, decryptPayload, encryptPayload, isEncryptedAttachment, isEncryptionEnabled, isUnlocked, mimeForName } from '../crypto'

const props = defineProps<{ date: string, entryId?: number }>()
const emit = defineEmits<{ saved: [], closed: [] }>()

const entries = ref<Entry[]>([])
const selected = ref<Entry | null>(null)
const heading = ref('')
const body = ref('')
const rating = ref<number | null>(null)
const tagsInput = ref('')
const location = ref<EntryLocation>({})
const trackers = ref<Entry['trackers']>({})
const locating = ref(false)
const loading = ref(true)
const unreadable = ref(false)
let selectionVersion = 0

function locate() {
	if (!navigator.geolocation) {
		message.value = 'Standortabfrage ist in diesem Browser nicht verfügbar. Du kannst einen Ortsnamen eintragen.'
		return
	}
	const version = selectionVersion
	locating.value = true
	message.value = ''
	navigator.geolocation.getCurrentPosition(({ coords }) => {
		if (version !== selectionVersion) return
		location.value = { lat: coords.latitude, lon: coords.longitude }
		locating.value = false
	}, () => {
		if (version !== selectionVersion) return
		locating.value = false
		message.value = 'Standort nicht verfügbar. Prüfe die Browserfreigabe oder trage den Ort ein.'
	}, { timeout: 12000 })
}
const attachments = ref<string[]>([])
const templates = ref<string[]>([])
const message = ref('')
const saving = ref(false)

const load = async () => {
	loading.value = true
	try {
		entries.value = await api.day(props.date)
		await pick(entries.value.find(entry => entry.id === props.entryId) ?? entries.value[0] ?? null)
		loading.value = false
	} catch {
		message.value = 'Einträge konnten nicht geladen werden. Bitte öffne den Tag erneut.'
	}
}

const pick = async (entry: Entry | null) => {
	const version = ++selectionVersion
	locating.value = false
	unreadable.value = false
	trackers.value = {}
	location.value = {}
	selected.value = entry
	if (entry === null) {
		heading.value = ''
		body.value = ''
		rating.value = null
		tagsInput.value = ''
		attachments.value = []
		return
	}
	rating.value = entry.rating
	attachments.value = [...entry.attachments]
	if (entry.encrypted === 1) {
		unreadable.value = true
		const payload = isEncryptionEnabled() && isUnlocked() ? await decryptPayload(entry.body) : null
		if (version !== selectionVersion) return
		if (payload) {
			unreadable.value = false
			heading.value = payload.heading
			body.value = payload.body
			tagsInput.value = payload.tags.join(', ')
			location.value = { ...payload.location }
			trackers.value = payload.trackers
		} else {
			heading.value = ''
			body.value = '[verschlüsselt – in den Einstellungen entsperren]'
			tagsInput.value = ''
			unreadable.value = true
		}
		return
	}
	heading.value = entry.heading
	body.value = entry.body
	tagsInput.value = entry.tags.join(', ')
	location.value = { ...entry.location }
	trackers.value = entry.trackers
}

const save = async () => {
	saving.value = true
	message.value = ''
	try {
		const tags = tagsInput.value.split(',').map((t) => t.trim()).filter(Boolean)
		if (unreadable.value || (isEncryptionEnabled() && !isUnlocked())) {
			message.value = 'Bitte entsperre zuerst den Tresor in den Einstellungen.'
			return
		}
		const place = location.value.name?.trim() || location.value.lat != null || location.value.lon != null
			? location.value : null
		let payload: Record<string, unknown> = {
			id: selected.value?.id ?? null,
			date: props.date,
			time: selected.value?.time ?? null,
			heading: heading.value,
			body: body.value,
			rating: rating.value,
			tags,
			trackers: trackers.value,
			location: place,
			attachments: attachments.value,
			encrypted: 0,
		}
		if (isEncryptionEnabled() && isUnlocked()) {
			const cipher = await encryptPayload({
				heading: heading.value,
				body: body.value,
				tags,
				trackers: trackers.value,
				location: place,
			})
			payload = { ...payload, heading: '', body: cipher, tags: [], trackers: {}, location: null, encrypted: 1 }
		}
		const saved = await api.saveEntry(payload as Partial<Entry> & { date: string })
		entries.value = [...entries.value.filter(entry => entry.id !== saved.id), saved]
		selected.value = saved
		message.value = 'Gespeichert.'
		emit('saved')
	} catch {
		message.value = 'Speichern fehlgeschlagen. Dein Entwurf bleibt hier geöffnet.'
	} finally {
		saving.value = false
	}
}

const remove = async () => {
	if (!selected.value) return
	if (!window.confirm('Eintrag wirklich löschen?')) return
	await api.deleteEntry(selected.value.date, selected.value.time ?? undefined)
	emit('saved')
	await load()
}

const upload = async (event: Event) => {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	if (!file) return
	message.value = ''
	try {
		const res = await api.attachment(props.date, file)
		attachments.value.push(res.path)
	} catch (e) {
		message.value = 'Upload fehlgeschlagen: ' + (e as Error).message
	} finally {
		input.value = ''
	}
}

const preview = ref<{ url: string, kind: 'image' | 'video' | 'file', name: string } | null>(null)

const openAttachment = async (path: string) => {
	message.value = ''
	try {
		const stored = path.split('/').pop() ?? path
		const orig = attachmentName(path)
		const buf = await api.attachmentData(props.date, stored)
		let blob: Blob
		if (isEncryptedAttachment(stored)) {
			if (!isEncryptionEnabled() || !isUnlocked()) {
				message.value = 'Anhang ist verschlüsselt – bitte in den Einstellungen entsperren.'
				return
			}
			blob = await decryptFile(buf, orig)
		} else {
			blob = new Blob([buf], { type: mimeForName(orig) })
		}
		if (preview.value) URL.revokeObjectURL(preview.value.url)
		const url = URL.createObjectURL(blob)
		const kind = blob.type.startsWith('image/') ? 'image' : blob.type.startsWith('video/') ? 'video' : 'file'
		preview.value = { url, kind, name: orig }
	} catch {
		message.value = 'Anhang konnte nicht geladen werden.'
	}
}

const insertTemplate = async (name: string) => {
	const tpl = await api.template(name)
	body.value = body.value.trim() !== '' ? body.value.trim() + '\n\n' + tpl.body : tpl.body
}

onMounted(load)
onMounted(async () => {
	try {
		templates.value = await api.templates()
	} catch {
		templates.value = []
	}
})
</script>

<template>
	<div class="editor">
		<div class="editor-head">
			<h2>{{ props.date }}</h2>
			<div>
				<button type="button" :disabled="saving || loading || locating || unreadable" @click="save">Speichern</button>
				<button v-if="selected" type="button" class="danger" @click="remove">Löschen</button>
				<button type="button" @click="emit('closed')">Zurück</button>
			</div>
		</div>
		<p v-if="message" class="message">{{ message }}</p>

		<select v-if="entries.length > 1" :disabled="saving || loading" :value="selected?.id ?? ''" @change="pick(entries.find((e) => e.id === Number(($event.target as HTMLSelectElement).value)) ?? null)">
			<option v-for="e in entries" :key="e.id" :value="e.id">
				{{ e.time ?? 'Haupteintrag' }} – {{ e.heading || '(ohne Überschrift)' }}
			</option>
		</select>

		<fieldset :disabled="saving || loading || unreadable">
			<input v-model="heading" type="text" placeholder="Überschrift (optional)" class="heading">

			<textarea v-model="body" rows="14" placeholder="Markdown-Text …" class="body" />

			<div class="meta-row">
				<span>Bewertung:</span>
				<button
					v-for="n in 5"
					:key="n"
						type="button"
						class="star"
						:class="{ active: rating !== null && rating >= n }"
						@click="rating = rating === n ? null : n"
					>
					★
				</button>
				<input v-model="tagsInput" type="text" placeholder="Tags (kommagetrennt)" class="tags">
				<label>Ort der Erinnerung
					<input :value="location.name ?? ''" type="text" placeholder="z. B. Hallenbad Schlüchtern" :disabled="locating"
						@input="location = { name: ($event.target as HTMLInputElement).value }">
				</label>
				<button type="button" :disabled="locating" @click="locate">{{ locating ? 'Standort wird ermittelt …' : 'Aktuellen Standort übernehmen' }}</button>
				<span v-if="location.lat != null && location.lon != null">{{ location.lat.toFixed(5) }}, {{ location.lon.toFixed(5) }}</span>
				<button v-if="location.name || location.lat != null" type="button" :disabled="locating" @click="location = {}">Ort entfernen</button>
			</div>

			<div class="meta-row">
				<label class="attach">
					Datei anhängen
					<input type="file" hidden @change="upload">
				</label>
				<select v-if="templates.length" @change="insertTemplate(($event.target as HTMLSelectElement).value); ($event.target as HTMLSelectElement).value = ''">
					<option value="">Vorlage einfügen …</option>
					<option v-for="t in templates" :key="t" :value="t">{{ t }}</option>
				</select>
			</div>
		</fieldset>
		<ul v-if="attachments.length" class="attachments">
			<li v-for="a in attachments" :key="a">
				<button type="button" class="attach-link" @click="openAttachment(a)">{{ attachmentName(a) }}</button>
			</li>
		</ul>
		<div v-if="preview" class="preview">
			<img v-if="preview.kind === 'image'" :src="preview.url" :alt="preview.name">
			<video v-else-if="preview.kind === 'video'" :src="preview.url" controls></video>
			<a v-else :href="preview.url" :download="preview.name">{{ preview.name }} herunterladen</a>
		</div>
	</div>
</template>

<style scoped>
.editor {
	display: flex;
	flex-direction: column;
	gap: 10px;
	max-width: 760px;
}
fieldset { display: flex; flex-direction: column; gap: 10px; border: 0; padding: 0; min-width: 0; }
.editor-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.editor-head h2 {
	margin: 0;
}
.editor-head div {
	display: flex;
	gap: 8px;
}
.heading {
	font-size: 1.1em;
	font-weight: bold;
}
.body {
	font-family: monospace;
	width: 100%;
}
.meta-row {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
}
.star {
	border: none;
	background: none;
	font-size: 1.3em;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
}
.star.active {
	color: #e6a700;
}
.tags, .location {
	flex: 1;
	min-width: 140px;
}
.attach {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 4px 10px;
	cursor: pointer;
}
.danger {
	color: var(--color-error);
}
.message {
	color: var(--color-success);
}
.attachments {
	margin: 0;
	padding-left: 18px;
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}
.attach-link {
	border: none;
	background: none;
	padding: 0;
	cursor: pointer;
	color: var(--color-primary-element);
	text-decoration: underline;
	font-size: inherit;
}
.preview {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 8px;
	max-width: 100%;
}
.preview img, .preview video {
	max-width: 100%;
	max-height: 320px;
	display: block;
}
</style>
