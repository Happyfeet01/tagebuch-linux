<script setup lang="ts">
import { computed, ref } from 'vue'

const fields = defineModel<Record<string, unknown>>({ required: true })
const scales = {
	Stimmung: ['Sehr schlecht', 'Schlecht', 'Mittel', 'Gut', 'Sehr gut'],
	Energie: ['Sehr niedrig', 'Niedrig', 'Mittel', 'Hoch', 'Sehr hoch'],
}
const feelings = ['Zufrieden', 'Entspannt', 'Angespannt', 'Erleichtert', 'Traurig', 'Fröhlich', 'Müde']
const selectedFeelings = computed(() => typeof fields.value.Gefühle === 'string'
	? fields.value.Gefühle.split(',').map(value => value.trim()).filter(Boolean) : [])
const allFeelings = computed(() => [...new Set([...feelings, ...selectedFeelings.value])])
const sleep = computed(() => {
	const value = fields.value.Schlafdauer
	return typeof value === 'number' && value >= 0 && value <= 24 ? value : ''
})
const sleepInput = ref<HTMLInputElement>()
const extra = ref<HTMLDetailsElement>()
function valid() {
	if (sleepInput.value && !sleepInput.value.validity.valid) {
		if (extra.value) extra.value.open = true
		sleepInput.value.reportValidity()
		return false
	}
	return true
}
defineExpose({ valid })
const name = ref('')
const value = ref('')
const custom = computed(() => Object.entries(fields.value).filter(([key]) =>
	!Object.hasOwn(scales, key) && !(key === 'Gefühle' && typeof fields.value.Gefühle === 'string')
	&& !(key === 'Schlafdauer' && sleep.value !== '')))

function update(key: string, value: string | number) {
	const next = { ...fields.value }
	if (value === '') delete next[key]
	else next[key] = value
	fields.value = next
}
function toggleFeeling(feeling: string, enabled: boolean) {
	const values = new Set(selectedFeelings.value)
	if (enabled) values.add(feeling)
	else values.delete(feeling)
	update('Gefühle', [...values].join(', '))
}
function setSleep(input: HTMLInputElement) {
	if (!input.validity.valid) { input.reportValidity(); return }
	update('Schlafdauer', input.value === '' ? '' : input.valueAsNumber)
}
function addCustom() {
	const raw = value.value.trim()
	if (!name.value.trim() || !raw) return
	update(name.value.trim(), /^-?\d+(?:[.,]\d+)?$/.test(raw) ? Number(raw.replace(',', '.')) : raw)
	name.value = ''
	value.value = ''
}
</script>

<template>
	<div class="tracker-fields">
		<label>Stimmung
			<select :value="fields.Stimmung ?? ''" @change="update('Stimmung', ($event.target as HTMLSelectElement).value)">
				<option value="">Keine Angabe</option>
				<option v-if="fields.Stimmung != null && !scales.Stimmung.includes(String(fields.Stimmung))" :value="fields.Stimmung">{{ fields.Stimmung }}</option>
				<option v-for="item in scales.Stimmung" :key="item">{{ item }}</option>
			</select>
		</label>
		<details ref="extra">
			<summary>Weitere Angaben</summary>
			<div class="extra-fields">
				<label>Energie
					<select :value="fields.Energie ?? ''" @change="update('Energie', ($event.target as HTMLSelectElement).value)">
						<option value="">Keine Angabe</option>
						<option v-if="fields.Energie != null && !scales.Energie.includes(String(fields.Energie))" :value="fields.Energie">{{ fields.Energie }}</option>
						<option v-for="item in scales.Energie" :key="item">{{ item }}</option>
					</select>
				</label>
				<fieldset><legend>Gefühle</legend>
					<label v-for="item in allFeelings" :key="item">
						<input type="checkbox" :checked="selectedFeelings.includes(item)" @change="toggleFeeling(item, ($event.target as HTMLInputElement).checked)"> {{ item }}
					</label>
				</fieldset>
				<label>Schlafdauer in Stunden
					<input ref="sleepInput" type="number" inputmode="decimal" min="0" max="24" step="any" :value="sleep" @input="setSleep($event.target as HTMLInputElement)">
				</label>
				<div class="custom">
					<label>Eigenes Feld <input v-model="name" placeholder="z. B. Schritte"></label>
					<label>Wert <input v-model="value"></label>
					<button type="button" :disabled="!name.trim() || !value.trim()" @click="addCustom">Wert übernehmen</button>
				</div>
				<div v-if="custom.length" class="custom">
					<button v-for="[key, stored] in custom" :key="key" type="button" :aria-label="key + ' entfernen'" @click="update(key, '')">{{ key }} · {{ stored }} ×</button>
				</div>
			</div>
		</details>
	</div>
</template>

<style scoped>
.tracker-fields, .extra-fields { display: grid; gap: 10px; }
.extra-fields { margin-top: 8px; }
fieldset, .custom { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; min-width: 0; }
label { max-width: 100%; }
summary { cursor: pointer; }
</style>
