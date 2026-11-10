<script setup lang="ts">
import { computed } from 'vue'
import { actionWarningState, resolveActionWarning } from '@/composables/useActionWarning'

const warningTone = computed(() => actionWarningState.request?.tone || 'warning')
</script>

<template>
  <VDialog
    :model-value="actionWarningState.visible"
    persistent
    max-width="480"
    aria-modal="true"
    class="action-warning-overlay"
  >
    <VCard
      v-if="actionWarningState.request"
      data-form-warning-dialog
      :class="['action-warning-dialog', `action-warning-dialog--${warningTone}`]"
    >
      <VCardText class="action-warning-dialog__content">
        <div class="action-warning-dialog__icon" aria-hidden="true">
          <VIcon :icon="actionWarningState.request.icon || 'tabler-alert-triangle'" size="30" />
        </div>

        <h2 class="action-warning-dialog__title">
          {{ actionWarningState.request.title }}
        </h2>
        <p class="action-warning-dialog__description">
          {{ actionWarningState.request.message }}
        </p>

        <VCheckbox
          v-model="actionWarningState.rememberChoice"
          :label="$t('common.actionWarnings.dontShowAgain')"
          :color="warningTone"
          class="action-warning-dialog__remember"
          density="compact"
          hide-details
        />
      </VCardText>

      <VCardActions class="action-warning-dialog__actions">
        <VBtn class="action-warning-dialog__button action-warning-dialog__button--cancel" @click="resolveActionWarning(false)">
          {{ actionWarningState.request.cancelText }}
        </VBtn>
        <VBtn class="action-warning-dialog__button action-warning-dialog__button--confirm" @click="resolveActionWarning(true)">
          {{ actionWarningState.request.confirmText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.action-warning-overlay {
  .v-overlay__scrim {
    background: rgba(var(--v-theme-on-surface), 0.3) !important;
    backdrop-filter: blur(7px);
    opacity: 1 !important;
  }
}

.action-warning-dialog {
  border: 1px solid rgba(var(--v-border-color), 0.2) !important;
  border-radius: 16px !important;
  background: rgb(var(--v-theme-surface)) !important;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.18) !important;
  overflow: hidden;
  text-align: center;

  --warning-color: rgb(var(--v-theme-warning));
  --warning-on-color: rgb(var(--v-theme-on-warning));
  --warning-tint: rgba(var(--v-theme-warning), 0.14);

  &--error {
    --warning-color: rgb(var(--v-theme-error));
    --warning-on-color: rgb(var(--v-theme-on-error));
    --warning-tint: rgba(var(--v-theme-error), 0.14);
  }

  &--success {
    --warning-color: rgb(var(--v-theme-success));
    --warning-on-color: rgb(var(--v-theme-on-success));
    --warning-tint: rgba(var(--v-theme-success), 0.14);
  }

  &--info {
    --warning-color: rgb(var(--v-theme-info));
    --warning-on-color: rgb(var(--v-theme-on-info));
    --warning-tint: rgba(var(--v-theme-info), 0.14);
  }

  &--primary {
    --warning-color: rgb(var(--v-theme-primary));
    --warning-on-color: rgb(var(--v-theme-on-primary));
    --warning-tint: rgba(var(--v-theme-primary), 0.14);

    .action-warning-dialog__button--cancel {
      border-color: rgba(var(--v-theme-primary), 0.5) !important;
      background: rgba(var(--v-theme-primary), 0.13) !important;
      color: rgb(var(--v-theme-primary)) !important;

      .v-btn__content { color: rgb(var(--v-theme-primary)) !important; }

      &:hover,
      &:focus-visible,
      &.v-btn--active,
      &:active {
        border-color: rgb(var(--v-theme-primary)) !important;
        background: rgb(var(--v-theme-primary)) !important;
        color: rgb(var(--v-theme-on-primary)) !important;

        .v-btn__content { color: rgb(var(--v-theme-on-primary)) !important; }
      }
    }
  }
}

.action-warning-dialog__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2rem 2rem 1.1rem !important;
}

.action-warning-dialog__icon {
  display: grid;
  width: 4.25rem;
  height: 4.25rem;
  margin-block-end: 1.25rem;
  border-radius: 50%;
  place-items: center;
  background: var(--warning-tint);
  color: var(--warning-color);
}

.action-warning-dialog__title {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1.45;
  text-align: center;
}

.action-warning-dialog__description {
  max-width: 24rem;
  margin: 0.65rem 0 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  line-height: 1.65;
  text-align: center;
}

.action-warning-dialog__remember {
  max-width: 100%;
  margin-block-start: 1.1rem;

  .v-selection-control {
    justify-content: center;
  }

  .v-label {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    text-align: start;
  }
}

.action-warning-dialog__actions {
  display: grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0 2rem 2rem !important;
}

.action-warning-dialog__button {
  min-height: 2.85rem;
  border-radius: 10px !important;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: none;

  &--cancel {
    border: 1px solid rgba(var(--v-theme-error), 0.62) !important;
    background: rgba(var(--v-theme-error), 0.12) !important;
    color: rgb(var(--v-theme-error)) !important;

    .v-btn__content {
      color: rgb(var(--v-theme-error)) !important;
    }

    &:hover,
    &:focus-visible,
    &.v-btn--active,
    &:active {
      border-color: rgb(var(--v-theme-error)) !important;
      background: rgb(var(--v-theme-error)) !important;
      color: rgb(var(--v-theme-on-error)) !important;

      .v-btn__content {
        color: rgb(var(--v-theme-on-error)) !important;
      }
    }
  }

  &--confirm {
    background: var(--warning-color) !important;
    color: #fff !important;

    .v-btn__content {
      color: #fff !important;
    }

    &:hover,
    &:focus-visible,
    &.v-btn--active,
    &:active {
      filter: brightness(0.88);
      color: #fff !important;

      .v-btn__content {
        color: #fff !important;
      }
    }
  }
}

@media (max-width: 480px) {
  .action-warning-dialog__content { padding-inline: 1.25rem !important; }
  .action-warning-dialog__actions { padding-inline: 1.25rem !important; }
}
</style>
