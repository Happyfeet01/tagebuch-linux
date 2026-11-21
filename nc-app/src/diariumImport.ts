import type { Entry } from './api'

function plainText(html: string): string {
	const template = document.createElement('template')
	template.innerHTML = html
	const content = template.content
	content.querySelectorAll('script,style,noscript').forEach(node => node.remove())
	content.querySelectorAll('br').forEach(node => node.replaceWith('\n'))
	content.querySelectorAll('p,div,li,h1,h2,h3,h4,h5,h6,blockquote,tr').forEach(node => node.append('\n'))
	return (content.textContent ?? '').trim()
}

function parseRow(value: unknown): Entry {
	if (!value || typeof value !== 'object') throw new Error()
	const row = value as Record<string, unknown>
	if (typeof row.date !== 'string') throw new Error()
	const match = /^(\d{4}-\d{2}-\d{2})(?:T([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d(?:\.\d+)?)?(?:Z|[+-]\d\d:\d\d)?)?$/.exec(row.date)
	if (!match || new Date(match[1] + 'T00:00:00Z').toISOString().slice(0, 10) !== match[1]) throw new Error()
	if (!('heading' in row || 'html' in row || 'text' in row)) throw new Error()
	const heading = row.heading ?? ''
	const html = row.html ?? ''
	const text = row.text ?? ''
	const tags = row.tags ?? []
	if (typeof heading !== 'string' || typeof html !== 'string' || typeof text !== 'string'
		|| !Array.isArray(tags) || !tags.every(tag => typeof tag === 'string')) throw new Error()
	const rating = row.rating ?? 0
	if (typeof rating !== 'number' || !Number.isInteger(rating) || rating < 0 || rating > 5) throw new Error()
	let location = null
	if (row.location != null) {
		if (!Array.isArray(row.location)) throw new Error()
		if (row.location.length) {
			const [lat, lon] = row.location
			if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) throw new Error()
			location = { lat, lon }
		}
	}
	return { date: match[1], time: match[2] ? row.date.slice(11).replace(/(?:Z|[+-]\d\d:\d\d)$/, '').replace(/(\.\d{7})\d+$/, '$1') : '00:00',
		heading, body: html ? plainText(html) : text, tags, rating: rating || null, location,
		trackers: {}, attachments: [] }
}

export function parseDiarium(json: string): Entry[] {
	const data: unknown = JSON.parse(json.replace(/^\uFEFF/, ''))
	return parseDiariumRows(Array.isArray(data) ? data : [data])
}

export function parseDiariumRows(rows: unknown[]): Entry[] {
	return rows.map((row, index) => {
		try {
			return parseRow(row)
		} catch {
			throw new Error('Eintrag ' + (index + 1) + ' hat ein unbekanntes Format.')
		}
	})
}

export const importKey = (entry: Entry): string => entry.date + 'T' + (entry.time || '00:00')

export function importSlot(row: Entry, occupied: Map<string, Entry>): { time: string | null, repair?: Entry } {
	const original = importKey(row)
	const minute = original.slice(0, 16)
	function existingSlot(entry?: Entry) {
		if (!entry || entry.body !== row.body || entry.heading !== row.heading || entry.rating !== row.rating
			|| JSON.stringify(entry.tags) !== JSON.stringify(row.tags)) return
		const old = entry.location
		const incoming = row.location
		if (old?.lat != null && incoming?.lat != null && (old.lat !== incoming.lat || old.lon !== incoming.lon)) return
		const addPoint = incoming?.lat != null && incoming.lon != null && old?.lat == null && old?.lon == null
		const addName = incoming?.name && !old?.name
		if (!addPoint && !addName) return { time: null }
		const location = { ...old }
		if (addPoint) { location.lat = incoming.lat; location.lon = incoming.lon }
		if (addName) location.name = incoming.name
		return { time: entry.time, repair: { ...entry, location } }
	}
	// Older versions stored only the minute.
	const legacy = original !== minute ? existingSlot(occupied.get(minute)) : undefined
	if (legacy) return legacy
	let key = original
	let slot = row.time!
	let offset = 0
	const [hms, fraction = ''] = slot.split('.')
	while (occupied.has(key)) {
		const match = existingSlot(occupied.get(key))
		if (match) return match
		offset++
		if (offset >= 10000000) throw new Error('Kein freier Zeitstempel in dieser Sekunde.')
		const part = (Number(fraction.padEnd(7, '0')) + offset) % 10000000
		slot = (hms.length === 5 ? hms + ':00' : hms) + '.' + String(part).padStart(7, '0')
		key = row.date + 'T' + slot
	}
	return { time: slot }
}
