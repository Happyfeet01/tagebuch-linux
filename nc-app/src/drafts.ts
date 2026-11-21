import { openDraft, protectDraft } from './crypto'
import { readState, writeState } from './state'

// One queue per open draft. A late response can only update its own revision.
export class OnlineDraft {
	private revision = ''
	private pending: string | undefined
	private timer?: number
	private queue: Promise<void> = Promise.resolve()
	constructor(private key: string, private status: (text: string) => void) {}
	get dirty() { return this.pending !== undefined }
	async load() {
		const state = await readState(this.key)
		this.revision = state.revision
		return state.payload ? JSON.parse(await openDraft(state.payload)) : null
	}
	schedule(value: string) {
		this.pending = value
		this.status('Entwurf wird gesichert …')
		clearTimeout(this.timer)
		this.timer = window.setTimeout(() => this.flush().catch(() => {}), 650)
	}
	flush(): Promise<void> {
		clearTimeout(this.timer)
		this.queue = this.queue.catch(() => {}).then(async () => {
			const value = this.pending
			if (value === undefined) return
			try {
				this.revision = await writeState(this.key, this.revision, await protectDraft(value))
				if (this.pending === value) this.pending = undefined
				this.status('Entwurf online gesichert.')
			} catch (error) {
				this.status('Entwurf konnte nicht gesichert werden. Prüfe die Verbindung oder Änderungen auf einem anderen Gerät.')
				throw error
			}
		})
		return this.queue
	}
	async clear() {
		await this.flush()
		if (this.revision) this.revision = await writeState(this.key, this.revision, '')
	}
}
