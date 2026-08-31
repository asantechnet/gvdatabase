<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import ContractApi, { type Contract } from '@/api/contracts'
import ContractTemplateApi from '@/api/contractTemplate'
import SettingApi, { type Setting } from '@/api/setting'
import { legacyContentToHtml } from '@/utils/contractPlaceholders'
import ContractCover from '@/pages/templates/contract_cover_template.vue'
import ContractPageTemplate, { type CompanyDetails } from '@/pages/templates/contract_page_template.vue'
import { canPrintPartyDetails, partyDetailsFor } from '@/utils/contractPartyDetails'

const props = defineProps<{ modelValue: boolean; contract: Contract | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const loading = ref(false)
const documentContract = ref<Contract | null>(null)
const setting = ref<Setting | null>(null)
const printRef = ref<HTMLElement | null>(null)
const includePartyDetails = ref(false)
const { t } = useI18n()
const tr = (key: string) => t(`pages.contracts.shared.${key}`)
const contractLabel = () => t('common.messages.contract')
const company = computed<CompanyDetails>(() => ({ brandName: setting.value?.original_company_name || setting.value?.short_company_name || setting.value?.website_title || 'Company', logo: setting.value?.website_logo_url || undefined, address: setting.value?.physical_address || undefined, phone: setting.value?.phone || undefined, email: setting.value?.company_email || undefined, website: setting.value?.website_address || undefined, letterheadUrl: setting.value?.contract_letterhead_url || undefined, letterheadSafeX: setting.value?.contract_letterhead_safe_x, letterheadSafeY: setting.value?.contract_letterhead_safe_y, letterheadSafeWidth: setting.value?.contract_letterhead_safe_width, letterheadSafeHeight: setting.value?.contract_letterhead_safe_height, signatureUrl: setting.value?.company_signature_url || undefined }))
const pages = computed(() => { const content = legacyContentToHtml(documentContract.value?.content ?? documentContract.value?.contract_template?.content); return content ? [content] : [`<h1>${tr('contractAgreement')}</h1><p>${tr('defaultTerms')}</p>`] })
const canShowPartyDetails = computed(() => documentContract.value !== null && canPrintPartyDetails(documentContract.value))

watch(() => [props.modelValue, props.contract] as const, async ([open, contract]) => {
  if (!open || !contract) return
  loading.value = true
  try {
    const fresh = await ContractApi.getById(contract.id)
    if (!fresh.contract_template && fresh.template_id) fresh.contract_template = await ContractTemplateApi.getById(fresh.template_id)
    documentContract.value = fresh
    includePartyDetails.value = false
    setting.value = (await SettingApi.getCompanyProfile()).data
  } finally { loading.value = false }
}, { immediate: true })

async function print() {
  await nextTick()
  if (!printRef.value || !documentContract.value) return
  const popup = window.open('', '_blank', 'width=900,height=1100')
  if (!popup) return
  const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]')).map(node => node.outerHTML).join('\n')
  popup.document.write(`<!doctype html><html><head><title>${documentContract.value.contract_number}</title>${styles}<style>@media print{@page{size:A4;margin:0}body{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}.contract-cover,.contract-page-container,.custom-cover-page{box-shadow:none!important;page-break-after:always!important;break-after:page!important}.custom-cover-page{width:210mm!important;height:297mm!important}.custom-cover-page img{width:100%!important;height:100%!important;object-fit:cover!important}}</style></head><body>${printRef.value.innerHTML}<script>onload=()=>{print();close()}<\/script></body></html>`)
  popup.document.close()
}
</script>

<template>
  <VDialog :model-value="modelValue" max-width="900" @update:model-value="emit('update:modelValue', $event)">
    <VCard :loading="loading"><VCardTitle>{{ $t('pages.contracts.allContracts.allContracts.printContract') }}</VCardTitle><VCardText v-if="documentContract"><p class="text-medium-emphasis">{{ tr('printPreview') }}</p><VCheckbox v-if="canShowPartyDetails" v-model="includePartyDetails" :label="tr('includePartyDetails')" density="compact" hide-details class="mb-4" /><div class="contract-print-preview"><div v-if="setting?.contract_cover_design_url" class="custom-cover-page"><img :src="setting.contract_cover_design_url" alt="" /></div><ContractCover v-else :logo="company.logo" :contract-title="documentContract.contract || documentContract.contract_template?.name || contractLabel()" :contract-number="documentContract.contract_number || ''" :contractee-name="documentContract.counterparty || ''" :company-address="company.address || ''" :company-email="company.email || ''" :company-number="company.phone || ''" /><ContractPageTemplate v-if="includePartyDetails && canShowPartyDetails" :document-title="tr('contractParties')" :document-number="documentContract.contract_number || ''" :company="company" :contractee-name="documentContract.counterparty || ''" :party-details="partyDetailsFor(documentContract, company)" /><ContractPageTemplate v-for="(page, index) in pages" :key="index" :document-number="documentContract.contract_number || ''" :document-title="documentContract.contract || documentContract.contract_template?.name || contractLabel()" :company="company" :html-content="page" :contractee-name="documentContract.counterparty || ''" /></div></VCardText><VCardActions><VSpacer /><VBtn variant="text" @click="emit('update:modelValue', false)">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" prepend-icon="tabler-printer" :disabled="!documentContract" @click="print">{{ $t('pages.contracts.allContracts.allContracts.printContract') }}</VBtn></VCardActions></VCard>
  </VDialog>
  <div ref="printRef" class="d-none"><template v-if="documentContract"><div v-if="setting?.contract_cover_design_url" class="custom-cover-page"><img :src="setting.contract_cover_design_url" alt="" /></div><ContractCover v-else :logo="company.logo" :contract-title="documentContract.contract || documentContract.contract_template?.name || contractLabel()" :contract-number="documentContract.contract_number || ''" :contractee-name="documentContract.counterparty || ''" :company-address="company.address || ''" :company-email="company.email || ''" :company-number="company.phone || ''" /><ContractPageTemplate v-if="includePartyDetails && canShowPartyDetails" :document-title="tr('contractParties')" :document-number="documentContract.contract_number || ''" :company="company" :contractee-name="documentContract.counterparty || ''" :party-details="partyDetailsFor(documentContract, company)" /><ContractPageTemplate v-for="(page, index) in pages" :key="index" :document-number="documentContract.contract_number || ''" :document-title="documentContract.contract || documentContract.contract_template?.name || contractLabel()" :company="company" :html-content="page" :contractee-name="documentContract.counterparty || ''" /></template></div>
</template>

<style scoped>
.custom-cover-page {
  width: 100%;
  max-width: 800px;
  aspect-ratio: 210 / 297;
  margin: 20px auto;
  overflow: hidden;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.custom-cover-page img { width: 100%; height: 100%; display: block; object-fit: cover; }
</style>
