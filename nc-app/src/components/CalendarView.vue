<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Entry } from '../api'

const props = defineProps<{ entries: Entry[] }>()
const emit = defineEmits<{ select: [date: string] }>()

const today = new Date()
const year = ref(today.getFullYear())
const month = ref(today.getMonth())

const monthName = computed(() =>
	new Date(year.value, month.value, 1).toLocaleDateString('de-DE', { month: 'long', year: 'numeric' }))

const marks = computed(() => new Set(props.entries.map(entry => entry.date)))

const weeks = computed(() => {
	const first = new Date(year.value, month.value, 1)
	const startOffset = (first.getDay() + 6) % 7
	const daysInMonth = new Date(year.value, month.value + 1, 0).getDate()
	const cells: (number | null)[] = []
	for (let i = 0; i < startOffset; i++) cells.push(null)
	for (let d = 1; d <= daysInMonth; d++) cells.push(d)
	while (cells.length % 7 !== 0) cells.push(null)
	const result: (number | null)[][] = []
	for (let i = 0; i < cells.length; i += 7) result.push(cells.slice(i, i + 7))
	return result
})

const iso = (day: number): string =>
	`${year.value}-${String(month.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`

const shift = (delta: number) => {
	month.value += delta
	if (month.value < 0) { month.value = 11; year.value-- }
	if (month.value > 11) { month.value = 0; year.value++ }
}

const isToday = (day: number): boolean => {
	const t = new Date()
	return day === t.getDate() && month.value === t.getMonth() && year.value === t.getFullYear()
}
</script>

<template>
	<div class="calendar">
		<div class="calendar-header">
			<button type="button" @click="shift(-1)">‹</button>
			<strong>{{ monthName }}</strong>
			<button type="button" @click="shift(1)">›</button>
		</div>
		<div class="calendar-grid">
			<span v-for="d in ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']" :key="d" class="weekday">{{ d }}</span>
			<template v-for="(week, wi) in weeks" :key="wi">
				<template v-for="(day, di) in week" :key="di">
					<span v-if="day === null" class="cell empty" />
					<button
						v-else
						type="button"
						class="cell day"
						:class="{
							today: isToday(day),
							'has-entry': marks.has(iso(day)),
						}"
						:aria-label="iso(day) + (marks.has(iso(day)) ? ', mit Eintrag' : '')"
						@click="emit('select', iso(day))"
					>
						{{ day }}
					</button>
				</template>
			</template>
		</div>
	</div>
</template>

<style scoped>
.calendar { width: 100%; min-width: 0; max-width: 100%; box-sizing: border-box; overflow: hidden; }
.calendar-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}
.calendar-grid {
	display: grid;
	grid-template-columns: repeat(7, minmax(0, 1fr));
	gap: clamp(1px, 0.7vw, 4px);
	width: 100%;
	min-width: 0;
	box-sizing: border-box;
}
.weekday {
	text-align: center;
	font-size: 0.8em;
	color: var(--color-text-maxcontrast);
}
.cell {
	width: 100%;
	min-width: 0;
	min-height: 0;
	aspect-ratio: 1;
	font-size: clamp(0.75rem, 3.5vw, 1rem);
	line-height: 1;
	padding: 0;
	box-sizing: border-box;
	border: none;
	background: none;
	border-radius: 10px;
}
.day {
	cursor: pointer;
}
.day:hover {
	background: var(--color-background-hover);
}
.day.today {
	border: 2px solid var(--color-primary-element);
	font-weight: bold;
}
.day.has-entry {
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #fff);
	font-weight: 700;
	text-decoration: none;
}
.day.has-entry:hover { filter: brightness(.91); }
.day.today.has-entry { outline: 2px solid var(--color-main-text, #172b43); outline-offset: -4px; }
.calendar-header button { min-width: 36px; min-height: 36px; }
.calendar-header strong { text-align: center; min-width: 0; }
@media (max-width: 420px) { .calendar-grid { gap: 1px; } .calendar-header { gap: 8px; } }
</style>
