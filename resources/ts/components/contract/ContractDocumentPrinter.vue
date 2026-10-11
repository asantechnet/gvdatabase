<script setup lang="ts">
import { printDocument } from '@/utils/printDocument'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import ContractApi, { type Contract } from '@/api/contracts'
import ContractTemplateApi from '@/api/contractTemplate'
import SettingApi, { type Setting } from '@/api/setting'
import { legacyContentToHtml } from '@/utils/contractPlaceholders'
import { resolveContractArtwork } from '@/utils/printArtwork'
import { canPrintPartyDetails, partyDetailsFor } from '@/utils/contractPartyDetails'
import { buildContractPrintHeader, canPrintContractHeader } from '@/composables/useContractPrintHeader'
import ContractCover from '@/pages/templates/contract_cover_template.vue'
import ContractPageTemplate, { type CompanyDetails } from '@/pages/templates/contract_page_template.vue'

// The print dialog is shared by the contracts workspace and the visa case page
// so an individual contract prints identically wherever it is opened from.
const props = defineProps<{ modelValue: boolean; contract: Contract | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const toast = useToast()
const { t } = useI18n()
const tr = (key: string) => t(`pages.contracts.shared.${key}`)

const loading = ref(false)
const documentContract = ref<Contract | null>(null)
const setting = ref<Setting | null>(null)
const printRef = ref<HTMLElement | null>(null)

// Print options — mirror the contracts workspace defaults.
const includeEmptyLastPage = ref(false)
const includeSignatureSection = ref(true)
const includeCustomCover = ref(true)
const includeCustomLetterhead = ref(true)
const includePartyDetails = ref(false)
const includeContractHeader = ref(false)

// Configured artwork whose file is gone must never replace the built-in cover
// or letterhead with a blank page, so availability is resolved before the
// print body is rendered (and therefore before it is cloned for printing).
const coverArtworkAvailable = ref(true)
const letterheadArtworkAvailable = ref(true)

const documentDesign = computed(() => documentContract.value?.snapshot_data?.document_design)
const canShowPartyDetails = computed(() => documentContract.value !== null && canPrintPartyDetails(documentContract.value))
const canShowContractHeader = computed(() => documentContract.value !== null && canPrintContractHeader(documentContract.value))
const contractHeader = computed(() => (documentContract.value ? buildContractPrintHeader(documentContract.value) : null))

const company = computed<CompanyDetails>(() => ({
  brandName: setting.value?.original_company_name || setting.value?.short_company_name || setting.value?.website_title || 'Company',
  logo: setting.value?.website_logo_url || undefined,
  address: setting.value?.physical_address || undefined,
  phone: setting.value?.phone || undefined,
  website: setting.value?.website_address || undefined,
  email: setting.value?.company_email || undefined,
  licence: setting.value?.business_licence || undefined,
  signatureUrl: setting.value?.company_signature_url || undefined,
  letterheadUrl: documentDesign.value?.letterhead_url || setting.value?.contract_letterhead_url || undefined,
  letterheadSafeX: documentDesign.value?.safe_x ?? setting.value?.contract_letterhead_safe_x,
  letterheadSafeY: documentDesign.value?.safe_y ?? setting.value?.contract_letterhead_safe_y,
  letterheadSafeWidth: documentDesign.value?.safe_width ?? setting.value?.contract_letterhead_safe_width,
  letterheadSafeHeight: documentDesign.value?.safe_height ?? setting.value?.contract_letterhead_safe_height,
}))

const versionLabel = computed(() => {
  const version = documentContract.value?.snapshot_data?.template_version
  if (!version?.version_number) return null
  return [version.name, `v${version.version_number}`, version.country_name, version.language_name].filter(Boolean).join(' - ')
})

const printedTitle = computed(() => {
  const name = documentContract.value?.contract || documentContract.value?.contract_template?.name || translate('pages.contracts.allContracts.allContracts.untitledContract')
  return versionLabel.value ? `${name} - ${versionLabel.value}` : name
})

const coverDesignUrl = computed(() => documentDesign.value?.cover_design_url || setting.value?.contract_cover_design_url)
const useCustomCover = computed(() => includeCustomCover.value && Boolean(coverDesignUrl.value) && coverArtworkAvailable.value)
const printableCompany = computed<CompanyDetails>(() => ({
  ...company.value,
  letterheadUrl: includeCustomLetterhead.value && letterheadArtworkAvailable.value ? company.value.letterheadUrl : undefined,
}))

// The print body renders once per option change; resolving the pages here keeps
// the render function from rebuilding the same HTML for every page binding.
const printedPages = computed(() => (documentContract.value ? getContractPagesHtml(documentContract.value) : []))

const getContractPagesHtml = (contract: Contract): string[] => {
  // Contracts retain their own content snapshot so later template edits never
  // rewrite a document that has already been issued.
  const html = legacyContentToHtml(contract.content)
  if (html)
    return [html]

  const startDate = formatLocalizedDate(contract.start_date)
  const contractName = contract.contract || contract.contract_template?.name || translate('pages.contracts.allContracts.allContracts.untitledContract')

  return [
    `
    <h1>${translate('pages.contracts.shared.contractAgreement')}</h1>
    <p>${translate('pages.contracts.shared.fallbackOpening')} <strong>${company.value.brandName}</strong> ${translate('pages.contracts.shared.and')} <strong>${contract.counterparty}</strong> on <strong>${startDate}</strong>.</p>

    <h2>1. ${translate('pages.contracts.shared.contractParties')}</h2>
    <p><strong>${translate('pages.contracts.shared.serviceProvider')}:</strong> ${company.value.brandName}, ${translate('pages.contracts.shared.locatedAt')} ${company.value.address || translate('pages.contracts.shared.registeredCompanyAddress')}</p>
    <p><strong>${translate('pages.contracts.shared.client')}:</strong> ${contract.counterparty}</p>

    <h2>2. ${translate('pages.contracts.shared.contractDetails')}</h2>
    <p><strong>${translate('pages.contracts.shared.contractName')}:</strong> ${contractName}</p>
    <p><strong>${translate('pages.contracts.shared.status')}:</strong> ${contract.status}</p>
    <p><strong>${translate('pages.contracts.shared.startDate')}:</strong> ${startDate}</p>
    `,
    `
    <h2>3. ${translate('pages.contracts.shared.termsAndConditions')}</h2>
    <p>${translate('pages.contracts.shared.partiesAgree')}</p>
    <ol>
      <li>${translate('pages.contracts.shared.obligationsTerm')}</li>
      <li>${translate('pages.contracts.shared.changesTerm')}</li>
      <li>${translate('pages.contracts.shared.durationTerm')}</li>
    </ol>

    <h2>4. ${translate('pages.contracts.shared.termination')}</h2>
    <p>${translate('pages.contracts.shared.terminationNotice')}</p>
    `,
  ]
}

const resetPrintOptions = () => {
  includeEmptyLastPage.value = false
  includeSignatureSection.value = true
  includePartyDetails.value = false
  includeContractHeader.value = false
}

const loadPrintArtwork = async () => {
  coverArtworkAvailable.value = true
  letterheadArtworkAvailable.value = true

  const { cover, letterhead } = await resolveContractArtwork({
    cover: coverDesignUrl.value,
    letterhead: company.value.letterheadUrl,
  })

  coverArtworkAvailable.value = cover
  letterheadArtworkAvailable.value = letterhead

  if ((coverDesignUrl.value && !cover) || (company.value.letterheadUrl && !letterhead))
    toast.info(t('pages.contracts.allContracts.allContracts.artworkUnavailable'))
}

watch(() => [props.modelValue, props.contract] as const, async ([open, contract]) => {
  if (!open || !contract)
    return

  loading.value = true
  try {
    // The print body renders from the freshest contract: the server resolves
    // the artwork scoped to this contract's type (and service) — including the
    // print-safe area — for contracts that carry no design snapshot yet.
    let printable = contract

    try {
      printable = await ContractApi.getById(contract.id)
    }
    catch (error) {
      console.warn('Failed to refresh the contract before printing, using the listed row.', error)
    }

    if (!printable.contract_template && printable.template_id) {
      const template = await ContractTemplateApi.getById(printable.template_id)
      printable.contract_template = {
        id: template.id,
        name: template.name,
        type: template.type,
        cover_text: template.cover_text,
        content: template.content,
      }
    }

    documentContract.value = printable
    resetPrintOptions()
  }
  catch (error) {
    console.error('Failed to load contract template:', error)
    toast.warning(translate('pages.contracts.allContracts.allContracts.failedToLoadContractTemplateUsingDefaultContent'))
    documentContract.value = contract
    resetPrintOptions()
  }

  try {
    setting.value = (await SettingApi.getCompanyProfile()).data
  }
  catch {
    // Printing still works with neutral fallback company details.
  }

  // Only publish the dialog once every input it shows is final: the loaded
  // contract, the company profile and the artwork availability. Rendering it
  // earlier leaves the dialog with a stale contract name and checkbox set.
  await loadPrintArtwork()
  loading.value = false
}, { immediate: true })

const dialogOpen = computed(() => props.modelValue && !loading.value && documentContract.value !== null)

const executePrint = async () => {
  if (!printRef.value) return
  await printDocument({ bodyClass: 'printing-contract-document', pageStyle: 'size: A4; margin: 0;', element: printRef.value })
  emit('update:modelValue', false)
}

</script>

<template>
  <VDialog
    :model-value="dialogOpen"
    max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-printer" />
        <span>{{ $t('pages.contracts.allContracts.allContracts.printContractOptions') }}</span>
      </VCardTitle>
      <VCardText>
        <p class="mb-4">
          {{ $t('pages.contracts.allContracts.allContracts.prepare') }} <strong>{{ documentContract?.contract || documentContract?.contract_template?.name
            || $t('pages.contracts.allContracts.allContracts.untitledContract') }}</strong> {{ $t('pages.contracts.allContracts.allContracts.forA4PrintingTheDocumentWillIncludeTheCoverPageAndAllContractTerms') }}
        </p>
        <div class="d-flex flex-column gap-2">
          <VCheckbox
            v-model="includeSignatureSection"
            :label="$t('pages.contracts.allContracts.allContracts.includeFormalSignatureStampSectionAtTheEnd')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-model="includeCustomCover"
            :label="$t('pages.contracts.allContracts.allContracts.useCustomCoverPageFromSettings')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-model="includeCustomLetterhead"
            :label="$t('pages.contracts.allContracts.allContracts.useCustomLetterheadFromSettings')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-if="canShowPartyDetails"
            v-model="includePartyDetails"
            :label="tr('includePartyDetails')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-if="canShowContractHeader"
            v-model="includeContractHeader"
            :label="tr('header.includeContractHeader')"
            :hint="tr('header.includeContractHeaderHint')"
            density="compact"
            hide-details
            persistent-hint
          />
          <VCheckbox
            v-model="includeEmptyLastPage"
            :label="$t('pages.contracts.allContracts.allContracts.addALastFullyEmptyPage')"
            density="compact"
            hide-details
          />
        </div>
      </VCardText>
      <VCardActions class="justify-end px-4 pb-4">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :disabled="!documentContract"
          @click="executePrint"
        >
          {{ $t('pages.contracts.allContracts.allContracts.printA4Document') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Hidden printable document structure -->
  <div
    ref="printRef"
    style="display: none;"
  >
    <template v-if="documentContract">
      <div
        v-if="useCustomCover"
        class="custom-cover-page"
      >
        <img
          :src="coverDesignUrl || ''"
          alt=""
          @error="coverArtworkAvailable = false"
        >
      </div>
      <ContractCover
        v-else
        :contractee-name="documentContract.counterparty || ''"
        :logo="company.logo"
        cover-design-url=""
        :contract-number="documentContract.contract_number || ''"
        :contract-title="printedTitle"
        :cover-description="documentContract.cover_text || documentContract.contract_template?.cover_text || ''"
        :company-website="company.website || ''"
        :company-address="company.address || ''"
        :company-email="company.email || ''"
        :company-number="company.phone || ''"
      />

      <ContractPageTemplate
        v-for="(pageHtml, idx) in printedPages"
        :key="idx"
        :document-number="documentContract.contract_number || ''"
        :document-title="printedTitle"
        :company="printableCompany"
        :header-details="includeContractHeader && idx === 0 ? contractHeader : null"
        :party-details="idx === 0 && includePartyDetails && canShowPartyDetails ? partyDetailsFor(documentContract, company) : undefined"
        :html-content="pageHtml"
        :page-number-string="$t('pages.contracts.allContracts.allContracts.pageNumber', { page: idx + 1, total: printedPages.length })"
        :show-signature="includeSignatureSection && idx === printedPages.length - 1"
        :contractee-name="documentContract.counterparty || ''"
        :company-signatory-name="documentContract.company_signatory_name || null"
        :footer-text="$t('pages.contracts.allContracts.allContracts.confidentialAllRightsReserved')"
        class="contract-content"
      />

      <div
        v-if="includeEmptyLastPage"
        class="empty-print-page"
        style="width: 210mm; height: 297mm; background: white;"
      />
    </template>
  </div>
</template>
