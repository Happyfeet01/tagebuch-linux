import assert from 'node:assert/strict'
import { test } from 'node:test'
import { availableImportTime, importKey, parseDiarium } from '../../src/diariumImport.ts'

test('Preserves distinct memories within the same second and skips exact repeats', () => {
	const source = { date: '2026-11-21T08:45:12.1234567', text: 'Erinnerung' }
	const entries = parseDiarium(JSON.stringify([source, { ...source, text: 'Zweite Erinnerung' },
		{ ...source, date: '2026-11-21T08:45:46' }, source]))
	const occupied = new Map()
	const inserted = []
	for (const row of entries) {
		const time = availableImportTime(row, occupied)
		if (time === null) continue
		occupied.set(importKey({ ...row, time }), row)
		inserted.push(time)
	}
	assert.deepEqual(inserted, ['08:45:12.1234567', '08:45:12.1234568', '08:45:46'])
	assert.ok(entries.every(row => availableImportTime(row, occupied) === null))
})
