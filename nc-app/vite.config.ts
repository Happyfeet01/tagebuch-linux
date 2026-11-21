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
		assetFileNames: asset => {
			if (asset.names.includes('worker.sql-asm.js')) return 'js/diarium-sql-[hash].js'
			if (asset.names.some(name => name.endsWith('.css'))) return `css/tagebuch-${entry}.css`
		},
		// Util::addScript requests a classic .js entry.
		config: {
			build: {
				cssCodeSplit: false,
				rollupOptions: {
					output: { format: 'iife', inlineDynamicImports: true, entryFileNames: 'js/tagebuch-[name].js' },
				},
			},
		},
		extractLicenseInformation: true,
		thirdPartyLicense: false,
	},
)
