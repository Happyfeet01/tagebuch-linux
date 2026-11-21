<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type Settings } from '../api'
import { hasEncryptionSetup, isEncryptionEnabled, isUnlocked, lockEncryption, setupEncryption, unlockEncryption } from '../crypto'
import DiariumImport from './DiariumImport.vue'

const emit = defineEmits<{ imported: [] }>()
const settings = ref<Settings | null>(null)
const message = ref('')
const encPassword = ref('')
const encMessage = ref('')
const encState = ref<'setup' | 'locked' | 'unlocked'>('setup')

const refreshEncState = () => {
	if (!isEncryptionEnabled()) encState.value = 'setup'
	else if (!hasEncryptionSetup()) encState.value = 'setup'
	else encState.value = isUnlocked() ? 'unlocked' : 'locked'
}

const onEncAction = async () => {
	encMessage.value = ''
	try {
		if (encState.value === 'setup') {
			if (encPassword.value.length < 8) {
				encMessage.value = 'Passwort mindestens 8 Zeichen.'
				return
			}
			await setupEncryption(encPassword.value)
			encMessage.value = 'Verschlüsselung aktiviert. Neue Einträge werden clientseitig verschlüsselt.'
		} else if (encState.value === 'locked') {
			if (!(await unlockEncryption(encPassword.value))) {
				encMessage.value = 'Passwort falsch.'
				return
			}
			encMessage.value = 'Entsperrt.'
		} else {
			lockEncryption()
			encMessage.value = 'Gesperrt.'
		}
		encPassword.value = ''
	} catch (e) {
		encMessage.value = 'Fehler: ' + (e as Error).message
	}
	refreshEncState()
}

onMounted(async () => {
	settings.value = await api.settings()
	refreshEncState()
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
			photo_folder: settings.value.photo_folder,
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
		<label class="row">
			Foto-Ordner für „An diesem Tag“:
			<input v-model="settings.photo_folder" type="text" placeholder="z. B. Fotos" class="photo-folder">
		</label>
		<p class="hint">
			Fotos/Videos aus diesem Ordner (Struktur <code>&lt;Ordner&gt;/&lt;Jahr&gt;/&lt;Monat&gt;/</code>,
			optional mit Tages-Unterordner <code>&lt;Tag&gt;</code>) werden in der Ansicht
			„An diesem Tag“ für frühere Jahre angezeigt.
		</p>
		<button type="button" @click="save">Speichern</button>
		<p v-if="message" class="message">{{ message }}</p>

		<DiariumImport @imported="emit('imported')" />

		<h3>Verschlüsselung (clientseitig, AES-GCM)</h3>
		<p v-if="encState === 'setup'">
			Aktiviere die Verschlüsselung mit einem App-Passwort. Der Schlüssel wird per PBKDF2 aus dem
			Passwort abgeleitet und nur in diesem Browser gehalten – die Nextcloud-Datenbank enthält dann
			nur Ciphertext. <strong>Ohne Passwort sind verschlüsselte Einträge nicht wiederherstellbar.</strong>
		</p>
		<p v-else-if="encState === 'locked'">Verschlüsselung ist eingerichtet, aber gesperrt. Zum Entsperren App-Passwort eingeben.</p>
		<p v-else>Verschlüsselung aktiv und entsperrt. Neue Einträge werden verschlüsselt gespeichert.</p>
		<div class="row">
			<input v-model="encPassword" type="password" :placeholder="encState === 'setup' ? 'Neues App-Passwort (min. 8 Zeichen)' : 'App-Passwort'">
			<button type="button" @click="onEncAction">
				{{ encState === 'setup' ? 'Aktivieren' : encState === 'locked' ? 'Entsperren' : 'Sperren' }}
			</button>
		</div>
		<p v-if="encMessage" class="message">{{ encMessage }}</p>
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
.hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
	margin: 0;
}
.photo-folder {
	flex: 1;
}
.message {
	color: var(--color-success);
}
</style>
