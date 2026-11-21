type Town = [number, number, string]
export function buildPlaceLookup(towns: Town[]) {
	const bands = new Map<number, Town[]>()
	for (const town of towns) {
		const band = Math.floor(town[0])
		if (!bands.has(band)) bands.set(band, [])
		bands.get(band)!.push(town)
	}
	return (lat: number, lon: number) => {
		const rad = Math.PI / 180
		const cos = Math.cos(lat * rad)
		let best = Math.sin(15 / (2 * 6371)) ** 2
		let name = ''
		for (let band = Math.floor(lat - 0.14); band <= Math.floor(lat + 0.14); band++) {
			for (const [y, x, town] of bands.get(band) || []) {
				const h = Math.sin((lat - y) * rad / 2) ** 2 + cos * Math.cos(y * rad) * Math.sin((lon - x) * rad / 2) ** 2
				if (h < best) { best = h; name = town }
			}
		}
		return name
	}
}
