<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  supervisorName: string | null
  employeeName: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
  cancel: []
}>()

const clearing = computed(() => !props.supervisorName)

const open = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

function cancel() {
  emit('cancel')
  open.value = false
}

function confirm() {
  emit('confirm')
  open.value = false
}
</script>

<template>
  <VDialog
    v-model="open"
    max-width="560"
    :aria-label="$t('common.ui.supervisorDisclosureTitle')"
  >
    <VCard>
      <VCardTitle class="text-h6">
        {{ clearing ? $t('common.ui.supervisorDisclosureClearTitle') : $t('common.ui.supervisorDisclosureTitle') }}
      </VCardTitle>
      <VCardText class="d-flex flex-column ga-3">
        <p class="mb-0">
          <template v-if="clearing">
            {{ $t('common.ui.supervisorDisclosureClearing') }}
          </template>
          <template v-else>
            {{ $t('common.ui.supervisorDisclosureDescription', { name: supervisorName ?? '' }) }}
          </template>
        </p>

        <VAlert
          v-if="!clearing"
          type="info"
          variant="tonal"
          density="compact"
          class="py-2"
        >
          {{ $t('common.ui.supervisorDisclosureShared') }}
        </VAlert>

        <VAlert
          type="warning"
          variant="tonal"
          density="compact"
          class="py-2"
        >
          {{ $t('common.ui.supervisorDisclosureNotGranted') }}
        </VAlert>

        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('common.ui.supervisorDisclosureSeparate') }}
        </p>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('common.ui.supervisorDisclosureClearing') }}
        </p>
        <p class="text-caption text-medium-emphasis mb-0">
          {{ employeeName }}
        </p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" @click="cancel">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" @click="confirm">
          {{ clearing ? $t('common.ui.supervisorDisclosureClearConfirm') : $t('common.ui.supervisorDisclosureConfirm') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
