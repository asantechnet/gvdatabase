<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ from?: string; to?: string; disabled?: boolean }>()
const emit = defineEmits<{ apply: [range: { date_from?: string; date_to?: string }] }>()
const { t } = useI18n()
const open = ref(false)
const from = ref('')
const to = ref('')
const invalid = computed(() => !!from.value && !!to.value && from.value > to.value)
const active = computed(() => !!props.from || !!props.to)
function resetDraft(isOpen: boolean) {
  if (isOpen) { from.value = props.from ?? ''; to.value = props.to ?? '' }
}
function apply(clear = false) {
  if (!clear && invalid.value) return
  emit('apply', clear ? {} : { date_from: from.value || undefined, date_to: to.value || undefined })
  open.value = false
}
</script>

<template>
  <VMenu v-model="open" :close-on-content-click="false" location="bottom end" :max-width="460" @update:model-value="resetDraft">
    <template #activator="{ props: activatorProps }">
      <VBtn v-bind="activatorProps" variant="tonal" size="small" prepend-icon="tabler-adjustments-horizontal" :disabled="disabled" :color="active ? 'primary' : undefined">
        {{ t('educational.filters') }}
        <VChip v-if="active" size="x-small" class="ms-2">{{ Number(!!props.from) + Number(!!props.to) }}</VChip>
      </VBtn>
    </template>
    <VCard class="date-filter-menu">
      <VCardText>
        <form class="date-filter-form" @submit.prevent="apply()">
          <VTextField v-model="from" type="date" :label="t('erp.fromDate')" :max="to || undefined" hide-details="auto" />
          <VTextField v-model="to" type="date" :label="t('erp.toDate')" :min="from || undefined" hide-details="auto" :error-messages="invalid ? t('erp.invalidRange') : []" />
          <div class="d-flex gap-2">
            <VBtn type="submit" :disabled="invalid" color="primary">{{ t('educational.apply') }}</VBtn>
            <VBtn variant="tonal" @click="apply(true)">{{ t('educational.clear') }}</VBtn>
          </div>
        </form>
      </VCardText>
    </VCard>
  </VMenu>
</template>

<style scoped>
.date-filter-menu { inline-size: min(460px, calc(100vw - 24px)); }
.date-filter-form { display: grid; gap: 16px; }
</style>
