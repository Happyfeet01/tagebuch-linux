<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api, type Entry } from '../api'

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
	pick(entries.value[0] ?? null)
}

const pick = (entry: Entry | null) => {
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
	heading.value = entry.heading
	body.value = entry.body
	rating.value = entry.rating
	tagsInput.value = entry.tags.join(', ')
	locationInput.value = entry.location?.name ?? ''
	attachments.value = [...entry.attachments]
}

const save = async () => {
	saving.value = true
	message.value = ''
	try {
		await api.saveEntry({
			date: props.date,
			time: selected.value?.time ?? (entries.value.length > 0 ? new Date().toTimeString().slice(0, 5) : null),
			heading: heading.value,
			body: body.value,
			rating: rating.value,
			tags: tagsInput.value.split(',').map((t) => t.trim()).filter(Boolean),
			location: locationInput.value ? { name: locationInput.value } : null,
			attachments: attachments.value,
		})
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
	const res = await api.attachment(props.date, file)
	attachments.value.push(res.path)
	input.value = ''
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
			<li v-for="a in attachments" :key="a">{{ a }}</li>
		</ul>
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
</style>
