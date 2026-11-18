<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type Settings } from '../api'

const settings = ref<Settings | null>(null)
const message = ref('')

onMounted(async () => {
	settings.value = await api.settings()
})

const save = async () => {
	if (!settings.value) return
	message.value = ''
	try {
		await api.saveSettings({
			reminder_enabled: settings.value.reminder_enabled,
			theme: settings.value.theme,
			accent: settings.value.accent,
			font: settings.value.font,
		})
		message.value = 'Gespeichert.'
	} catch {
		message.value = 'Fehler beim Speichern.'
	}
}
</script>

<template>
	<div v-if="settings" class="settings">
		<h2>Einstellungen</h2>
		<p v-if="settings.email">
			Erinnerungs-Mails gehen an deine Nextcloud-E-Mail: <strong>{{ settings.email }}</strong>
			(Versand über die SMTP-Einstellungen der Nextcloud-Systemeinstellungen).
		</p>
		<p v-else class="warn">
			Am Nextcloud-Benutzer ist keine E-Mail-Adresse hinterlegt – bitte in den
			Nextcloud-Nutzereinstellungen eintragen.
		</p>
		<label class="row">
			<input v-model="settings.reminder_enabled" type="checkbox" true-value="1" false-value="0">
			Tägliche Schreib-Erinnerung per E-Mail (täglich 20:00 Uhr)
		</label>
		<label class="row">
			Design:
			<select v-model="settings.theme">
				<option value="system">System</option>
				<option value="light">Hell</option>
				<option value="dark">Dunkel</option>
			</select>
		</label>
		<button type="button" @click="save">Speichern</button>
		<p v-if="message" class="message">{{ message }}</p>
	</div>
</template>

<style scoped>
.settings {
	max-width: 560px;
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.row {
	display: flex;
	gap: 10px;
	align-items: center;
}
.warn {
	color: var(--color-warning);
}
.message {
	color: var(--color-success);
}
</style>
