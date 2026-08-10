<script setup lang="ts">
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
  content: string
  contentType?: 'html' | 'text'
  theme?: string
}>()

const emit = defineEmits<{
  (e: 'update:content', value: string): void
}>()

const editorContainer = ref<HTMLElement | null>(null)
let quillInstance: Quill | null = null
let isInternalChange = false

onMounted(() => {
  if (!editorContainer.value) return

  quillInstance = new Quill(editorContainer.value, {
    theme: props.theme || 'snow',
    modules: {
      toolbar: [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'code-block'],
        [{ header: [1, 2, 3, false] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['clean']
      ]
    }
  })

  // Set initial content safely
  if (props.content) {
    quillInstance.root.innerHTML = props.content
  }

  // Listen for text changes and emit upward
  quillInstance.on('text-change', () => {
    if (!quillInstance) return
    isInternalChange = true
    const value = props.contentType === 'text'
      ? quillInstance.getText()
      : quillInstance.root.innerHTML

    emit('update:content', value)
    isInternalChange = false
  })
})

// Sync external content mutations back into Quill if updated programmatically
watch(() => props.content, (newVal) => {
  if (quillInstance && !isInternalChange) {
    const currentHtml = quillInstance.root.innerHTML
    if (newVal !== currentHtml) {
      quillInstance.root.innerHTML = newVal || ''
    }
  }
})
</script>

<template>
  <div class="quill-editor-component">
    <div ref="editorContainer"></div>
  </div>
</template>

<style scoped>
/* Vuetify surface styling integration */
.quill-editor-component :deep(.ql-toolbar.ql-snow) {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  border-top-left-radius: 6px;
  border-top-right-radius: 6px;
  background-color: rgb(var(--v-theme-surface));
}

.quill-editor-component :deep(.ql-container.ql-snow) {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  border-bottom-left-radius: 6px;
  border-bottom-right-radius: 6px;
  background-color: rgb(var(--v-theme-surface));
  font-family: inherit;
  font-size: 0.95rem;
  min-height: 180px;
}
</style>
