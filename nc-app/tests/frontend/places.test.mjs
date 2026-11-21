import assert from 'node:assert/strict'
import { test } from 'node:test'
import { locationLabel, point, withinTenKm } from '../../src/places.ts'

test('displays GPS-only locations and ignores missing or invalid coordinates', () => {
	assert.equal(locationLabel({ lat: 0, lon: 10 }), '0.00000, 10.00000')
	assert.equal(locationLabel({}), '')
	assert.equal(point({ lat: 45, lon: 190 }), null)
	assert.equal(locationLabel({ name: ' Marktplatz ' }), 'Marktplatz')
})

test('filters a 10 km radius locally, including across the date line', () => {
	assert.ok(withinTenKm([0, 179.98], [0, -179.99]))
	assert.equal(withinTenKm([0, 0], [0, 0.2]), false)
})
