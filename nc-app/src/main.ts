import { createApp } from 'vue'
import { generateUrl } from '@nextcloud/router'
import App from './App.vue'

if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker.register(generateUrl('/apps/tagebuch/sw.js')).catch(() => {})
	})
}

const app = createApp(App)
app.mount('#tagebuch')
