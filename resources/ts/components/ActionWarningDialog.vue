<script setup lang="ts">
import { actionWarningState, resolveActionWarning } from '@/composables/useActionWarning'
</script>

<template>
  <VDialog
    :model-value="actionWarningState.visible"
    persistent
    max-width="520"
    aria-modal="true"
  >
    <VCard
      v-if="actionWarningState.request"
      data-form-warning-dialog
      class="action-warning-dialog"
    >
      <VCardText class="pa-6 pb-4">
        <div class="d-flex align-start gap-4">
          <VAvatar
            color="warning"
            variant="tonal"
            size="48"
            rounded="lg"
          >
            <VIcon
              :icon="actionWarningState.request.icon || 'tabler-alert-triangle'"
              size="27"
            />
          </VAvatar>

          <div class="flex-grow-1 min-width-0">
            <h2 class="text-h6 font-weight-bold mb-2">
              {{ actionWarningState.request.title }}
            </h2>
            <p class="text-body-2 text-medium-emphasis mb-4">
              {{ actionWarningState.request.message }}
            </p>
            <VCheckbox
              v-model="actionWarningState.rememberChoice"
              :label="$t('common.actionWarnings.dontShowAgain')"
              color="warning"
              density="compact"
              hide-details
            />
          </div>
        </div>
      </VCardText>

      <VCardActions class="justify-end gap-2 px-6 pb-6">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="resolveActionWarning(false)"
        >
          {{ actionWarningState.request.cancelText }}
        </VBtn>
        <VBtn
          color="warning"
          variant="elevated"
          @click="resolveActionWarning(true)"
        >
          {{ actionWarningState.request.confirmText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
