import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

import { readFileSync } from 'node:fs'

const info = readFileSync(new URL('./appinfo/info.xml', import.meta.url), 'utf8')
const version = info.match(/<version>([0-9]+(?:\.[0-9]+)*)<\/version>/)?.[1]
if (!version) throw new Error('Missing app version in appinfo/info.xml')

export default createAppConfig(
	{
		[`main-${version.replaceAll('.', '-')}`]: resolve(join('src', 'main.ts')),
	},
	{
		createEmptyCSSEntryPoints: true,
		extractLicenseInformation: true,
		thirdPartyLicense: false,
	},
)
