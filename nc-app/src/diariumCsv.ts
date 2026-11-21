import type { Entry } from './api'
import { decryptPayload } from './crypto'

const csvField = (value: string | number | null | undefined): string =>
	'"' + String(value ?? '').replaceAll('"', '""') + '"'

export async function diariumCsv(entries: Entry[]): Promise<string> {
	const rows = ['date,text,title,rating,tags,latitude,longitude']
	for (const entry of entries) {
		const content = entry.encrypted ? await decryptPayload(entry.body) : entry
		if (!content) {
			throw new Error('Ein Eintrag konnte nicht entschlüsselt werden. Bitte entsperre den Tresor in den Einstellungen.')
		}
		const time = entry.time?.length === 5 ? entry.time + ':00' : entry.time
		const date = time ? entry.date + 'T' + time : entry.date
		if (content.tags.some(tag => tag.includes('|'))) {
			throw new Error('Ein Tag vom ' + entry.date + ' enthält „|“. Diarium verwendet dieses Zeichen zum Trennen von Tags. Bitte passe den Tag vor dem Export an.')
		}
		rows.push([date, content.body, content.heading, entry.rating, content.tags.join('|'),
			content.location?.lat, content.location?.lon].map(csvField).join(','))
	}
	return rows.join('\r\n') + '\r\n'
}
