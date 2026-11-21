import type { EntryLocation } from './api'

export function point(location?: EntryLocation | null): [number, number] | null {
	const { lat, lon } = location || {}
	if (typeof lat !== 'number' || typeof lon !== 'number' || !Number.isFinite(lat) || !Number.isFinite(lon)
		|| Math.abs(lat) > 90 || Math.abs(lon) > 180) return null
	return [lat, lon]
}

export function locationLabel(location: EntryLocation | null) {
	return location?.name?.trim() || point(location)?.map(n => n.toFixed(5)).join(', ') || ''
}

export function withinTenKm(a: [number, number], b: [number, number]) {
	const rad = Math.PI / 180
	const h = Math.sin((a[0] - b[0]) * rad / 2) ** 2 + Math.cos(a[0] * rad) * Math.cos(b[0] * rad)
		* Math.sin((a[1] - b[1]) * rad / 2) ** 2
	return h <= Math.sin(10 / (2 * 6371)) ** 2
}
