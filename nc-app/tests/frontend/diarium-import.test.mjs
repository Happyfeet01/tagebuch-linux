import assert from 'node:assert/strict'
import { test } from 'node:test'
import { importSlot, importKey, parseDiarium } from '../../src/diariumImport.ts'

test('Preserves distinct memories within the same second and skips exact repeats', () => {
	const source = { date: '2026-11-21T08:45:12.1234567', text: 'Erinnerung' }
	const entries = parseDiarium(JSON.stringify([source, { ...source, text: 'Zweite Erinnerung' },
		{ ...source, date: '2026-11-21T08:45:46' }, source]))
	const occupied = new Map()
	const inserted = []
	for (const row of entries) {
		const { time } = importSlot(row, occupied)
		if (time === null) continue
		occupied.set(importKey({ ...row, time }), row)
		inserted.push(time)
	}
	assert.deepEqual(inserted, ['08:45:12.1234567', '08:45:12.1234568', '08:45:46'])
	assert.ok(entries.every(row => importSlot(row, occupied).time === null))
})

test('repairs a minute-only import without losing attachments or its saved place name', () => {
	const [row] = parseDiarium(JSON.stringify({ date: '2026-11-21T08:45:12', text: 'Text', location: [12, 34] }))
	const old = { ...row, id: 7, time: '08:45', attachments: ['file'], location: { name: 'Ort' } }
	const occupied = new Map([[importKey(old), old]])
	const { repair } = importSlot(row, occupied)
	assert.deepEqual(repair, { ...old, location: { name: 'Ort', lat: 12, lon: 34 } })
	occupied.set(importKey(old), repair)
	assert.deepEqual(importSlot(row, occupied), { time: null })
})
