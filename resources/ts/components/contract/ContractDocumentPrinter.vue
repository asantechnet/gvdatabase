<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import AuthApi from '@/api/auth'
import ContractApi, { type Contract } from '@/api/contracts'
import ContractTemplateApi from '@/api/contractTemplate'
import SettingApi, { type Setting } from '@/api/setting'
import { legacyContentToHtml } from '@/utils/contractPlaceholders'
import { canPrintPartyDetails, partyDetailsFor } from '@/utils/contractPartyDetails'
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

// Read the live authenticated profile whenever printing so role changes and
// employee links are reflected even when the persisted cookie is stale.
const userData = useCookie<{ roles?: Array<string | { name?: string }>; employee?: { id: number; name?: string } | null } | null>('userData')
const isSuperAdmin = computed(() => userData.value?.roles?.some(role =>
  (typeof role === 'string' ? role : role.name) === 'super_admin') ?? false)
const currentEmployeeName = computed(() =>
  String(userData.value?.employee?.name ?? '').trim()
  || null,
)
const companySignatoryName = ref('')
const signatoryNameFor = (savedName: string | null | undefined) => {
  const storedName = String(savedName ?? '').trim()

  return isSuperAdmin.value
    ? storedName || currentEmployeeName.value || ''
    : currentEmployeeName.value || storedName
}

// Print options — mirror the contracts workspace defaults.
const includeEmptyLastPage = ref(false)
const includeSignatureSection = ref(true)
const includeCustomCover = ref(true)
const includeCustomLetterhead = ref(true)
const includePartyDetails = ref(false)

const documentDesign = computed(() => documentContract.value?.snapshot_data?.document_design)
const canShowPartyDetails = computed(() => documentContract.value !== null && canPrintPartyDetails(documentContract.value))

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

watch(() => [props.modelValue, props.contract] as const, async ([open, contract]) => {
  if (!open || !contract)
    return

  loading.value = true
  try {
    try {
      userData.value = (await AuthApi.me()).data.data
    }
    catch {
      // The cookie remains a usable fallback if the profile refresh fails.
    }

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
    companySignatoryName.value = signatoryNameFor(printable.company_signatory_name)
    includeEmptyLastPage.value = false
    includeSignatureSection.value = true
    includePartyDetails.value = false
  }
  catch (error) {
    console.error('Failed to load contract template:', error)
    toast.warning(translate('pages.contracts.allContracts.allContracts.failedToLoadContractTemplateUsingDefaultContent'))
    documentContract.value = contract
    companySignatoryName.value = signatoryNameFor(contract.company_signatory_name)
    includeEmptyLastPage.value = false
    includeSignatureSection.value = true
    includePartyDetails.value = false
  }
  finally {
    loading.value = false
  }

  try {
    setting.value = (await SettingApi.getCompanyProfile()).data
  }
  catch {
    // Printing still works with neutral fallback company details.
  }
}, { immediate: true })

// Fill the signatory once the employee name resolves if the popup opened
// before it was available.
watch(currentEmployeeName, name => {
  if (props.modelValue && name && (!isSuperAdmin.value || !companySignatoryName.value))
    companySignatoryName.value = name
})

