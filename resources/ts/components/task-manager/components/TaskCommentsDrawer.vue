<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { ref, watch } from 'vue'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isOpen', value: boolean): void
}>()

const store = useTaskStore()
const newComment = ref('')

const handleClose = () => {
  emit('update:isOpen', false)
}

const handleSendComment = async () => {
  if (newComment.value.trim() && store.selectedTaskId) {
    try {
      await store.addComment(store.selectedTaskId, newComment.value.trim())
      newComment.value = ''
    } catch (error) {
      console.error('Failed to add comment:', error)
    }
  }
}

// Scroll to bottom when comments change
watch(() => store.selectedTask?.comments, () => {
  setTimeout(() => {
    const container = document.querySelector('.comments-scroll-container')
    if (container) {
      container.scrollTop = container.scrollHeight
    }
  }, 100)
}, { deep: true })
</script>

<template>
  <VNavigationDrawer
    v-model="props.isOpen"
    location="end"
    temporary
    :width="400"
    class="task-comments-drawer"
    @update:model-value="handleClose"
  >
    <VCard elevation="0" class="h-100 d-flex flex-column">
      <VCardItem class="border-b">
        <template #title>
          <div class="d-flex align-center">
            <VIcon icon="tabler-message-circle" class="mr-2" />
            <span class="text-h6 font-weight-bold">Task Comments</span>
          </div>
        </template>
        <template #append>
          <VBtn icon="tabler-x" variant="text" size="small" @click="handleClose" />
        </template>
      </VCardItem>

      <div class="flex-grow-1 overflow-y-auto comments-scroll-container pa-4">
        <div v-if="store.selectedTask" class="comments-container">
          <div
            v-if="!store.selectedTask.comments || store.selectedTask.comments.length === 0"
            class="text-center text-disabled mt-10"
          >
            <VIcon icon="tabler-messages" size="40" class="mb-2 opacity-50" />
            <p>No comments yet.<br>Be the first to start the discussion!</p>
          </div>

          <div
            v-for="comment in store.selectedTask.comments"
            :key="comment.id"
            class="comment mb-4"
          >
            <div class="d-flex">
              <VAvatar size="32" class="mr-3">
                <VImg :src="comment.senderId ? `/images/avatars/avatar-${comment.senderId}.png` : ''" />
              </VAvatar>
              <div class="flex-grow-1">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="font-weight-medium">{{ comment.senderName }}</span>
                  <span class="text-caption text-disabled">{{ comment.timestamp }}</span>
                </div>
                <p class="mb-0">{{ comment.content }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <VCardActions class="border-t pa-4">
        <VTextarea
          v-model="newComment"
          placeholder="Add a comment..."
          rows="2"
          auto-grow
          hide-details
          class="mr-2"
          @keyup.enter.exact.prevent="handleSendComment"
        />
        <VBtn
          icon="tabler-send"
          color="primary"
          :disabled="!newComment.trim()"
          @click="handleSendComment"
        />
      </VCardActions>
    </VCard>
  </VNavigationDrawer>
</template>

<style scoped>
.task-comments-drawer {
  z-index: 1001;
}

.comment {
  word-break: break-word;
}

.comments-scroll-container {
  max-height: calc(100vh - 180px);
}
</style>
