<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api, type Entry, type Photo } from '../api'
import { mimeForName } from '../crypto'
import EntryList from './EntryList.vue'

const props = defineProps<{ entries: Entry[] }>()
const emit = defineEmits<{ open: [entry: Entry] }>()

const photos = ref<{ photo: Photo, url: string }[]>([])
const loading = ref(false)

const isImage = (name: string): boolean => mimeForName(name).startsWith('image/')
const isVideo = (name: string): boolean => mimeForName(name).startsWith('video/')

const loadPhotos = async () => {
	loading.value = true
	try {
		const list = await api.onThisDayPhotos()
		const loaded = await Promise.all(list.map(async (p) => {
			try {
				const buf = await api.photoData(p.path)
				return { photo: p, url: URL.createObjectURL(new Blob([buf], { type: mimeForName(p.name) })) }
			} catch {
				return null
			}
		}))
		photos.value = loaded.filter((x): x is { photo: Photo, url: string } => x !== null)
	} catch {
		photos.value = []
	} finally {
		loading.value = false
	}
}

watch(() => props.entries, loadPhotos)
onMounted(loadPhotos)
</script>

<template>
	<div class="onthisday">
		<div v-if="loading || photos.length" class="gallery">
			<h3>Fotos &amp; Videos an diesem Tag</h3>
			<p v-if="loading" class="hint">Lade Fotos …</p>
			<div v-else class="grid">
				<a v-for="p in photos" :key="p.photo.path" :href="p.url" target="_blank" rel="noopener" class="tile">
					<img v-if="isImage(p.photo.name)" :src="p.url" :alt="p.photo.name" loading="lazy">
					<video v-else-if="isVideo(p.photo.name)" :src="p.url" muted preload="metadata" />
					<span v-else class="filename">{{ p.photo.name }}</span>
					<em class="year">{{ p.photo.year }}</em>
				</a>
			</div>
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
.tile .year {
	position: absolute;
	left: 4px;
	bottom: 4px;
	background: rgba(0, 0, 0, 0.6);
	color: #fff;
	font-size: 0.75em;
	padding: 1px 6px;
	border-radius: var(--border-radius-pill);
}
.tile .filename {
	padding: 6px;
	font-size: 0.8em;
	word-break: break-all;
}
</style>