function executePrint() {
  if (!printRef.value || !documentContract.value)
    return

  const printWindow = window.open('', '_blank', 'width=900,height=1100')
  if (!printWindow) {
    toast.warning(translate('common.messages.couldNotOpenPrintWindowPleaseAllowPopUpsForThisSite'))

    return
  }

  const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
    .map(el => el.outerHTML)
    .join('\n')

  const printContent = printRef.value.innerHTML

  printWindow.document.open()
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${documentContract.value.contract_number} - ${documentContract.value.contract || documentContract.value.contract_template?.name || translate('pages.contracts.allContracts.allContracts.untitledContract')}</title>
        ${styles}
        <style>
          @media print {
            @page {
              size: A4;
              margin: 0 !important;
            }
            body {
              margin: 0;
              padding: 0;
              background: #fff;
              -webkit-print-color-adjust: exact !important;
              print-color-adjust: exact !important;
            }
            .contract-cover {
              position: relative !important;
              width: 210mm !important;
              height: 297mm !important;
              max-width: none !important;
              margin: 0 !important;
              box-shadow: none !important;
              page-break-after: always !important;
              break-after: page !important;
              display: flex !important;
            }
            .custom-cover-page {
              position: relative !important;
              width: 210mm !important;
              height: 297mm !important;
              margin: 0 !important;
              page-break-after: always !important;
              break-after: page !important;
              overflow: hidden !important;
            }
            .custom-cover-page img { width: 100% !important; height: 100% !important; object-fit: cover !important; display: block !important; }
            .contract-page-container {
              position: relative !important;
              width: 210mm !important;
              height: 296mm !important;
              max-height: 296mm !important;
              margin: 0 !important;
              padding: 16mm 18mm 12mm 18mm !important;
              box-shadow: none !important;
              page-break-after: always !important;
              break-after: page !important;
              box-sizing: border-box !important;
              display: flex !important;
              flex-direction: column !important;
              justify-content: space-between !important;
              overflow: hidden !important;
            }
            .empty-print-page {
              width: 210mm !important;
              height: 297mm !important;
              page-break-after: always !important;
              break-after: page !important;
              background: #ffffff !important;
              display: block !important;
            }
            .contract-content {
              font-family: 'Vazirmatn', 'Noto Sans Arabic', Tahoma, sans-serif;
              line-height: 1.6;
              color: #333;
            }
            .contract-content h1,
            .contract-content h2,
            .contract-content h3 {
              color: #222;
              margin-top: 1.5em;
              margin-bottom: 0.5em;
            }
            .contract-content p {
              margin-bottom: 1em;
            }
            .contract-content ul,
            .contract-content ol {
              margin-bottom: 1em;
              padding-inline-start: 2em;
            }
            .contract-content table {
              width: 100%;
              border-collapse: collapse;
              margin: 1em 0;
            }
            .contract-content th,
            .contract-content td {
              border: 1px solid #ddd;
              padding: 8px;
              text-align: left;
            }
            .contract-content th {
              background-color: #f2f2f2;
            }
          }
        </style>
      </head>
      <body>
        <div class="multi-page-print-wrapper">
          ${printContent}
        </div>
        <script>
          window.onload = () => {
            const pages = document.querySelectorAll('.contract-page-container');
            pages.forEach(page => {
              let zoom = 1.0;
              while (page.scrollHeight > page.clientHeight && zoom > 0.80) {
                zoom -= 0.02;
                page.style.zoom = zoom;
              }
            });

            const images = document.images;
            let loaded = 0;
            if (images.length === 0) {
              triggerPrint();
              return;
            }
            const checkDone = () => {
              loaded++;
              if (loaded === images.length) triggerPrint();
            };
            for (let i = 0; i < images.length; i++) {
              if (images[i].complete) {
                checkDone();
              } else {
                images[i].addEventListener('load', checkDone);
                images[i].addEventListener('error', checkDone);
              }
            }
          };
          function triggerPrint() {
            setTimeout(() => {
              window.focus();
              window.print();
              window.close();
            }, 300);
          }
        <\/script>
      </body>
    </html>
  `)
  printWindow.document.close()
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
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
          <VTextField
            v-model="companySignatoryName"
            :label="$t('pages.contracts.contractform.contractform.signatory')"
            :hint="$t(isSuperAdmin ? 'pages.contracts.contractform.contractform.signatoryHint' : 'pages.contracts.contractform.contractform.signatoryLockedHint')"
            persistent-hint
            density="compact"
            :disabled="!isSuperAdmin"
          />
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
        v-if="includeCustomCover && coverDesignUrl"
        class="custom-cover-page"
      >
        <img
          :src="coverDesignUrl"
          alt=""
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
        v-if="includePartyDetails && canShowPartyDetails"
        :document-title="$t('pages.contracts.shared.contractParties')"
        :document-number="documentContract.contract_number || ''"
        :company="{ ...company, letterheadUrl: includeCustomLetterhead ? company.letterheadUrl : undefined }"
        :party-details="partyDetailsFor(documentContract, company)"
        :contractee-name="documentContract.counterparty || ''"
        :page-number-string="$t('pages.contracts.allContracts.allContracts.pageNumber', { page: 1, total: getContractPagesHtml(documentContract).length + 1 })"
        :footer-text="$t('pages.contracts.allContracts.allContracts.confidentialAllRightsReserved')"
        class="contract-content"
      />

      <ContractPageTemplate
        v-for="(pageHtml, idx) in getContractPagesHtml(documentContract)"
        :key="idx"
        :document-number="documentContract.contract_number || ''"
        :document-title="printedTitle"
        :company="{ ...company, letterheadUrl: includeCustomLetterhead ? company.letterheadUrl : undefined }"
        :html-content="pageHtml"
        :page-number-string="$t('pages.contracts.allContracts.allContracts.pageNumber', { page: idx + (includePartyDetails && canShowPartyDetails ? 2 : 1), total: getContractPagesHtml(documentContract).length + (includePartyDetails && canShowPartyDetails ? 1 : 0) })"
        :show-signature="includeSignatureSection && idx === getContractPagesHtml(documentContract).length - 1"
        :contractee-name="documentContract.counterparty || ''"
        :company-signatory-name="companySignatoryName || documentContract.company_signatory_name || null"
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
