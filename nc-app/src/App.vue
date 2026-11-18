<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type Entry } from './api'
import EntryList from './components/EntryList.vue'
import CalendarView from './components/CalendarView.vue'
import OnThisDayView from './components/OnThisDayView.vue'
import EditorView from './components/EditorView.vue'
import SearchView from './components/SearchView.vue'
import StatsView from './components/StatsView.vue'
import SettingsView from './components/SettingsView.vue'

type View = 'timeline' | 'calendar' | 'onthisday' | 'search' | 'stats' | 'settings'

const view = ref<View>('timeline')
const entries = ref<Entry[]>([])
const onThisDayEntries = ref<Entry[]>([])
const editorDate = ref<string | null>(null)

const todayIso = (): string => {
	const t = new Date()
	return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`
}

const reload = async () => {
	entries.value = await api.entries()
	onThisDayEntries.value = await api.onThisDay()
}

const openEntry = (entry: Entry) => {
	editorDate.value = entry.date
}

const openDate = (date: string) => {
	editorDate.value = date
}

onMounted(async () => {
	await reload()
	const token = document.getElementById('tagebuch')?.dataset.token ?? ''
	if (token !== '') {
		try {
			const info = await api.tokenInfo(token)
			editorDate.value = info.date
		} catch {
			editorDate.value = todayIso()
		}
	}
})
</script>

<template>
	<div class="tagebuch-app">
		<nav class="tabs">
			<button type="button" :class="{ active: view === 'timeline' }" @click="view = 'timeline'">Timeline</button>
			<button type="button" :class="{ active: view === 'calendar' }" @click="view = 'calendar'">Kalender</button>
			<button type="button" :class="{ active: view === 'onthisday' }" @click="view = 'onthisday'">An diesem Tag</button>
			<button type="button" :class="{ active: view === 'search' }" @click="view = 'search'">Suche</button>
			<button type="button" :class="{ active: view === 'stats' }" @click="view = 'stats'">Statistik</button>
			<button type="button" :class="{ active: view === 'settings' }" @click="view = 'settings'">Einstellungen</button>
			<button type="button" class="new" @click="openDate(todayIso())">+ Neu</button>
		</nav>

		<main>
			<EditorView
				v-if="editorDate !== null"
				:date="editorDate"
				@saved="reload"
				@closed="editorDate = null"
			/>
			<template v-else>
				<EntryList
					v-if="view === 'timeline'"
					:entries="entries"
					empty-text="Noch keine Einträge – leg oben mit „+ Neu“ los."
					@open="openEntry"
				/>
				<CalendarView v-else-if="view === 'calendar'" :entries="entries" @select="openDate" />
				<OnThisDayView
					v-else-if="view === 'onthisday'"
					:entries="onThisDayEntries"
					@open="openEntry"
				/>
				<SearchView v-else-if="view === 'search'" @open="openEntry" />
				<StatsView v-else-if="view === 'stats'" />
				<SettingsView v-else-if="view === 'settings'" />
			</template>
		</main>
	</div>
</template>

<style scoped>
.tagebuch-app {
	padding: 16px;
	max-width: 900px;
}
.tabs {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-bottom: 16px;
}
.tabs button {
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	border-radius: var(--border-radius-pill);
	padding: 6px 14px;
	cursor: pointer;
}
.tabs button.active {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border-color: var(--color-primary-element);
}
.tabs .new {
	margin-left: auto;
	font-weight: bold;
}
</style>
