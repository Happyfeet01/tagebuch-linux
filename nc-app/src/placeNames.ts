import { shallowRef } from 'vue'
import { generateFilePath } from '@nextcloud/router'
import type { EntryLocation } from './api'
import { locationLabel, point } from './places'
import { buildPlaceLookup } from './nearestPlace'

const lookup = shallowRef<ReturnType<typeof buildPlaceLookup>>()
const names = new Map<string, string>()
let loading: Promise<void> | undefined
export function approximatePlace(location?: EntryLocation | null) {
	const coords = point(location)
	if (!coords) return ''
	if (!lookup.value) {
		loading ??= fetch(generateFilePath('tagebuch', 'js', 'places-3.1.0.json'))
			.then(async response => {
				if (!response.ok) throw new Error()
				lookup.value = buildPlaceLookup(await response.json())
			}).catch(() => { loading = undefined })
		return ''
	}
	const key = coords.join(',')
	if (!names.has(key)) names.set(key, lookup.value(...coords))
	return names.get(key)!
}
export function displayPlace(location: EntryLocation | null) {
	if (location?.name?.trim()) return location.name.trim()
	const name = approximatePlace(location)
	return name ? 'Nähe ' + name : locationLabel(location)
}
