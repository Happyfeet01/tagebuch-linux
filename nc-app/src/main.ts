import { createApp } from 'vue'
import { generateUrl, imagePath } from '@nextcloud/router'
import App from './App.vue'

// Nextcloud also supplies a global manifest and icons. Use this app's links on its pages.
const manifestUrl = generateUrl('/apps/tagebuch/manifest.webmanifest')
for (const link of document.querySelectorAll<HTMLLinkElement>('link[rel="manifest"]')) {
	if (new URL(link.href).pathname !== new URL(manifestUrl, location.href).pathname) link.remove()
}
for (const rel of ['icon', 'apple-touch-icon', 'apple-touch-icon-precomposed']) {
	for (const old of document.querySelectorAll('link[rel~="' + rel + '"]')) old.remove()
	const link = document.createElement('link')
	link.rel = rel
	link.type = 'image/png'
	link.href = imagePath('tagebuch', rel === 'icon' ? 'journal-32.png' : 'journal-180.png')
	document.head.append(link)
}

if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker.register(generateUrl('/apps/tagebuch/sw.js')).catch(() => {})
	})
}

const app = createApp(App)
app.mount('#tagebuch')
