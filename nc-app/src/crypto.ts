import { readState, writeState } from './state'

const SALT_KEY = 'tagebuch.enc.salt'
const VERIFIER_KEY = 'tagebuch.enc.verifier'
const VERIFIER_PLAIN = 'tagebuch-verifier'

let sessionKey: CryptoKey | null = null

const b64encode = (bytes: Uint8Array): string => {
	let bin = ''
	for (let i = 0; i < bytes.length; i += 0x8000) {
		bin += String.fromCharCode(...bytes.subarray(i, i + 0x8000))
	}
	return btoa(bin)
}

const b64decode = (b64: string): Uint8Array<ArrayBuffer> => {
	const bin = atob(b64)
	const bytes = new Uint8Array(bin.length)
	for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
	return bytes
}

const deriveKey = async (password: string, salt: Uint8Array<ArrayBuffer>): Promise<CryptoKey> => {
	const base = await crypto.subtle.importKey(
		'raw',
		new TextEncoder().encode(password) as Uint8Array<ArrayBuffer>,
		'PBKDF2',
		false,
		['deriveKey'],
	)
	return crypto.subtle.deriveKey(
		{ name: 'PBKDF2', salt, iterations: 100000, hash: 'SHA-256' },
		base,
		{ name: 'AES-GCM', length: 256 },
		false,
		['encrypt', 'decrypt'],
	)
}

const encryptWithKey = async (key: CryptoKey, plain: string): Promise<string> => {
	const nonce = crypto.getRandomValues(new Uint8Array(12))
	const data = new TextEncoder().encode(plain) as Uint8Array<ArrayBuffer>
	const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: nonce }, key, data)
	return b64encode(nonce) + '.' + b64encode(new Uint8Array(ct))
}

const decryptWithKey = async (key: CryptoKey, payload: string): Promise<string> => {
	const [nonceB64, ctB64] = payload.split('.')
	const pt = await crypto.subtle.decrypt(
		{ name: 'AES-GCM', iv: b64decode(nonceB64) },
		key,
		b64decode(ctB64),
	)
	return new TextDecoder().decode(pt)
}

interface VaultProfile { salt: string, verifier: string }
let profile: VaultProfile | null = null
let legacy = false
let needsOriginal = false

export async function initializeVault(encryptedSample?: string) {
	const state = await readState('vault')
	profile = state.payload ? JSON.parse(state.payload) : null
	const salt = localStorage.getItem(SALT_KEY)
	const verifier = localStorage.getItem(VERIFIER_KEY)
	legacy = !profile && !!salt && !!verifier && !!encryptedSample
	if (legacy) profile = { salt: salt!, verifier: verifier! }
	needsOriginal = !profile && !!encryptedSample
	sample = legacy ? encryptedSample : undefined
}
let sample: string | undefined

export const isEncryptionEnabled = (): boolean => !!profile || needsOriginal
export const hasEncryptionSetup = isEncryptionEnabled
export const isUnlocked = (): boolean => sessionKey !== null

export const setupEncryption = async (password: string): Promise<void> => {
	if (isEncryptionEnabled()) throw new Error('Es ist bereits ein Tresor vorhanden.')
	const salt = crypto.getRandomValues(new Uint8Array(16))
	const key = await deriveKey(password, salt)
	const next = { salt: b64encode(salt), verifier: await encryptWithKey(key, VERIFIER_PLAIN) }
	await writeState('vault', '', JSON.stringify(next))
	profile = next
	sessionKey = key
}

export const unlockEncryption = async (password: string): Promise<boolean> => {
	if (!profile) throw new Error('Öffne den ursprünglichen Browser und entsperre dort einmal den Tresor. Lade danach diese Seite neu.')
	const key = await deriveKey(password, b64decode(profile.salt))
	try {
		if (await decryptWithKey(key, profile.verifier) !== VERIFIER_PLAIN) return false
		if (sample) await decryptWithKey(key, sample)
	} catch { return false }
	if (legacy) {
		await writeState('vault', '', JSON.stringify(profile))
		legacy = false
		sample = undefined
	}
	sessionKey = key
	return true
}

export async function protectDraft(value: string): Promise<string> {
	if (!isEncryptionEnabled()) return JSON.stringify({ value })
	if (!sessionKey) throw new Error('Tresor gesperrt.')
	return JSON.stringify({ cipher: await encryptWithKey(sessionKey, value) })
}

export async function openDraft(raw: string): Promise<string> {
	const record = JSON.parse(raw)
	if (typeof record.value === 'string') return record.value
	if (!sessionKey) throw new Error('Entwurf verschlüsselt. Bitte zuerst den Tresor entsperren.')
	return decryptWithKey(sessionKey, record.cipher)
}

export const lockEncryption = (): void => {
	sessionKey = null
}

export interface EntryPayload {
	heading: string
	body: string
	tags: string[]
	trackers: Record<string, unknown>
	location: { name?: string, lat?: number, lon?: number } | null
}

export const encryptPayload = async (payload: EntryPayload): Promise<string> => {
	if (!sessionKey) throw new Error('tresor verschlossen')
	return encryptWithKey(sessionKey, JSON.stringify(payload))
}

export const decryptPayload = async (cipher: string): Promise<EntryPayload | null> => {
	if (!sessionKey) return null
	try {
		return JSON.parse(await decryptWithKey(sessionKey, cipher)) as EntryPayload
	} catch {
		return null
	}
}

export const ENC_SUFFIX = '.enc'

export const isEncryptedAttachment = (path: string): boolean => path.endsWith(ENC_SUFFIX)

export const attachmentName = (path: string): string => {
	const base = path.split('/').pop() ?? path
	return isEncryptedAttachment(base) ? base.slice(0, -ENC_SUFFIX.length) : base
}

const MIME_BY_EXT: Record<string, string> = {
	jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif',
	webp: 'image/webp', bmp: 'image/bmp', avif: 'image/avif', heic: 'image/heic', heif: 'image/heif',
	mp4: 'video/mp4', m4v: 'video/mp4', mov: 'video/quicktime', webm: 'video/webm', '3gp': 'video/3gpp',
}

export const mimeForName = (name: string): string => {
	const dot = name.lastIndexOf('.')
	const ext = dot === -1 ? '' : name.slice(dot + 1).toLowerCase()
	return MIME_BY_EXT[ext] ?? 'application/octet-stream'
}

export const encryptFile = async (file: File): Promise<File> => {
	if (!sessionKey) throw new Error('tresor verschlossen')
	const nonce = crypto.getRandomValues(new Uint8Array(12))
	const ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv: nonce }, sessionKey, await file.arrayBuffer()))
	const out = new Uint8Array(nonce.length + ct.length)
	out.set(nonce, 0)
	out.set(ct, nonce.length)
	return new File([out], file.name + ENC_SUFFIX, { type: 'application/octet-stream' })
}

export const decryptFile = async (data: ArrayBuffer, name: string): Promise<Blob> => {
	if (!sessionKey) throw new Error('tresor verschlossen')
	const bytes = new Uint8Array(data)
	const nonce = bytes.slice(0, 12)
	const ct = bytes.slice(12)
	const pt = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: nonce }, sessionKey, ct)
	return new Blob([pt], { type: mimeForName(name) })
}
