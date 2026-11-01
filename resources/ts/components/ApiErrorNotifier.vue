<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { type ApiError, apiErrorMessage } from '@/utils/apiErrors'
import { apiFieldErrors } from '@/utils/apiErrors'
import { displayInlineFormErrors, replaceFormErrors } from '@/utils/formErrors'

const toast = useToast()

// The dispatcher already emits once per request; this keeps a duplicated event
// (for example from a replayed request) from producing a second toast.
const notified = new WeakSet<ApiError>()

const showApiError = async (event: Event) => {
  const error = (event as CustomEvent<ApiError>).detail
  if (!error || notified.has(error))
    return
  notified.add(error)
  if (error.code === 'validation_failed' || (error.status === 422 && error.errors)) {
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
  <span
    class="d-none"
    aria-hidden="true"
  />
</template>
