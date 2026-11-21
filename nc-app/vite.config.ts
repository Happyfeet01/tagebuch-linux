import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

import { readFileSync } from 'node:fs'

const info = readFileSync(new URL('./appinfo/info.xml', import.meta.url), 'utf8')
const version = info.match(/<version>([0-9]+(?:\.[0-9]+)*)<\/version>/)?.[1]
if (!version) throw new Error('Missing app version in appinfo/info.xml')

const entry = `main-${version.replaceAll('.', '-')}`
const template = readFileSync(new URL('./templates/index.php', import.meta.url), 'utf8')
if (!template.includes(`$entry = 'tagebuch-${entry}';`)) {
	throw new Error('Update the frontend entry in templates/index.php to match appinfo/info.xml')
}

export default createAppConfig(
	{
		[entry]: resolve(join('src', 'main.ts')),
	},
	{
		assetFileNames: asset => asset.names.includes('worker.sql-asm.js') ? 'js/diarium-sql-[hash].js' : undefined,
		createEmptyCSSEntryPoints: true,
		extractLicenseInformation: true,
		thirdPartyLicense: false,
	},
)
