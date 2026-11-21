<script setup lang="ts">
import { ref, watch } from 'vue'
import { decryptPayload } from '../crypto'
import type { Entry } from '../api'
import { formatDate, stars } from '../api'

const props = defineProps<{ entries: Entry[], emptyText: string }>()
const display = ref<Entry[]>([])
watch(() => props.entries, async (rows, _, onCleanup) => {
	let current = true
	onCleanup(() => { current = false })
	const resolved = await Promise.all(rows.map(async entry => {
		if (!entry.encrypted) return entry
		const payload = await decryptPayload(entry.body)
		return payload ? { ...entry, ...payload } : { ...entry, body: '', heading: 'Verschlüsselter Eintrag' }
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
	gap: 6px;
}
.empty {
	color: var(--color-text-maxcontrast);
}
.entry-row {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: baseline;
	text-align: left;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	cursor: pointer;
}
.entry-row:hover {
	background: var(--color-background-hover);
}
.entry-date {
	font-weight: bold;
}
.entry-stars {
	color: #e6a700;
}
.entry-title {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.entry-tags {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}
</style>
