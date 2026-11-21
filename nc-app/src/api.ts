import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { encryptFile, isEncryptionEnabled, isUnlocked } from './crypto'

type Ocs<T> = { ocs: { data: T } }

const unwrap = <T>(p: Promise<{ data: Ocs<T> }>): Promise<T> => p.then((r) => r.data.ocs.data)

export interface EntryLocation {
	name?: string
	lat?: number
	lon?: number
}

export interface Entry {
	id?: number
	date: string
	time: string | null
	heading: string
	body: string
	rating: number | null
	tags: string[]
	trackers: Record<string, unknown>
	location: EntryLocation | null
	attachments: string[]
	encrypted?: number
	created?: string | null
	modified?: string | null
}

export interface Settings {
	reminder_timezone?: string
	reminder_zone: string
	reminder_sent_date: string
	reminder_enabled: string
	theme: string
	accent: string
	font: string
	photo_folder: string
	email: string
}

export interface Photo {
	etag?: string
	id?: number
	path: string
	name: string
	year: string
}

export interface Stats {
	total_entries: number
	total_days: number
	total_words: number
	current_streak: number
	longest_streak: number
	average_rating: number | null
	per_month: Record<string, number>
}

export const api = {
	dayContext: (date: string, calendar: string) =>
		unwrap<{ calendars: { id: string, name: string }[], events: string[], photos: Photo[] }>(
			axios.get(generateOcsUrl('/apps/tagebuch/api/day-context'), { params: { date, calendar, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone } })),
	entries: (from?: string, to?: string) =>
		unwrap<Entry[]>(axios.get(generateOcsUrl('/apps/tagebuch/api/entries'), { params: { from, to } })),
	day: (date: string) => unwrap<Entry[]>(axios.get(generateOcsUrl('/apps/tagebuch/api/day/' + date))),
	saveEntry: (e: Partial<Entry> & { date: string, expectedModified?: string | null }) =>
		unwrap<Entry>(axios.post(generateOcsUrl('/apps/tagebuch/api/entry'), e)),
	deleteEntry: (date: string, time?: string) =>
		unwrap<{ deleted: boolean }>(axios.delete(generateOcsUrl('/apps/tagebuch/api/entry'), { params: { date, time } })),
	search: (q: string, tag?: string, since?: string, until?: string, minRating?: number) =>
		unwrap<Entry[]>(axios.get(generateOcsUrl('/apps/tagebuch/api/search'), { params: { q, tag, since, until, min_rating: minRating } })),
	onThisDay: () => unwrap<Entry[]>(axios.get(generateOcsUrl('/apps/tagebuch/api/on-this-day'))),
	tags: () => unwrap<string[]>(axios.get(generateOcsUrl('/apps/tagebuch/api/tags'))),
	stats: () => unwrap<Stats>(axios.get(generateOcsUrl('/apps/tagebuch/api/stats'))),
	templates: () => unwrap<string[]>(axios.get(generateOcsUrl('/apps/tagebuch/api/templates'))),
	template: (name: string) => unwrap<{ name: string, body: string }>(axios.get(generateOcsUrl('/apps/tagebuch/api/templates/' + encodeURIComponent(name)))),
	saveTemplate: (name: string, body: string) => unwrap<{ name: string }>(axios.post(generateOcsUrl('/apps/tagebuch/api/templates'), { name, body })),
	attachment: async (date: string, file: File) => {
		let upload = file
		if (isEncryptionEnabled()) {
			if (!isUnlocked()) throw new Error('Tresor ist gesperrt – Anhang kann nicht verschlüsselt werden.')
			upload = await encryptFile(file)
		}
		const fd = new FormData()
		fd.append('file', upload, upload.name)
		return unwrap<{ path: string }>(axios.post(generateOcsUrl(`/apps/tagebuch/api/entry/${date}/attachment`), fd))
	},
	attachmentData: (date: string, name: string) =>
		axios.get(generateOcsUrl(`/apps/tagebuch/api/entry/${date}/attachment/${encodeURIComponent(name)}`), { responseType: 'arraybuffer' })
			.then((r) => r.data as ArrayBuffer),
	onThisDayPhotos: () => unwrap<Photo[]>(axios.get(generateOcsUrl('/apps/tagebuch/api/on-this-day/photos'))),
	photoLocation: (path: string) => unwrap<{ location: { lat: number, lon: number } | null }>(
		axios.get(generateOcsUrl('/apps/tagebuch/api/photo-location'), { params: { path } })),
	photoData: (path: string, signal?: AbortSignal, version?: string) =>
		axios.get(generateOcsUrl('/apps/tagebuch/api/photo'), { params: { path, version }, signal, responseType: 'arraybuffer' })
			.then((r) => r.data as ArrayBuffer),
	settings: () => unwrap<Settings>(axios.get(generateOcsUrl('/apps/tagebuch/api/settings'))),
	saveSettings: (s: Partial<Settings>) => unwrap<Record<string, string>>(axios.post(generateOcsUrl('/apps/tagebuch/api/settings'), s)),
	tokenInfo: (t: string) => unwrap<{ user: string, date: string }>(axios.get(generateOcsUrl('/apps/tagebuch/api/token/' + t))),
}

export const stars = (rating: number | null): string => (rating === null ? '' : '★'.repeat(rating) + '☆'.repeat(5 - rating))

export const formatDate = (date: string, time: string | null): string => {
	const [y, m, d] = date.split('-')
	return `${d}.${m}.${y}` + (time ? ` ${time}` : '')
}
