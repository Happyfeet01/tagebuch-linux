<script setup lang="ts">
import { ref, watch } from 'vue'
import { generateUrl } from '@nextcloud/router'
import { api } from '../api'
import { mimeForName } from '../crypto'
const props = defineProps<{ date: string, preview?: boolean }>()
const emit = defineEmits<{ insert: [string], attach: [File] }>()
const calendar = ref('')
const data = ref<Awaited<ReturnType<typeof api.dayContext>>>()
const calendars = ref<{ id: string, name: string }[]>([])
const status = ref('')
let request = 0
const opened = ref(true)
const thumbnail = (id: number) => generateUrl('/core/preview') + '?' + new URLSearchParams({ fileId: String(id), x: '256', y: '256' })
async function load() {
	const id = ++request
	data.value = undefined
	if (!opened.value || !props.date) return
	status.value = 'Lade Tageskontext …'
	try {
		const result = await api.dayContext(props.date, calendar.value)
		if (id === request) { data.value = result; calendars.value = result.calendars; status.value = '' }
	} catch { if (id === request) status.value = 'Tageskontext nicht verfügbar.' }
}
async function attach(path: string, name: string) {
	try { emit('attach', new File([await api.photoData(path)], name, { type: mimeForName(name) })) }
	catch { status.value = 'Foto konnte nicht geladen werden.' }
}
watch([() => props.date, calendar, opened], load, { immediate: true })
</script>
<template>
	<details :open="opened" @toggle="opened = ($event.target as HTMLDetailsElement).open">
		<summary>Termine und Fotos dieses Tages</summary>
		<label v-if="calendars.length">Kalender <select v-model="calendar">
			<option value="">Alle Kalender</option>
			<option v-for="cal in calendars" :key="cal.id" :value="cal.id">{{ cal.name }}</option>
		</select></label>
		<p v-if="status" role="status">{{ status }}</p>
		<template v-if="data">
			<p v-if="!data.events.length">Keine Termine an diesem Tag.</p>
			<ul v-else>
				<li v-for="(event, index) in data.events" :key="index">
					<span v-if="preview">{{ event }}</span>
					<button v-else type="button" @click="emit('insert', event)">Termin übernehmen · {{ event }}</button>
				</li>
			</ul>
			<p v-if="!data.photos.length">Keine passenden Fotos im eingestellten Foto-Ordner.</p>
			<div v-else class="day-photos">
				<figure v-for="photo in data.photos" :key="photo.path">
					<img v-if="photo.id" :src="thumbnail(photo.id)" alt="" loading="lazy" @error="($event.target as HTMLImageElement).hidden = true">
					<figcaption>{{ photo.name }}</figcaption>
					<button v-if="!preview" type="button" @click="attach(photo.path, photo.name)">Anhängen</button>
				</figure>
			</div>
		</template>
	</details>
</template>

<style scoped>
details { margin-block: 1em; }
.day-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
figure { margin: 0; min-width: 0; overflow-wrap: anywhere; }
figure img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: var(--border-radius-large); }
</style>
