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
	return { date: match[1], time: match[2] ? match[2] + ':' + match[3] : '00:00',
		heading, body: html ? plainText(html) : text, tags, rating: rating || null, location,
		trackers: {}, attachments: [] }
}

export function parseDiarium(json: string): Entry[] {
	const data: unknown = JSON.parse(json.replace(/^\uFEFF/, ''))
	return (Array.isArray(data) ? data : [data]).map((row, index) => {
		try {
			return parseRow(row)
		} catch {
			throw new Error('Eintrag ' + (index + 1) + ' hat ein unbekanntes Format.')
		}
	})
}

export const importKey = (entry: Entry): string => entry.date + 'T' + (entry.time || '00:00')
