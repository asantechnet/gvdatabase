<script setup lang="ts">
interface Emit {
  (e: 'update:isDialogVisible', val: boolean): void
}

interface Prop {
  isDialogVisible: boolean
}

const props = defineProps<Prop>()

const emit = defineEmits<Emit>()

const selectedPlan = ref('standard')

const plansList = computed(() => [
  { desc: 'Standard - $99/month', title: translate('components.dialogs.userupgradeplandialog.standard'), value: 'standard' },
  { desc: 'Basic - $0/month', title: translate('common.messages.basic'), value: 'basic' },
  { desc: 'Enterprise - $499/month', title: translate('components.dialogs.userupgradeplandialog.enterprise'), value: 'enterprice' },
  { desc: 'Company - $999/month', title: translate('common.fields.company'), value: 'company' },
])

const isConfirmDialogVisible = ref(false)

const dialogModelValueUpdate = (val: boolean) => {
  emit('update:isDialogVisible', val)
}
</script>

<template>
  <!-- 👉 upgrade plan -->
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 650"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ $t('components.dialogs.userupgradeplandialog.upgradePlan') }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ $t('components.dialogs.userupgradeplandialog.chooseTheBestPlanForUser') }}
        </p>

        <div class="d-flex justify-space-between flex-column flex-sm-row gap-4">
          <AppSelect
            v-model="selectedPlan"
            :items="plansList"
            :label="$t('components.dialogs.userupgradeplandialog.chooseAPlan')"
            :placeholder="$t('common.messages.basic')"
          />
          <VBtn
            class="align-self-end"
            :block="$vuetify.display.xs"
          >
            {{ $t('components.dialogs.userupgradeplandialog.upgrade') }}
          </VBtn>
        </div>

        <VDivider class="my-6" />

        <p class="text-body-1 mb-1">
          {{ $t('components.dialogs.userupgradeplandialog.userCurrentPlanIsStandardPlan') }}
        </p>
        <div class="d-flex justify-space-between align-center flex-wrap">
          <div class="d-flex align-center gap-1 me-3">
            <sup class="text-body-1 text-primary">$</sup>
            <h1 class="text-h1 text-primary">
              99
            </h1>
            <sub class="text-body-2 mt-5">
              {{ $t('components.dialogs.userupgradeplandialog.month') }}
            </sub>
          </div>
          <VBtn
            color="error"
            variant="tonal"
            @click="isConfirmDialogVisible = true"
          >
            {{ $t('components.dialogs.userupgradeplandialog.cancelSubscription') }}
          </VBtn>
        </div>
      </VCardText>

      <!-- 👉 Confirm Dialog -->
      <ConfirmDialog
        v-model:is-dialog-visible="isConfirmDialogVisible"
        cancel-title="Cancelled"
        confirm-title="Unsubscribed!"
        confirm-msg="Your subscription cancelled successfully."
        confirmation-question="Are you sure to cancel your subscription?"
        cancel-msg="Unsubscription Cancelled!!"
      />
    </VCard>
  </VDialog>
</template>
