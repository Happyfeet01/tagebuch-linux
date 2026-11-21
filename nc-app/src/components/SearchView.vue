<script setup lang="ts">
import { ref } from 'vue'
import { api, type Entry } from '../api'
import { decryptPayload, isEncryptionEnabled, isUnlocked } from '../crypto'
import EntryList from './EntryList.vue'

const emit = defineEmits<{ open: [entry: Entry] }>()

const q = ref('')
const tag = ref('')
const tags = ref<string[]>([])
const results = ref<Entry[] | null>(null)

const decryptEntry = async (entry: Entry): Promise<Entry> => {
	if (entry.encrypted !== 1 || !isEncryptionEnabled() || !isUnlocked()) return entry
	const payload = await decryptPayload(entry.body)
	if (!payload) return entry
	return { ...entry, encrypted: 0, heading: payload.heading, body: payload.body, tags: payload.tags, location: payload.location }
}

const run = async () => {
	if (isEncryptionEnabled() && isUnlocked()) {
		const needle = q.value.trim().toLowerCase()
		const all = await api.entries()
		const decrypted = await Promise.all(all.map(decryptEntry))
		results.value = decrypted.filter((e) => {
			if (tag.value && !e.tags.map((t) => t.toLowerCase()).includes(tag.value.toLowerCase())) return false
			if (!needle) return true
			const hay = `${e.heading}\n${e.body}\n${e.tags.join(' ')}`.toLowerCase()
			return hay.includes(needle)
		})
		return
	}
	results.value = await api.search(q.value, tag.value || undefined)
}

api.tags().then((t) => { tags.value = t })
</script>

<template>
	<div class="search">
		<div class="search-bar">
			<input v-model="q" type="search" placeholder="Suchen …" @input="run">
			<select v-model="tag" @change="run">
				<option value="">Alle Tags</option>
				<option v-for="t in tags" :key="t" :value="t">{{ t }}</option>
			</select>
		</div>
		<EntryList
			v-if="results !== null"
			:entries="results"
			empty-text="Keine Treffer."
			@open="emit('open', $event)"
		/>
	</div>
</template>

<style scoped>
.search-bar {
	display: flex;
	gap: 8px;
	margin-bottom: 10px;
}
.search-bar input {
	flex: 1;
}
</style>
