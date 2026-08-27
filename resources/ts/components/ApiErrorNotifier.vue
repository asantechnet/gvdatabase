<script setup lang="ts">
import { apiErrorMessage, type ApiError } from '@/utils/apiErrors'
import { apiFieldErrors } from '@/utils/apiErrors'
import { displayInlineFormErrors, replaceFormErrors } from '@/utils/formErrors'
import { onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()

const showApiError = async (event: Event) => {
  const error = (event as CustomEvent<ApiError>).detail
  if (error.code === 'validation_failed' || error.status === 422 && error.errors) {
    replaceFormErrors(apiFieldErrors(error))
    const matchedFields = await displayInlineFormErrors()
    if (!matchedFields)
      toast.error(apiErrorMessage(error))
    return
  }
  toast.error(apiErrorMessage(error))
}

onMounted(() => window.addEventListener('api-error', showApiError))
onUnmounted(() => window.removeEventListener('api-error', showApiError))
</script>

<template>
  <span class="d-none" aria-hidden="true" />
</template>
