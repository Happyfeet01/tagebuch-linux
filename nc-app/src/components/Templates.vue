<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../api'
const names = ref<string[]>([])
const name = ref('')
const body = ref('')
const error = ref('')
const busy = ref(false)
async function select(value: string) {
	if (busy.value) return
	busy.value = true
	try {
		const text = value ? (await api.template(value)).body : ''
		name.value = value
		body.value = text
		error.value = ''
	} catch { error.value = 'Vorlage nicht lesbar.' } finally { busy.value = false }
}
async function save() {
	busy.value = true
	try {
		const result = await api.saveTemplate(name.value.trim(), body.value)
		name.value = result.name
		names.value = await api.templates()
		error.value = 'Vorlage gespeichert.'
	} catch { error.value = 'Speichern fehlgeschlagen.' } finally { busy.value = false }
}
onMounted(async () => { try { names.value = await api.templates() } catch { error.value = 'Vorlagen nicht verfügbar.' } })
</script>
<template><details><summary>Schreibvorlagen verwalten</summary>
	<fieldset :disabled="busy"><select aria-label="Vorlage öffnen" @change="select(($event.target as HTMLSelectElement).value)">
		<option value="">Neue Vorlage</option><option v-for="n in names" :key="n">{{ n }}</option>
	</select>
	<label>Name <input v-model="name"></label><label>Vorlagentext <textarea v-model="body" rows="16" /></label>
	<button type="button" :disabled="!name.trim()" @click="save">Vorlage speichern</button></fieldset>
	<p role="status">{{ error }}</p>
</details></template>

<style scoped>
fieldset, label { display: grid; gap: 8px; min-width: 0; }
fieldset { padding: 12px 0; width: 100%; }
input, textarea, select { box-sizing: border-box; width: 100%; max-width: none; font: inherit; }
input, textarea, select { padding: 12px; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-main-background); color: var(--color-main-text); }
textarea { min-height: 24rem; width: 100%; resize: vertical; line-height: 1.6; }
label { width: 100%; font-weight: 600; }
input:focus-visible, textarea:focus-visible, select:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: 2px; }
</style>
