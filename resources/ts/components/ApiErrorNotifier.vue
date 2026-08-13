<script setup lang="ts">
import { apiErrorMessage, type ApiError } from '@/utils/apiErrors'
import { onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()

const showApiError = (event: Event) => {
  const error = (event as CustomEvent<ApiError>).detail
  toast.error(apiErrorMessage(error))
}

onMounted(() => window.addEventListener('api-error', showApiError))
onUnmounted(() => window.removeEventListener('api-error', showApiError))
</script>

<template>
  <span class="d-none" aria-hidden="true" />
</template>
