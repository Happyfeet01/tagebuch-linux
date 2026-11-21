<script setup lang="ts">
import { ref } from 'vue'
import { api } from '../api'
import { mimeForName } from '../crypto'
const props = defineProps<{ date: string }>()
const emit = defineEmits<{ insert: [string], attach: [File] }>()
const calendar = ref('')
const data = ref<Awaited<ReturnType<typeof api.dayContext>>>()
const status = ref('')
let request = 0
async function load() {
	const id = ++request
	status.value = 'Lade Tageskontext …'
	try {
		const result = await api.dayContext(props.date, calendar.value)
		if (id === request) { data.value = result; status.value = '' }
	} catch { if (id === request) status.value = 'Tageskontext nicht verfügbar.' }
}
async function attach(path: string, name: string) {
	try { emit('attach', new File([await api.photoData(path)], name, { type: mimeForName(name) })) }
	catch { status.value = 'Foto konnte nicht geladen werden.' }
}
</script>
<template>
	<details @toggle="event => { if ((event.target as HTMLDetailsElement).open && !data) load() }">
		<summary>Termine und Fotos dieses Tages</summary>
		<p role="status">{{ status }}</p>
		<template v-if="data">
			<label>Kalender <select v-model="calendar" @change="load">
				<option value="">Kalender auswählen</option>
				<option v-for="cal in data.calendars" :key="cal.id" :value="cal.id">{{ cal.name }}</option>
			</select></label>
			<p v-if="calendar && !data.events.length">Keine Termine gefunden.</p>
			<button v-for="(event, index) in data.events" :key="index" type="button" @click="emit('insert', event)">Termin übernehmen · {{ event }}</button>
			<p v-if="!data.photos.length">Keine passenden Fotos im eingestellten Foto-Ordner.</p>
			<button v-for="photo in data.photos" :key="photo.path" type="button" @click="attach(photo.path, photo.name)">Anhängen · {{ photo.name }}</button>
		</template>
	</details>
</template>
