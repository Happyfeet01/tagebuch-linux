// GeoNames via all-the-cities, CC BY 4.0. Keep the index on the Nextcloud server.
const cities = require('all-the-cities')
const fs = require('node:fs')
const rows = cities.map(city => [city.loc.coordinates[1], city.loc.coordinates[0], city.name])
fs.mkdirSync('js', { recursive: true })
fs.writeFileSync('js/places-3.1.0.json', JSON.stringify(rows))
