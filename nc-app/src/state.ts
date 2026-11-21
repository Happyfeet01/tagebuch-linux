import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const stateUrl = (key: string) => generateOcsUrl('/apps/tagebuch/api/state/{key}', { key })
export async function readState(key: string): Promise<{ payload: string, revision: string }> {
	return (await axios.get(stateUrl(key))).data.ocs.data
}
export async function writeState(key: string, revision: string, payload: string): Promise<string> {
	return (await axios.put(stateUrl(key), { revision, payload })).data.ocs.data.revision
}
