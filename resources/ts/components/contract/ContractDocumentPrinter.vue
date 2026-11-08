<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import ContractApi, { type Contract } from '@/api/contracts'
import ContractTemplateApi from '@/api/contractTemplate'
import SettingApi, { type Setting } from '@/api/setting'
import OfficialContractPrintDocument from '@/components/contract/OfficialContractPrintDocument.vue'
import { printDocument } from '@/utils/printDocument'
import { type OfficialCompanyDetails, officialPrintMargins } from '@/utils/officialContractPrint'
import { resolveContractArtwork } from '@/utils/printArtwork'

interface OfficialPrintDocumentExposed { element: HTMLElement | null }

// This printer is intentionally shared by built-in service contracts and the
// Other Contracts register so print choices and issued-document rendering stay identical.
const props = defineProps<{ modelValue: boolean; contract: Contract | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const toast = useToast()
const { t } = useI18n()
const loading = ref(false)
const documentContract = ref<Contract | null>(null)
const setting = ref<Setting | null>(null)
const documentRef = ref<OfficialPrintDocumentExposed | null>(null)
const includeCover = ref(true)
const includeCustomLetterhead = ref(false)
const coverArtworkAvailable = ref(false)
const letterheadArtworkAvailable = ref(false)

const documentDesign = computed(() => documentContract.value?.snapshot_data?.document_design)

const company = computed<OfficialCompanyDetails>(() => ({
  brandName: setting.value?.original_company_name || setting.value?.short_company_name || setting.value?.website_title || t('pages.contracts.shared.officialPrint.company'),
  logo: setting.value?.website_logo_url || undefined,
  address: setting.value?.physical_address || undefined,
  phone: setting.value?.phone || undefined,
  website: setting.value?.website_address || undefined,
  email: setting.value?.company_email || undefined,
  signatureUrl: setting.value?.company_signature_url || undefined,
  letterheadUrl: documentDesign.value?.letterhead_url || setting.value?.contract_letterhead_url || undefined,
  letterheadSafeX: documentDesign.value?.safe_x ?? setting.value?.contract_letterhead_safe_x,
  letterheadSafeY: documentDesign.value?.safe_y ?? setting.value?.contract_letterhead_safe_y,
  letterheadSafeWidth: documentDesign.value?.safe_width ?? setting.value?.contract_letterhead_safe_width,
  letterheadSafeHeight: documentDesign.value?.safe_height ?? setting.value?.contract_letterhead_safe_height,
}))

const printedTitle = computed(() => documentContract.value?.contract || documentContract.value?.contract_template?.name || t('pages.contracts.allContracts.allContracts.untitledContract'))
const coverDesignUrl = computed(() => documentDesign.value?.cover_design_url || setting.value?.contract_cover_design_url || null)
const useLetterhead = computed(() => includeCustomLetterhead.value && letterheadArtworkAvailable.value && Boolean(company.value.letterheadUrl))
const printableCompany = computed<OfficialCompanyDetails>(() => ({ ...company.value, letterheadUrl: useLetterhead.value ? company.value.letterheadUrl : undefined }))
const margins = computed(() => officialPrintMargins(printableCompany.value, useLetterhead.value))

const pageStyle = computed(() => {
  if (useLetterhead.value)
    return 'size: A4; margin: 0;'

  const { top, right, bottom, left } = margins.value

  return `size: A4; margin: ${top}mm ${right}mm ${bottom}mm ${left}mm;`
})

const printOptions = computed(() => [
  {
    key: 'cover' as const,
    label: t('pages.contracts.shared.officialPrint.includeCoverPage'),
    checked: includeCover.value,
    disabled: false,
  },
  {
    key: 'letterhead' as const,
    label: t('pages.contracts.shared.officialPrint.useCustomLetterhead'),
    checked: includeCustomLetterhead.value,
    disabled: !letterheadArtworkAvailable.value,
  },
].sort((left, right) => Number(right.checked) - Number(left.checked)))

const updatePrintOption = (key: 'cover' | 'letterhead', checked: boolean) => {
  if (key === 'cover')
    includeCover.value = checked
  else
    includeCustomLetterhead.value = checked
}

const resetPrintOptions = () => {
  includeCover.value = false
  includeCustomLetterhead.value = letterheadArtworkAvailable.value && Boolean(company.value.letterheadUrl)
}

const loadPrintArtwork = async () => {
  const availability = await resolveContractArtwork({ cover: coverDesignUrl.value, letterhead: company.value.letterheadUrl })

  coverArtworkAvailable.value = availability.cover
  letterheadArtworkAvailable.value = availability.letterhead

  if ((coverDesignUrl.value && !availability.cover) || (company.value.letterheadUrl && !availability.letterhead))
    toast.info(t('pages.contracts.allContracts.allContracts.artworkUnavailable'))
}

watch(() => [props.modelValue, props.contract] as const, async ([open, contract]) => {
  if (!open || !contract)
    return

  loading.value = true
  documentContract.value = null
  try {
    let printable = contract
    try {
      printable = await ContractApi.getById(contract.id)
    }
    catch (error) {
      console.warn('Failed to refresh the contract before printing, using the listed row.', error)
    }

    if (!printable.contract_template && printable.template_id) {
      const template = await ContractTemplateApi.getById(printable.template_id)

      printable.contract_template = { id: template.id, name: template.name, type: template.type, type_slug: template.type_slug, cover_text: template.cover_text, content: template.content }
    }
    documentContract.value = printable
  }
  catch (error) {
    console.error('Failed to load the contract for printing:', error)
    toast.warning(t('pages.contracts.shared.officialPrint.contractLoadFallback'))
    documentContract.value = contract
  }

  try {
    setting.value = (await SettingApi.getCompanyProfile()).data
  }
  catch {
    setting.value = null
  }

  await loadPrintArtwork()
  resetPrintOptions()
  loading.value = false
}, { immediate: true })

const dialogOpen = computed(() => props.modelValue && !loading.value && documentContract.value !== null)

const executePrint = async () => {
  const element = documentRef.value?.element
  if (!element)
    return

  await printDocument({
    bodyClass: 'printing-contract-document',
    pageStyle: pageStyle.value,
    element,
    printPageSelector: '.official-contract-print-page',
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="dialogOpen"
    max-width="520"
    content-class="contract-print-dialog-overlay"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      class="contract-print-dialog"
      rounded="lg"
    >
      <div class="contract-print-dialog__hero">
        <VAvatar
          class="contract-print-dialog__seal"
          size="52"
          rounded="lg"
        >
          <VIcon
            icon="tabler-file-description"
            size="28"
          />
        </VAvatar>
        <div class="flex-grow-1">
          <h2 class="text-h5 font-weight-bold mb-1">
            {{ $t('contractPrintDialog.title') }}
          </h2>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('contractPrintDialog.subtitle') }}
          </p>
        </div>
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="contract-print-dialog__close"
          :aria-label="$t('common.actions.close')"
          @click="emit('update:modelValue', false)"
        />
      </div>
      <VCardText>
        <div class="contract-print-dialog__document mb-5">
          <VIcon
            icon="tabler-file-description"
            color="primary"
            size="24"
          />
          <div class="contract-print-dialog__identity">
            <strong class="text-body-1 font-weight-medium">{{ printedTitle }}</strong><span
              v-if="documentContract?.contract_number"
              class="text-caption text-medium-emphasis"
            >{{ documentContract.contract_number }}</span>
          </div>
          <VChip
            color="primary"
            variant="tonal"
            size="small"
          >
            A4
          </VChip>
        </div>
        <h3 class="text-subtitle-2 mb-2">
          {{ $t('pages.contracts.allContracts.allContracts.printContractOptions') }}
        </h3>
        <div class="contract-print-dialog__options d-flex flex-column gap-1">
          <VCheckbox
            v-for="option in printOptions"
            :key="option.key"
            :model-value="option.checked"
            :label="option.label"
            :disabled="option.disabled"
            density="compact"
            hide-details
            @update:model-value="updatePrintOption(option.key, Boolean($event))"
          />
        </div>
      </VCardText>
      <VCardActions class="contract-print-dialog__actions px-6 py-3 gap-3">
        <VBtn
          class="contract-print-dialog__action contract-print-dialog__cancel"
          variant="flat"
          color="secondary"
          size="default"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          class="contract-print-dialog__action contract-print-dialog__print"
          color="primary"
          variant="flat"
          size="default"
          prepend-icon="tabler-printer"
          @click="executePrint"
        >
          {{ $t('pages.contracts.allContracts.allContracts.printA4Document') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <OfficialContractPrintDocument
    v-if="documentContract"
    ref="documentRef"
    style="display: none;"
    :contract="documentContract"
    :company="printableCompany"
    :include-cover="includeCover"
    :use-letterhead="useLetterhead"
    :cover-url="coverArtworkAvailable ? coverDesignUrl : null"
    :margins="margins"
  />
</template>

<style scoped>
.contract-print-dialog { border-block-start: 3px solid rgb(var(--v-theme-primary)); }
.contract-print-dialog__hero { display: flex; align-items: center; gap: 16px; padding: 16px 24px; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .035); background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%) !important; }
.contract-print-dialog__seal { flex-shrink: 0; color: rgb(var(--v-theme-primary)); background: rgb(var(--v-theme-surface)); border: 1px solid rgb(var(--v-theme-primary) / .18); box-shadow: 0 4px 16px rgb(var(--v-theme-primary) / .1); }
.contract-print-dialog__document { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid rgba(var(--v-theme-primary), .26); border-radius: 10px; background-color: rgba(var(--v-theme-primary), .13) !important; }
.contract-print-dialog__identity { display: flex; flex: 1; flex-direction: column; min-inline-size: 0; overflow-wrap: anywhere; }
.contract-print-dialog__options :deep(.v-label) { opacity: .85; font-size: .875rem; white-space: normal; }
.contract-print-dialog__actions { justify-content: space-between; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .035); background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%) !important; }
.contract-print-dialog__close { flex: 0 0 auto; border: 1px solid rgba(var(--v-theme-error), .38); border-radius: 50%; block-size: 36px; color: rgb(var(--v-theme-error)); inline-size: 36px; }
.contract-print-dialog__close:hover { background-color: rgba(var(--v-theme-error), .18) !important; }
.contract-print-dialog__action { min-height: 38px; font-size: .8125rem; font-weight: 600; letter-spacing: 0; }
.contract-print-dialog__cancel { border: 1px solid rgb(var(--v-theme-secondary)); background-color: rgb(var(--v-theme-secondary) / .12) !important; color: rgb(var(--v-theme-secondary)) !important; transition: background-color 160ms ease, color 160ms ease; }
.contract-print-dialog__cancel:hover { background-color: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.contract-print-dialog__print { padding-inline: 22px; box-shadow: 0 4px 14px rgb(var(--v-theme-primary) / .22); }
.contract-print-dialog__print:hover { box-shadow: 0 6px 20px rgb(var(--v-theme-primary) / .3); }
@media (max-width: 399px) { .contract-print-dialog__hero { padding: 20px; gap: 12px; } .contract-print-dialog__actions { flex-wrap: wrap; } .contract-print-dialog__print { flex: 1; } }
</style>

<style>
.v-overlay-container:has(.contract-print-dialog-overlay) .v-overlay__scrim { backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
</style>
