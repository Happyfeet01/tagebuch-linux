<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api, type Entry, type Photo } from '../api'
import { mimeForName } from '../crypto'
import EntryList from './EntryList.vue'

const props = defineProps<{ entries: Entry[] }>()
const emit = defineEmits<{ open: [entry: Entry] }>()

const photos = ref<Photo[]>([])
const loading = ref(true)
const listError = ref(false)
const selected = ref('')
const page = ref(0)
const pageSize = 12
const urls = ref<Record<string, string>>({})
const isVideo = (name: string) => mimeForName(name).startsWith('video/')

const groups = computed(() => {
	const years = new Map<string, { year: string, items: Photo[], images: number, videos: number }>()
	for (const photo of photos.value) {
		const group = years.get(photo.year) ?? { year: photo.year, items: [], images: 0, videos: 0 }
		group.items.push(photo)
		if (isVideo(photo.name)) group.videos++
		else group.images++
		years.set(photo.year, group)
	}
	return [...years.values()].sort((a, b) => b.year.localeCompare(a.year))
})

const visible = computed(() => groups.value.find(group => group.year === selected.value)
	?.items.slice(page.value * pageSize, (page.value + 1) * pageSize) ?? [])

function toggle(year: string) {
	page.value = 0
	selected.value = selected.value === year ? '' : year
}

watch(visible, (items, _, cleanup) => {
	const controller = new AbortController()
	const loaded: Record<string, string> = {}
	urls.value = {}
	cleanup(() => {
		controller.abort()
		Object.values(loaded).forEach(url => { if (url) URL.revokeObjectURL(url) })
	})
	for (const item of items) {
		api.photoData(item.path, controller.signal, item.etag).then(data => {
			if (controller.signal.aborted) return
			loaded[item.path] = URL.createObjectURL(new Blob([data], { type: mimeForName(item.name) }))
			urls.value[item.path] = loaded[item.path]
		}).catch(() => {
			if (!controller.signal.aborted) urls.value[item.path] = ''
		})
	}
})

onMounted(async () => {
	try {
		photos.value = await api.onThisDayPhotos()
	} catch {
		listError.value = true
	} finally {
		loading.value = false
	}
})
</script>

<template>
	<div class="onthisday">
		<div v-if="loading || listError || groups.length" class="gallery">
			<h3>Fotos &amp; Videos an diesem Tag</h3>
			<p v-if="loading" class="hint">Lade Jahresübersicht …</p>
			<p v-else-if="listError" role="status">Die Jahresübersicht konnte nicht geladen werden.</p>
			<section v-for="group in groups" :key="group.year">
				<button type="button" class="folder" :aria-expanded="selected === group.year" @click="toggle(group.year)">
					<span aria-hidden="true">📁</span>
					<strong>{{ group.year }}</strong>
					<span>{{ group.images }} {{ group.images === 1 ? 'Bild' : 'Bilder' }}<template v-if="group.videos"> · {{ group.videos }} {{ group.videos === 1 ? 'Video' : 'Videos' }}</template></span>
				</button>
				<div v-if="selected === group.year">
					<div class="grid">
						<template v-for="p in visible" :key="p.path">
							<a v-if="urls[p.path]" :href="urls[p.path]" target="_blank" rel="noopener" class="tile" :aria-label="p.name">
								<video v-if="isVideo(p.name)" :src="urls[p.path]" muted preload="metadata" />
								<img v-else :src="urls[p.path]" :alt="p.name">
							</a>
							<span v-else class="tile filename">{{ urls[p.path] === '' ? 'Laden fehlgeschlagen' : 'Lädt …' }} · {{ p.name }}</span>
						</template>
					</div>
					<div v-if="group.items.length > pageSize" class="pages">
						<button type="button" :disabled="page === 0" @click="page--">Zurück</button>
						<span aria-live="polite">{{ page + 1 }} / {{ Math.ceil(group.items.length / pageSize) }}</span>
						<button type="button" :disabled="(page + 1) * pageSize >= group.items.length" @click="page++">Weiter</button>
					</div>
				</div>
			</section>
		</div>
		<EntryList
			:entries="entries"
			empty-text="Keine Einträge an diesem Tag in früheren Jahren."
			@open="emit('open', $event)"
		/>
	</div>
</template>

<style scoped>
.onthisday {
	display: flex;
	flex-direction: column;
	gap: 16px;
}
.gallery h3 {
	margin: 0 0 8px;
}
.hint {
	color: var(--color-text-maxcontrast);
}
.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
	gap: 8px;
}
.tile {
	position: relative;
	display: block;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
	aspect-ratio: 1;
	background: var(--color-main-background);
}
.tile img, .tile video {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}
.folder, .pages {
	display: flex;
	align-items: center;
	gap: 10px;
	margin: 8px 0;
}
.folder {
	width: 100%;
	justify-content: flex-start;
	padding: 8px 14px;
	min-height: 44px;
}
.folder span:last-child { margin-inline-start: auto; }
.pages { justify-content: center; }
.tile.filename {
	padding: 6px;
	font-size: 0.8em;
	word-break: break-all;
}
</style>
