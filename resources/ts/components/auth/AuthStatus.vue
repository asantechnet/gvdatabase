<script setup lang="ts">
withDefaults(defineProps<{
  message: string
  tone?: 'success' | 'error' | 'warning' | 'info'
  dismissible?: boolean
}>(), {
  tone: 'info',
  dismissible: true,
})

const emit = defineEmits<{ dismiss: [] }>()
</script>

<template>
  <VAlert
    :type="tone"
    variant="tonal"
    density="comfortable"
    :role="tone === 'error' ? 'alert' : 'status'"
    :closable="dismissible"
    class="auth-status"
    @click:close="emit('dismiss')"
  >
    {{ message }}
  </VAlert>
</template>

<style scoped>
.auth-status {
  text-align: start;
}
</style>
