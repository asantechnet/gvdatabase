<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import AcademySlipApi, { type AcademySlipPayload } from '@/api/academySlip'
import AcademySlipTemplate, { type AcademySlipPaperSize } from '@/pages/templates/academy_slip_template.vue'

const props = defineProps<{ modelValue: boolean; contractId: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const toast = useToast()
const slip = ref<AcademySlipPayload | null>(null)
const templateRef = ref<InstanceType<typeof AcademySlipTemplate> | null>(null)

// Academy enrolment slips are always two A5 copies on one A4 sheet. The
// source is mounted only long enough to produce that document; there is no
// preview modal between the table action and the browser print dialog.
const paperSize: AcademySlipPaperSize = 'A4 2x A5'
const copies = 2
let requestGeneration = 0

watch(() => [props.modelValue, props.contractId] as const, async ([open, contractId]) => {
  const generation = ++requestGeneration
  slip.value = null
  if (!open || !contractId)
    return

  try {
    const result = await AcademySlipApi.getForContract(Number(contractId))
    if (generation !== requestGeneration)
      return

    slip.value = result
    await nextTick()
    if (!templateRef.value)
      throw new Error('documents.academySlip.printFailed')

    await templateRef.value.print()
  }
  catch (error: any) {
    if (generation === requestGeneration)
      toast.error(error?.response?.data?.message || t('documents.academySlip.printFailed'))
  }
  finally {
    if (generation === requestGeneration)
      emit('update:modelValue', false)
  }
}, { immediate: true })
</script>

<template>
  <!-- `printAcademySlip` clones this off-screen source into a namespaced print
       host immediately before invoking the browser dialog. -->
  <div class="academy-slip-source" aria-hidden="true">
    <AcademySlipTemplate
      v-if="slip"
      ref="templateRef"
      :slip="slip"
      :copies="copies"
      :paper-size="paperSize"
    />
  </div>
</template>

<style scoped>
.academy-slip-source { display: none; }
</style>
