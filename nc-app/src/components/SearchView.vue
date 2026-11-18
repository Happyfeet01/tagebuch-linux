<script setup lang="ts">
import { ref } from 'vue'
import { api, type Entry } from '../api'
import EntryList from './EntryList.vue'

const emit = defineEmits<{ open: [entry: Entry] }>()

const q = ref('')
const tag = ref('')
const tags = ref<string[]>([])
const results = ref<Entry[] | null>(null)

const run = async () => {
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
