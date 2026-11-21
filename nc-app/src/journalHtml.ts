import { strFromU8, strToU8, unzipSync, zipSync } from 'fflate'
import type { Entry } from './api'
import { parseDiariumRows } from './diariumImport'
import { decryptPayload } from './crypto'

export interface HtmlEntry { date: string, heading: string, html: string, source: string }

// Parse into an inert template; imported markup is never mounted in the page.
export function htmlEntries(source: string, contents: string): HtmlEntry[] {
	const template = document.createElement('template')
	template.innerHTML = contents
	const root = template.content
	if (/(?:^|\/)index\.html?$/i.test(source) && [...root.querySelectorAll('a[href]')].some(link =>
		/^(?:\.\/)?Entries\/[^?#]+\.html?(?:[?#]|$)/i.test(link.getAttribute('href') || ''))) {
		throw new Error('Dies ist das Inhaltsverzeichnis von Apple Journal. Wähle die ZIP-Datei mit dem Ordner Entries oder eine HTML-Datei aus Entries.')
	}

	root.querySelectorAll('style, script, noscript, head, title, .assetGrid').forEach(el => el.remove())
	const articles = [...root.querySelectorAll('article')]
	return (articles.length ? articles : [root]).map(section => {
		const title = section.querySelector('.title, h1, h2')
		const time = section.querySelector('time')
		const stamp = time?.getAttribute('datetime') || time?.textContent || ''
		const filename = source.split('/').pop() || source
		const date = stamp.match(/^\d{4}-\d{2}-\d{2}(?:T\d\d:\d\d(?::\d\d(?:\.\d+)?)?)?/)?.[0]
			|| filename.match(/^(\d{4}-\d{2}-\d{2})/)?.[1] || ''
		const heading = title?.textContent?.trim() || ''
		title?.remove()
		time?.remove()
		section.querySelectorAll('.pageHeader, .entryDate, .pageFooter').forEach(el => el.remove())
		const body = section.querySelector('.bodyText, main') || section
		const wrapper = document.createElement('div')
		wrapper.append(...[...body.childNodes].map(node => node.cloneNode(true)))
		return { date, heading, html: wrapper.innerHTML, source: filename }
	})
}

export async function readJournalHtml(file: File): Promise<HtmlEntry[]> {
	if (!file.name.toLowerCase().endsWith('.zip')) return htmlEntries(file.name, await file.text())
	let total = 0
	const data = unzipSync(new Uint8Array(await file.arrayBuffer()), { filter: entry => {
		if (!/\.html?$/i.test(entry.name) || /(?:^|\/)(?:__MACOSX|Resources|Attachments|media)\//i.test(entry.name)
			|| /(?:^|\/)(?:\._.*|index\.html?)$/i.test(entry.name)) return false
		total += entry.originalSize
		if (total > 64 * 1024 * 1024) throw new Error('Die HTML-Texte im Archiv sind zu groß.')
		return true
	} })
	const entries = Object.entries(data).flatMap(([name, bytes]) => htmlEntries(name, strFromU8(bytes)))
	if (!entries.length) throw new Error('Keine HTML-Einträge gefunden. Eine einzelne HTML-Datei kannst du auch direkt auswählen.')
	return entries
}

export function confirmedHtml(entries: HtmlEntry[]) {
	return parseDiariumRows(entries.map(({ date, heading, html }) => ({ date, heading, html })))
}

const escape = (text: string) => text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;')

export async function portableJournal(entries: Entry[], format: 'html' | 'dayone'): Promise<Blob> {
	const files: Record<string, Uint8Array> = {}
	const records = []
	for (const [index, entry] of entries.entries()) {
		const clear = entry.encrypted ? await decryptPayload(entry.body) : entry
		if (!clear) throw new Error('Bitte zuerst den Tresor entsperren.')
		const date = entry.date + 'T' + (entry.time || '00:00')
		if (format === 'dayone') {
			records.push({ uuid: crypto.randomUUID().replaceAll('-', '').toUpperCase(), creationDate: new Date(date).toISOString(),
				timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone, tags: clear.tags,
				text: [clear.heading, clear.body].filter(Boolean).join('\n\n') })
		} else {
			const path = 'Entries/' + entry.date + '_' + index + '.html'
			files[path] = strToU8('<!doctype html><meta charset="utf-8"><article><time datetime="' + escape(date) + '">' + escape(date)
				+ '</time><h1>' + escape(clear.heading) + '</h1><main>' + escape(clear.body).replaceAll('\n', '<br>') + '</main></article>')
		}
	}
	if (format === 'dayone') return new Blob([JSON.stringify({ metadata: { version: '1.0' }, entries: records })], { type: 'application/json' })
	return new Blob([new Uint8Array(zipSync(files))], { type: 'application/zip' })
}
