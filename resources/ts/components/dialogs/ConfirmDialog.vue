<script setup lang="ts">
import { computed, ref } from 'vue'

type ConfirmationTone = 'error' | 'warning' | 'success' | 'info' | 'primary'

interface Props {
  confirmationQuestion: string
  isDialogVisible: boolean
  confirmTitle: string
  confirmMsg: string
  cancelTitle: string
  cancelMsg: string
  confirmationMessage?: string
  confirmText?: string
  cancelText?: string
  tone?: ConfirmationTone
  icon?: string
}

interface Emit {
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'confirm', value: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
  confirmationMessage: '',
  confirmText: '',
  cancelText: '',
  tone: 'warning',
  icon: '',
})

const emit = defineEmits<Emit>()
const result = ref<'success' | 'error' | null>(null)

const toneIcon = computed(() => props.icon || {
  error: 'tabler-trash',
  warning: 'tabler-alert-triangle',
  success: 'tabler-circle-check',
  info: 'tabler-info-circle',
  primary: 'tabler-help-circle',
}[props.tone])

const updateModelValue = (value: boolean) => emit('update:isDialogVisible', value)

const onConfirmation = () => {
  emit('confirm', true)
  updateModelValue(false)
  result.value = 'success'
}

const onCancel = () => {
  emit('confirm', false)
  updateModelValue(false)
  result.value = 'error'
}
</script>

<template>
  <VDialog
    max-width="480"
    :model-value="props.isDialogVisible"
    class="confirmation-dialog-overlay"
    @update:model-value="updateModelValue"
  >
    <VCard :class="['confirmation-dialog', `confirmation-dialog--${props.tone}`]">
      <VCardText class="confirmation-dialog__content">
        <div class="confirmation-dialog__icon" aria-hidden="true">
          <VIcon :icon="toneIcon" size="30" />
        </div>

        <h2 class="confirmation-dialog__title">
          {{ props.confirmationQuestion }}
        </h2>
        <p v-if="props.confirmationMessage" class="confirmation-dialog__description">
          {{ props.confirmationMessage }}
        </p>
      </VCardText>

      <VCardActions class="confirmation-dialog__actions">
        <VBtn class="confirmation-dialog__button confirmation-dialog__button--cancel" @click="onCancel">
          {{ props.cancelText || $t('common.actions.cancel') }}
        </VBtn>
        <VBtn class="confirmation-dialog__button confirmation-dialog__button--confirm" @click="onConfirmation">
          {{ props.confirmText || $t('common.actions.confirm') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    :model-value="Boolean(result)"
    max-width="440"
    class="confirmation-dialog-overlay"
    @update:model-value="value => { if (!value) result = null }"
  >
    <VCard
      v-if="result"
      :class="['confirmation-dialog', `confirmation-dialog--${result === 'success' ? 'success' : 'error'}`]"
    >
      <VCardText class="confirmation-dialog__content">
        <div class="confirmation-dialog__icon" aria-hidden="true">
          <VIcon :icon="result === 'success' ? 'tabler-circle-check' : 'tabler-x'" size="30" />
        </div>
        <h2 class="confirmation-dialog__title">
          {{ result === 'success' ? props.confirmTitle : props.cancelTitle }}
        </h2>
        <p class="confirmation-dialog__description">
          {{ result === 'success' ? props.confirmMsg : props.cancelMsg }}
        </p>
      </VCardText>
      <VCardActions class="confirmation-dialog__actions confirmation-dialog__actions--single">
        <VBtn class="confirmation-dialog__button confirmation-dialog__button--confirm" @click="result = null">
          {{ $t('common.messages.ok') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.confirmation-dialog-overlay {
  .v-overlay__scrim {
    background: rgba(var(--v-theme-on-surface), 0.3) !important;
    backdrop-filter: blur(7px);
    opacity: 1 !important;
  }
}

.confirmation-dialog {
  border: 1px solid rgba(var(--v-border-color), 0.2) !important;
  border-radius: 16px !important;
  background: rgb(var(--v-theme-surface)) !important;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.18) !important;
  overflow: hidden;
  text-align: center;

  --confirmation-color: rgb(var(--v-theme-warning));
  --confirmation-on-color: rgb(var(--v-theme-on-warning));
  --confirmation-tint: rgba(var(--v-theme-warning), 0.14);

  &--error {
    --confirmation-color: rgb(var(--v-theme-error));
    --confirmation-on-color: rgb(var(--v-theme-on-error));
    --confirmation-tint: rgba(var(--v-theme-error), 0.14);
  }

  &--success {
    --confirmation-color: rgb(var(--v-theme-success));
    --confirmation-on-color: rgb(var(--v-theme-on-success));
    --confirmation-tint: rgba(var(--v-theme-success), 0.14);
  }

  &--info {
    --confirmation-color: rgb(var(--v-theme-info));
    --confirmation-on-color: rgb(var(--v-theme-on-info));
    --confirmation-tint: rgba(var(--v-theme-info), 0.14);
  }

  &--primary {
    --confirmation-color: rgb(var(--v-theme-primary));
    --confirmation-on-color: rgb(var(--v-theme-on-primary));
    --confirmation-tint: rgba(var(--v-theme-primary), 0.14);
  }
}

.confirmation-dialog__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2rem 2rem 1.25rem !important;
}

.confirmation-dialog__icon {
  display: grid;
  width: 4.25rem;
  height: 4.25rem;
  margin-block-end: 1.25rem;
  border-radius: 50%;
  place-items: center;
  background: var(--confirmation-tint);
  color: var(--confirmation-color);
}

.confirmation-dialog__title {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1.45;
  text-align: center;
}

.confirmation-dialog__description {
  max-width: 24rem;
  margin: 0.65rem 0 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  line-height: 1.65;
  text-align: center;
}

.confirmation-dialog__actions {
  display: grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0 2rem 2rem !important;
}

.confirmation-dialog__actions--single {
  grid-template-columns: minmax(0, 1fr);
}

.confirmation-dialog__button {
  min-height: 2.85rem;
  border-radius: 10px !important;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: none;

  &--cancel {
    border: 1px solid rgba(var(--v-theme-secondary), 0.52) !important;
    background: rgba(var(--v-theme-secondary), 0.13) !important;
    color: rgb(var(--v-theme-secondary)) !important;

    &:hover {
      background: rgb(var(--v-theme-secondary)) !important;
      color: rgb(var(--v-theme-on-secondary)) !important;
    }
  }

  &--confirm {
    background: var(--confirmation-color) !important;
    color: var(--confirmation-on-color) !important;

    &:hover {
      filter: brightness(0.88);
    }
  }
}

@media (max-width: 480px) {
  .confirmation-dialog__content { padding-inline: 1.25rem !important; }
  .confirmation-dialog__actions { padding-inline: 1.25rem !important; }
}
</style>
