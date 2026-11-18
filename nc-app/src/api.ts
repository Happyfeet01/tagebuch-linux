import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

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
	reminder_enabled: string
	theme: string
	accent: string
	font: string
	email: string
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
	entries: (from?: string, to?: string) =>
		unwrap<Entry[]>(axios.get(generateUrl('/apps/tagebuch/api/entries'), { params: { from, to } })),
	day: (date: string) => unwrap<Entry[]>(axios.get(generateUrl('/apps/tagebuch/api/day/' + date))),
	saveEntry: (e: Partial<Entry> & { date: string }) =>
		unwrap<Entry>(axios.post(generateUrl('/apps/tagebuch/api/entry'), e)),
	deleteEntry: (date: string, time?: string) =>
		unwrap<{ deleted: boolean }>(axios.delete(generateUrl('/apps/tagebuch/api/entry'), { params: { date, time } })),
	search: (q: string, tag?: string, since?: string, until?: string, minRating?: number) =>
		unwrap<Entry[]>(axios.get(generateUrl('/apps/tagebuch/api/search'), { params: { q, tag, since, until, min_rating: minRating } })),
	onThisDay: () => unwrap<Entry[]>(axios.get(generateUrl('/apps/tagebuch/api/on-this-day'))),
	tags: () => unwrap<string[]>(axios.get(generateUrl('/apps/tagebuch/api/tags'))),
	stats: () => unwrap<Stats>(axios.get(generateUrl('/apps/tagebuch/api/stats'))),
	templates: () => unwrap<string[]>(axios.get(generateUrl('/apps/tagebuch/api/templates'))),
	template: (name: string) => unwrap<{ name: string, body: string }>(axios.get(generateUrl('/apps/tagebuch/api/templates/' + encodeURIComponent(name)))),
	saveTemplate: (name: string, body: string) => unwrap<{ name: string }>(axios.post(generateUrl('/apps/tagebuch/api/templates'), { name, body })),
	attachment: (date: string, file: File) => {
		const fd = new FormData()
		fd.append('file', file)
		return unwrap<{ path: string }>(axios.post(generateUrl(`/apps/tagebuch/api/entry/${date}/attachment`), fd))
	},
	settings: () => unwrap<Settings>(axios.get(generateUrl('/apps/tagebuch/api/settings'))),
	saveSettings: (s: Partial<Settings>) => unwrap<Record<string, string>>(axios.post(generateUrl('/apps/tagebuch/api/settings'), s)),
	tokenInfo: (t: string) => unwrap<{ user: string, date: string }>(axios.get(generateUrl('/apps/tagebuch/api/token/' + t))),
}

export const stars = (rating: number | null): string => (rating === null ? '' : '★'.repeat(rating) + '☆'.repeat(5 - rating))

export const formatDate = (date: string, time: string | null): string => {
	const [y, m, d] = date.split('-')
	return `${d}.${m}.${y}` + (time ? ` ${time}` : '')
}
