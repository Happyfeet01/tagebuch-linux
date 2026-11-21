import workerUrl from 'sql.js/dist/worker.sql-asm.js?url'
import { diaryQuery } from './diaryQuery'
import { parseDiariumRows } from './diariumImport'

type Result = { values: unknown[][] }

export async function readDiaryBackup(file: File) {
	const header = await file.slice(0, 16).text()
	if (header !== 'SQLite format 3\0') throw new Error('Diese Datei ist keine lesbare SQLite-Sicherung von Diarium.')
	const buffer = await file.arrayBuffer()
	const worker = new Worker(workerUrl)
	try {
		const results = await new Promise<Result[]>((resolve, reject) => {
			worker.onerror = () => reject(new Error('Die SQLite-Sicherung konnte im Browser nicht geöffnet werden.'))
			worker.onmessage = ({ data }) => {
				if (data.error) reject(new Error('Die Sicherung ist beschädigt oder verwendet ein unbekanntes Diarium-Format.'))
				else if (data.ready) worker.postMessage({ action: 'exec', sql: 'PRAGMA query_only=ON; PRAGMA trusted_schema=OFF;' + diaryQuery })
				else resolve(data.results)
			}
			worker.postMessage({ action: 'open', buffer }, [buffer])
		})
		return parseDiariumRows((results[0]?.values ?? []).map(([date, heading, html, rating, lat, lon, tags]) => ({
			date, heading, html, rating, location: lat === 0 && lon === 0 ? null : [lat, lon],
			tags: JSON.parse(String(tags)),
		})))
	} finally {
		worker.terminate()
	}
}
