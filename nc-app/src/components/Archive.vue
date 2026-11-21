<script setup lang="ts">
import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
const endpoint = generateOcsUrl('/apps/tagebuch/api/archive')
const busy = ref(false), status = ref('')
const file = ref<File>()
async function download() {
	busy.value = true
	try {
		const response = await axios.get(endpoint, { responseType: 'blob' })
		const url = URL.createObjectURL(response.data)
		const a = document.createElement('a')
		a.href = url
		a.download = 'Tagebuch-' + new Date().toISOString().slice(0, 10) + '.zip'
		a.click()
		setTimeout(() => URL.revokeObjectURL(url), 60000)
		status.value = 'Sicherung zum Download bereit.'
	} catch { status.value = 'Sicherung fehlgeschlagen. Bei älteren Tresoren zuerst im ursprünglichen Browser entsperren.' }
	finally { busy.value = false }
}
async function restore() {
	if (!file.value) return
	busy.value = true
	try {
		const form = new FormData()
		form.append('file', file.value)
		await axios.post(endpoint, form)
		window.location.reload()
	} catch (e) {
		status.value = (e as { response?: { data?: { ocs?: { data?: { error?: string } } } } }).response?.data?.ocs?.data?.error || 'Wiederherstellung fehlgeschlagen.'
	} finally { busy.value = false }
}
</script>
<template><details><summary>Vollständige Tagebuchsicherung</summary>
	<p>ZIP mit Einträgen, Anhängen, Vorlagen, Einstellungen und Online-Entwürfen. Verschlüsselte Inhalte behalten ihr Passwort. Unverschlüsselte Inhalte sind im Archiv lesbar.</p>
	<button type="button" :disabled="busy" @click="download">ZIP herunterladen</button>
	<p>In einem leeren Tagebuch wiederherstellen</p>
	<input type="file" accept=".zip" :disabled="busy" @change="file = ($event.target as HTMLInputElement).files?.[0]">
	<button type="button" :disabled="busy || !file" @click="restore">Tagebuch wiederherstellen</button>
	<p role="status">{{ status }}</p>
</details></template>
