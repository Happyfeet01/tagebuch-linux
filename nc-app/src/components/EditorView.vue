<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api, type Entry } from '../api'
import { attachmentName, decryptFile, decryptPayload, encryptPayload, isEncryptedAttachment, isEncryptionEnabled, isUnlocked, mimeForName } from '../crypto'

const props = defineProps<{ date: string }>()
const emit = defineEmits<{ saved: [], closed: [] }>()

const entries = ref<Entry[]>([])
const selected = ref<Entry | null>(null)
const heading = ref('')
const body = ref('')
const rating = ref<number | null>(null)
const tagsInput = ref('')
const locationInput = ref('')
const attachments = ref<string[]>([])
const templates = ref<string[]>([])
const message = ref('')
const saving = ref(false)

const load = async () => {
	entries.value = await api.day(props.date)
	templates.value = await api.templates()
	await pick(entries.value[0] ?? null)
}

const pick = async (entry: Entry | null) => {
	selected.value = entry
	if (entry === null) {
		heading.value = ''
		body.value = ''
		rating.value = null
		tagsInput.value = ''
		locationInput.value = ''
		attachments.value = []
		return
	}
	rating.value = entry.rating
	attachments.value = [...entry.attachments]
	if (entry.encrypted === 1) {
		const payload = isEncryptionEnabled() && isUnlocked() ? await decryptPayload(entry.body) : null
		if (payload) {
			heading.value = payload.heading
			body.value = payload.body
			tagsInput.value = payload.tags.join(', ')
			locationInput.value = payload.location?.name ?? ''
		} else {
			heading.value = ''
			body.value = '[verschlüsselt – in den Einstellungen entsperren]'
			tagsInput.value = ''
			locationInput.value = ''
		}
		return
	}
	heading.value = entry.heading
	body.value = entry.body
	tagsInput.value = entry.tags.join(', ')
	locationInput.value = entry.location?.name ?? ''
}

const save = async () => {
	saving.value = true
	message.value = ''
	try {
		const tags = tagsInput.value.split(',').map((t) => t.trim()).filter(Boolean)
		const location = locationInput.value ? { name: locationInput.value } : null
		let payload: Record<string, unknown> = {
			date: props.date,
			time: selected.value?.time ?? (entries.value.length > 0 ? new Date().toTimeString().slice(0, 5) : null),
			heading: heading.value,
			body: body.value,
			rating: rating.value,
			tags,
			trackers: {},
			location,
			attachments: attachments.value,
			encrypted: 0,
		}
		if (isEncryptionEnabled() && isUnlocked()) {
			const cipher = await encryptPayload({
				heading: heading.value,
				body: body.value,
				tags,
				trackers: {},
				location,
			})
			payload = { ...payload, heading: '', body: cipher, tags: [], trackers: {}, location: null, encrypted: 1 }
		}
		await api.saveEntry(payload as Partial<Entry> & { date: string })
		message.value = 'Gespeichert.'
		emit('saved')
		await load()
	} catch {
		message.value = 'Fehler beim Speichern.'
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

watch(() => props.date, load)
onMounted(load)
</script>

<template>
	<div class="editor">
		<div class="editor-head">
			<h2>{{ props.date }}</h2>
			<div>
				<button type="button" :disabled="saving" @click="save">Speichern</button>
				<button v-if="selected" type="button" class="danger" @click="remove">Löschen</button>
				<button type="button" @click="emit('closed')">Zurück</button>
			</div>
		</div>
		<p v-if="message" class="message">{{ message }}</p>

		<select v-if="entries.length > 1" :value="selected?.id ?? ''" @change="pick(entries.find((e) => e.id === Number(($event.target as HTMLSelectElement).value)) ?? null)">
			<option v-for="e in entries" :key="e.id" :value="e.id">
				{{ e.time ?? 'Haupteintrag' }} – {{ e.heading || '(ohne Überschrift)' }}
			</option>
		</select>

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
			<input v-model="locationInput" type="text" placeholder="Ort (optional)" class="location">
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
