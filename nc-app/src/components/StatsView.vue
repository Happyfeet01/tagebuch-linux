<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, type Stats } from '../api'
import { decryptPayload, isEncryptionEnabled, isUnlocked } from '../crypto'

const stats = ref<Stats | null>(null)
onMounted(async () => {
	stats.value = await api.stats()
	if (stats.value && isEncryptionEnabled() && isUnlocked()) {
		const entries = await api.entries()
		let words = 0
		for (const e of entries) {
			if (e.encrypted === 1) {
				const p = await decryptPayload(e.body)
				if (p) words += (p.heading + ' ' + p.body).split(/\s+/).filter(Boolean).length
			} else {
				words += (e.heading + ' ' + e.body).split(/\s+/).filter(Boolean).length
			}
		}
		stats.value = { ...stats.value, total_words: words }
	}
})
</script>

<template>
	<div v-if="stats" class="stats">
		<p>Einträge gesamt: <strong>{{ stats.total_entries }}</strong></p>
		<p>Tage mit Eintrag: <strong>{{ stats.total_days }}</strong></p>
		<p>Wörter gesamt: <strong>{{ stats.total_words }}</strong></p>
		<p>Aktuelle Serie: <strong>{{ stats.current_streak }} Tage</strong></p>
		<p>Längste Serie: <strong>{{ stats.longest_streak }} Tage</strong></p>
		<p v-if="stats.average_rating !== null">Ø Bewertung: <strong>{{ stats.average_rating }} / 5</strong></p>
		<h3>Pro Monat</h3>
		<ul>
			<li v-for="(count, month) in stats.per_month" :key="month">{{ month }}: {{ count }}</li>
		</ul>
	</div>
</template>

<style scoped>
.stats {
	max-width: 480px;
}
</style>
