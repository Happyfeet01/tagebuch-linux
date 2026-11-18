const SALT_KEY = 'tagebuch.enc.salt'
const VERIFIER_KEY = 'tagebuch.enc.verifier'
const ENABLED_KEY = 'tagebuch.enc.enabled'
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

export const isEncryptionEnabled = (): boolean => localStorage.getItem(ENABLED_KEY) === '1'

export const hasEncryptionSetup = (): boolean => localStorage.getItem(SALT_KEY) !== null

export const isUnlocked = (): boolean => sessionKey !== null

export const setupEncryption = async (password: string): Promise<void> => {
	const salt = crypto.getRandomValues(new Uint8Array(16))
	const key = await deriveKey(password, salt)
	const verifier = await encryptWithKey(key, VERIFIER_PLAIN)
	localStorage.setItem(SALT_KEY, b64encode(salt))
	localStorage.setItem(VERIFIER_KEY, verifier)
	localStorage.setItem(ENABLED_KEY, '1')
	sessionKey = key
}

export const unlockEncryption = async (password: string): Promise<boolean> => {
	const saltB64 = localStorage.getItem(SALT_KEY)
	if (!saltB64) return false
	const key = await deriveKey(password, b64decode(saltB64))
	try {
		const verifier = localStorage.getItem(VERIFIER_KEY) ?? ''
		const plain = await decryptWithKey(key, verifier)
		if (plain !== VERIFIER_PLAIN) return false
	} catch {
		return false
	}
	sessionKey = key
	return true
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
