<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api'
import { attachmentName, decryptFile, isEncryptedAttachment, mimeForName } from '../crypto'
const props = defineProps<{ path: string, date: string }>()
const el = ref<HTMLElement>()
const url = ref('')
const name = attachmentName(props.path)
const mime = mimeForName(name)
let active = true
let observer: IntersectionObserver | undefined
async function load() {
	try {
		const bytes = await api.attachmentData(props.date, props.path.split('/').pop()!)
		const blob = isEncryptedAttachment(props.path) ? await decryptFile(bytes, name) : new Blob([bytes], { type: mime })
		if (active) url.value = URL.createObjectURL(blob)
	} catch { /* The file name still opens the entry when preview is unavailable. */ }
}
onMounted(() => {
	if (!mime.startsWith('image/') && !mime.startsWith('video/')) return
	if (!('IntersectionObserver' in window)) { void load(); return }
	observer = new IntersectionObserver(items => { if (items.some(item => item.isIntersecting)) { observer?.disconnect(); void load() } })
	if (el.value) observer.observe(el.value)
})
onBeforeUnmount(() => { active = false; observer?.disconnect(); if (url.value) URL.revokeObjectURL(url.value) })
</script>
<template><span ref="el">
	<img v-if="url && mime.startsWith('image/')" :src="url" :alt="name">
	<video v-else-if="url" :src="url" muted preload="metadata" />
	{{ name }}
</span></template>
<style scoped>img, video {display:block;width:100%;height:110px;object-fit:cover}</style>
