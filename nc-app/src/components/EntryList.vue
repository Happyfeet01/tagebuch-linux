<script setup lang="ts">
import { displayPlace } from '../placeNames'
import { ref, watch } from 'vue'
import { decryptPayload } from '../crypto'
import type { Entry } from '../api'
import { formatDate, stars } from '../api'

const props = defineProps<{ entries: Entry[], emptyText: string, details?: boolean, resolved?: boolean }>()
const display = ref<Entry[]>([])
watch(() => props.entries, async (rows, _, onCleanup) => {
	let current = true
	onCleanup(() => { current = false })
	const resolved = await Promise.all(rows.map(async entry => {
		if (props.resolved || !entry.encrypted) return entry
		const payload = await decryptPayload(entry.body)
		return payload ? { ...entry, heading: payload.heading, body: payload.body, location: payload.location, tags: payload.tags, trackers: payload.trackers } : { ...entry, body: '', heading: 'Verschlüsselter Eintrag' }
	}))
	if (current) display.value = resolved
}, { immediate: true })
const emit = defineEmits<{ open: [entry: Entry] }>()
</script>

<template>
	<div class="entry-list">
		<p v-if="entries.length === 0" class="empty">{{ emptyText }}</p>
		<button
			v-for="entry in display"
			:key="entry.id ?? entry.date + (entry.time ?? '')"
			class="entry-row"
			type="button"
			@click="emit('open', entry)"
		>
			<span class="entry-date">{{ formatDate(entry.date, entry.time) }}</span>
			<span v-if="entry.rating !== null" class="entry-stars">{{ stars(entry.rating) }}</span>
			<span class="entry-title">{{ entry.heading || firstLine(entry.body) || '(ohne Text)' }}</span>
			<span v-if="details && displayPlace(entry.location)" class="entry-tags">{{ displayPlace(entry.location) }}</span>
			<span v-if="details && entry.attachments.length" class="entry-tags">{{ entry.attachments.length }} {{ entry.attachments.length === 1 ? 'Anhang' : 'Anhänge' }}</span>
			<span v-if="entry.tags.length" class="entry-tags">{{ entry.tags.map((t) => '#' + t).join(' ') }}</span>
		</button>
	</div>
</template>

<script lang="ts">
export default {
	methods: {
		firstLine(body: string): string {
			const line = body.trim().split('\n')[0] ?? ''
			return line.length > 80 ? line.slice(0, 80) + '…' : line
		},
	},
}
</script>

<style scoped>
.entry-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.empty {
	color: var(--color-text-maxcontrast);
}
.entry-row {
	display: flex;
	flex-wrap: wrap;
	min-width: 0;
	width: 100%;
	height: auto;
	white-space: normal;
	gap: 8px;
	align-items: center;
	text-align: left;
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 77, 130, .05);
	cursor: pointer;
	transition: border-color .15s, box-shadow .15s;
}
.entry-row:hover { background: var(--color-background-hover); border-color: var(--color-primary-element); }
.entry-row:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: 2px; }
.entry-date { font-weight: 700; color: var(--color-primary-element); font-size: .9em; }
.entry-stars {
	color: #e6a700;
}
.entry-title {
	flex: 1 1 100%;
	font-weight: 650;
	font-size: 1.06em;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.entry-tags { color: var(--color-text-maxcontrast); font-size: .85em; overflow-wrap: anywhere; }
@media(max-width: 420px) { .entry-row { padding: 12px; } .entry-title { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; } }
</style>
