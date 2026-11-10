<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { type PlaceholderGroup, getPlaceholders } from '@/api/contractPlaceholders'
import ContractPolicyApi, { type ContractPolicy } from '@/api/contractPolicy'
import ContractTemplateApi, { type ContractTemplate, type ContractTemplateCreateData, type ContractTemplateVersion } from '@/api/contractTemplate'
import type { BuiltInService } from '@/api/applicants'
import ContractTypeApi, { type ContractType } from '@/api/contractType'
import PositionApi, { type Position } from '@/api/position'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import ContractContentEditor from '@/components/contract/ContractContentEditor.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { extractPlaceholderKeys, hydratePlaceholders, legacyContentToHtml, localizePlaceholderGroups, serializePlaceholders } from '@/utils/contractPlaceholders'
import { translateBackendValue, translateContractPolicyType, translateContractTypeRecord } from '@/utils/localization'
import { canAccess } from '@/utils/authorization'
import { moveRowIdFirst } from '@/utils/createdFirst'

const route = useRoute()
const toast = useToast()
const { locale, t } = useI18n()
const tr = (key: string, values: Record<string, unknown> = {}) => t(`pages.settings.policies.policies.contractTemplate.${key}`, values)
const props = withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const formRef = ref()
const contractName = ref('')
const contractTypeId = ref<number | null>(null)
const coverText = ref('')
const positionId = ref<number | null>(null)
const builtInService = ref<BuiltInService | null>(null)
const savedBuiltInService = ref<BuiltInService | null>(null)
const builtInServices: BuiltInService[] = ['scholarship', 'test_offer', 'private']
const policyIds = ref<number[]>([])
const contentHtml = ref('')
const selectedPolicyId = ref<number | null>(null)
const contractTypes = ref<ContractType[]>([])
const allPolicies = ref<ContractPolicy[]>([])
const positions = ref<Position[]>([])
const templates = ref<ContractTemplate[]>([])
const templateId = ref<number | null>(null)
const rawPlaceholderGroups = ref<PlaceholderGroup[]>([])
const isLoading = ref(false)
const deleteDialog = ref(false)
const templateToDelete = ref<number | null>(null)
const editorRef = ref<InstanceType<typeof ContractContentEditor> | null>(null)
const pristineTemplateState = ref('')
const countries = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const tests = ref<MasterDataRecord[]>([])
const versions = ref<ContractTemplateVersion[]>([])
const versionsLoading = ref(false)
const versionDialog = ref(false)
const activatingVersionId = ref<number | null>(null)
const versionCountryFilter = ref<number | null>(null)
const versionTestFilter = ref<number | null>(null)
const versionLanguageFilter = ref<number | null>(null)
const versionForm = ref({ name: '', country_id: null as number | null, test_id: null as number | null, language_id: null as number | null })
const initialVersion = ref({ name: '', country_id: null as number | null, test_id: null as number | null, language_id: null as number | null })
const editingVersionId = ref<number | null>(null)
const previewVersion = ref<ContractTemplateVersion | null>(null)
const editorDialog = ref(false)
const templateSearch = ref('')
const filterMenuOpen = ref(false)
const templateTypeFilter = ref<number | null>(null)
const templateServiceFilter = ref<string | null>(null)
const templatePositionFilter = ref<number | null>(null)
const templateCountryFilter = ref<number | null>(null)
const templateLanguageFilter = ref<number | null>(null)
const templateDateFrom = ref('')
const templateDateTo = ref('')
const templateFilterDraft = ref({ type: null as number | null, service: null as string | null, position: null as number | null, country: null as number | null, language: null as number | null, dateFrom: '', dateTo: '' })

const builtInLabel = (service: BuiltInService) => t(`educational.builtInServices.${service === 'test_offer' ? 'testOffer' : service}`)
const contractTypeName = (type: ContractType | null) => translateContractTypeRecord(type)
const templateTypeName = (template: ContractTemplate) => template.type_slug ? translateContractPolicyType(template.type_slug, template.type) : template.type || tr('custom')
const policyTypeName = (policy: ContractPolicy) => translateContractPolicyType(policy.type)
const selectedType = computed(() => contractTypes.value.find(type => type.id === contractTypeId.value) ?? null)
const isEmployment = computed(() => selectedType.value?.slug === 'employment')
const isServiceType = computed(() => selectedType.value?.slug === 'customer')
const isOtherType = computed(() => selectedType.value?.slug === 'other')
const isTestOffer = computed(() => builtInService.value === 'test_offer')
const serviceBindingLocked = computed(() => Boolean(templateId.value && savedBuiltInService.value))
const serviceItems = computed(() => builtInServices.map(service => ({ title: builtInLabel(service), value: service, props: { disabled: templates.value.some(template => template.service === service && template.id !== templateId.value) } })))
const placeholderGroups = computed(() => localizePlaceholderGroups(rawPlaceholderGroups.value))
const knownKeys = computed(() => new Set(placeholderGroups.value.flatMap(group => group.fields.map(field => field.key))))
const policies = computed(() => allPolicies.value.filter(policy => policy.is_active && policy.type === selectedType.value?.slug && (!policy.service || policy.service === builtInService.value) && (!policy.position_id || policy.position_id === positionId.value) && (!policy.country_id || policy.country_id === (templateId.value ? versionCountryFilter.value : initialVersion.value.country_id)) && (!policy.language_id || policy.language_id === (templateId.value ? versionLanguageFilter.value : initialVersion.value.language_id)) && !policyIds.value.includes(policy.id)))
const editingVersion = computed(() => versions.value.find(version => version.id === editingVersionId.value) ?? null)
const visibleVersions = computed(() => versions.value.filter(version => (!versionCountryFilter.value || version.country_id === versionCountryFilter.value) && (!versionTestFilter.value || version.test_id === versionTestFilter.value) && (!versionLanguageFilter.value || version.language_id === versionLanguageFilter.value)))
const hasTemplateChanges = computed(() => JSON.stringify({ name: contractName.value, type: contractTypeId.value, position: positionId.value, service: builtInService.value, policies: policyIds.value, cover: coverText.value, content: serializePlaceholders(contentHtml.value), editingVersion: editingVersion.value?.id, initialVersion: templateId.value ? null : initialVersion.value }) !== pristineTemplateState.value)
const canCreateVersions = computed(() => canAccess('create_contract_template_versions'))
const canEditDraftVersions = computed(() => canAccess('edit_draft_contract_template_versions'))
const canActivateVersions = computed(() => canAccess('activate_contract_template_versions'))
const canArchiveVersions = computed(() => canAccess('archive_contract_template_versions'))
const versionCountryName = (version: ContractTemplateVersion) => version.country?.name ?? countries.value.find(country => country.id === version.country_id)?.name ?? '—'
const versionTestName = (version: ContractTemplateVersion) => version.test?.name ?? tests.value.find(test => test.id === version.test_id)?.name ?? '—'
const versionLanguageName = (version: ContractTemplateVersion) => version.language?.name ?? languages.value.find(language => language.id === version.language_id)?.name ?? '—'
const filteredTemplates = computed(() => templates.value.filter(template => {
  const search = templateSearch.value.trim().toLowerCase()
  const templateVersions = template.versions ?? []
  return (!search || template.name.toLowerCase().includes(search) || templateTypeName(template).toLowerCase().includes(search))
    && (!templateTypeFilter.value || template.contract_type_id === templateTypeFilter.value)
    && (!templateServiceFilter.value || template.service === templateServiceFilter.value)
    && (!templatePositionFilter.value || template.position_id === templatePositionFilter.value)
    && (!templateDateFrom.value || template.created_at.slice(0, 10) >= templateDateFrom.value)
    && (!templateDateTo.value || template.created_at.slice(0, 10) <= templateDateTo.value)
    && (!templateCountryFilter.value || templateVersions.some(version => version.country_id === templateCountryFilter.value))
    && (!templateLanguageFilter.value || templateVersions.some(version => version.language_id === templateLanguageFilter.value))
}))

const snapshot = () => JSON.stringify({ name: contractName.value, type: contractTypeId.value, position: positionId.value, service: builtInService.value, policies: policyIds.value, cover: coverText.value, content: serializePlaceholders(contentHtml.value), editingVersion: editingVersion.value?.id, initialVersion: templateId.value ? null : initialVersion.value })
const markTemplatePristine = () => {
  pristineTemplateState.value = snapshot()
  nextTick(() => (formRef.value?.$el as HTMLFormElement | undefined)?.dispatchEvent(new Event('app-form-pristine', { bubbles: true })))
}
const syncTemplateFilterDraft = () => {
  templateFilterDraft.value = { type: templateTypeFilter.value, service: templateServiceFilter.value, position: templatePositionFilter.value, country: templateCountryFilter.value, language: templateLanguageFilter.value, dateFrom: templateDateFrom.value, dateTo: templateDateTo.value }
}
const applyTemplateFilters = () => {
  templateTypeFilter.value = templateFilterDraft.value.type
  templateServiceFilter.value = templateFilterDraft.value.service
  templatePositionFilter.value = templateFilterDraft.value.position
  templateCountryFilter.value = templateFilterDraft.value.country
  templateLanguageFilter.value = templateFilterDraft.value.language
  templateDateFrom.value = templateFilterDraft.value.dateFrom
  templateDateTo.value = templateFilterDraft.value.dateTo
  filterMenuOpen.value = false
}
const resetTemplateFilters = () => {
  templateSearch.value = ''
  templateTypeFilter.value = null
  templateServiceFilter.value = null
  templatePositionFilter.value = null
  templateCountryFilter.value = null
  templateLanguageFilter.value = null
  templateDateFrom.value = ''
  templateDateTo.value = ''
  syncTemplateFilterDraft()
  filterMenuOpen.value = false
}
const loadPlaceholders = async () => {
  if (!contractTypeId.value) {
    rawPlaceholderGroups.value = []
    return
  }
  try { rawPlaceholderGroups.value = await getPlaceholders({ contract_type_id: contractTypeId.value }) }
  catch (error) { console.error('Failed to load placeholders:', error); rawPlaceholderGroups.value = [] }
}
const loadTemplates = async () => {
  try { templates.value = (await ContractTemplateApi.getAll()).filter(template => template.service !== 'academy') }
  catch (error) { toast.error(tr('messages.loadTemplatesError')); console.error(error) }
}
const loadPolicies = async () => {
  try { allPolicies.value = await ContractPolicyApi.getAll(true) }
  catch (error) { console.error(error) }
}
const loadVersions = async (id = templateId.value) => {
  if (!id) { versions.value = []; return }
  versionsLoading.value = true
  try { versions.value = await ContractTemplateApi.versions(id) }
  catch (error) { toast.error(tr('versions.loadError')); console.error(error) }
  finally { versionsLoading.value = false }
}
const insertPolicy = () => {
  const policy = policies.value.find(item => item.id === selectedPolicyId.value)
  if (!policy) return
  contentHtml.value += (policy.header ? '<h2>' + policy.header + '</h2>' : '') + legacyContentToHtml(policy.content)
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, { emitUpdate: false })
  policyIds.value.push(policy.id)
  selectedPolicyId.value = null
}
const openVersionDialog = () => {
  versionForm.value = { name: '', country_id: versionCountryFilter.value, test_id: versionTestFilter.value, language_id: versionLanguageFilter.value }
  versionDialog.value = true
}
const createVersion = async () => {
  if (!templateId.value || !versionForm.value.name.trim()) { toast.warning(tr('validation.versionNameRequired')); return }
  const scopeMissing = isTestOffer.value ? !versionForm.value.test_id : !versionForm.value.country_id
  if (!isEmployment.value && (scopeMissing || !versionForm.value.language_id)) { toast.warning(tr('versions.countryLanguageRequired')); return }
  try {
    versionsLoading.value = true
    await ContractTemplateApi.createVersion(templateId.value, { name: versionForm.value.name.trim(), country_id: isEmployment.value ? null : versionForm.value.country_id, test_id: isEmployment.value ? null : versionForm.value.test_id, language_id: isEmployment.value ? null : versionForm.value.language_id, content: serializePlaceholders(contentHtml.value) })
    versionDialog.value = false
    await loadVersions()
    toast.success(tr('versions.created'))
  }
  catch (error) { toast.error(tr('versions.createError')); console.error(error) }
  finally { versionsLoading.value = false }
}
const editVersion = async (version: ContractTemplateVersion) => {
  if (version.status !== 'draft' || !canEditDraftVersions.value) return
  editingVersionId.value = version.id
  versionCountryFilter.value = version.country_id
  versionTestFilter.value = version.test_id
  versionLanguageFilter.value = version.language_id
  contentHtml.value = hydratePlaceholders(legacyContentToHtml(version.content), placeholderGroups.value, knownKeys.value)
  await nextTick()
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, { emitUpdate: false })
}
const archiveVersion = async (version: ContractTemplateVersion) => {
  if (!templateId.value || version.status !== 'draft') return
  try { await ContractTemplateApi.archiveVersion(templateId.value, version.id); await loadVersions(); toast.success(tr('versions.archived')) }
  catch (error) { toast.error(tr('versions.archiveError')); console.error(error) }
}
const activateVersion = async (version: ContractTemplateVersion) => {
  if (!templateId.value || version.is_current) return
  const confirmed = await confirmActionWarning({ key: ('activate-template-version-' + version.id) as import('@/composables/useActionWarning').ActionWarningKey, title: tr('versions.activateTitle'), message: tr('versions.activateMessage', { version: version.version_number }), confirmText: t('common.actions.confirm'), cancelText: t('common.actions.cancel'), icon: 'tabler-circle-check', tone: 'success' })
  if (!confirmed) return
  activatingVersionId.value = version.id
  try { await ContractTemplateApi.activateVersion(templateId.value, version.id); await loadVersions(); toast.success(tr('versions.activated')) }
  catch (error) { toast.error(tr('versions.activateError')); console.error(error) }
  finally { activatingVersionId.value = null }
}
const resetForm = () => {
  contractName.value = ''
  contractTypeId.value = null
  coverText.value = ''
  positionId.value = route.query.positionId ? Number(route.query.positionId) : null
  builtInService.value = null
  savedBuiltInService.value = null
  policyIds.value = []
  templateId.value = null
  contentHtml.value = ''
  rawPlaceholderGroups.value = []
  versions.value = []
  initialVersion.value = { name: '', country_id: null, test_id: null, language_id: null }
  editingVersionId.value = null
  nextTick(() => { formRef.value?.resetValidation(); markTemplatePristine() })
}
const loadTemplate = async (id: number) => {
  try {
    isLoading.value = true
    const template = await ContractTemplateApi.getById(id)
    contractName.value = template.name
    contractTypeId.value = template.contract_type_id ?? null
    positionId.value = template.position_id ?? null
    builtInService.value = template.service
    savedBuiltInService.value = template.service
    policyIds.value = template.policies?.map(policy => policy.id) ?? []
    coverText.value = template.cover_text ?? ''
    templateId.value = template.id
    await loadPlaceholders()
    await loadVersions(template.id)
    const editable = versions.value.find(version => version.status === 'draft') ?? versions.value.find(version => version.is_current)
    contentHtml.value = hydratePlaceholders(legacyContentToHtml(editable?.content ?? template.content), placeholderGroups.value, knownKeys.value)
    editingVersionId.value = editable?.status === 'draft' ? editable.id : null
    markTemplatePristine()
  }
  catch (error) { toast.error(tr('messages.loadTemplateDetailsError')); console.error(error) }
  finally { isLoading.value = false }
}
const saveContract = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid || !contractTypeId.value) { if (!contractTypeId.value) toast.warning(tr('messages.selectTypeBeforeSaving')); return }
  if (!isEmployment.value) positionId.value = null
  if (!isServiceType.value) builtInService.value = null
  const serialized = serializePlaceholders(contentHtml.value)
  const invalidKeys = extractPlaceholderKeys(serialized).filter(key => !knownKeys.value.has(key))
  if (invalidKeys.length) { toast.warning(tr('messages.unknownPlaceholders', { placeholders: invalidKeys.join(', ') })); return }
  const isNewTemplate = !templateId.value
  const scopeMissing = !isEmployment.value && ((isTestOffer.value ? !initialVersion.value.test_id : !initialVersion.value.country_id) || !initialVersion.value.language_id)
  if (isNewTemplate && (!initialVersion.value.name.trim() || scopeMissing)) { toast.warning(tr('messages.selectInitialVersion')); return }
  const payload: ContractTemplateCreateData = { name: contractName.value.trim(), contract_type_id: contractTypeId.value, position_id: positionId.value, policy_ids: policyIds.value, cover_text: coverText.value, content: serialized }
  if (builtInService.value) payload.service = builtInService.value
  try {
    isLoading.value = true
    const saved = templateId.value ? await ContractTemplateApi.update(templateId.value, payload) : await ContractTemplateApi.create(payload)
    if (isNewTemplate)
      await ContractTemplateApi.createVersion(saved.id, { name: initialVersion.value.name.trim(), country_id: isEmployment.value ? null : initialVersion.value.country_id, test_id: isEmployment.value ? null : initialVersion.value.test_id, language_id: isEmployment.value ? null : initialVersion.value.language_id, content: serialized })
    else if (editingVersionId.value) {
      const version = versions.value.find(item => item.id === editingVersionId.value)
      if (version) await ContractTemplateApi.updateVersion(saved.id, version.id, { name: version.name, content: serialized })
    }
    const index = templates.value.findIndex(item => item.id === saved.id)
    if (index < 0) templates.value.unshift(saved)
    else templates.value[index] = saved
    await loadTemplates()
    if (isNewTemplate) templates.value = moveRowIdFirst(templates.value, saved.id)
    toast.success(tr(isNewTemplate ? 'messages.templateCreated' : 'messages.templateUpdated'))
    resetForm()
    editorDialog.value = false
  }
  catch (error) { toast.error(tr('messages.saveTemplateError')); console.error(error) }
  finally { isLoading.value = false }
}
const confirmDelete = (id: number) => { templateToDelete.value = id; deleteDialog.value = true }
const closeTemplateEditor = () => { editorDialog.value = false; resetForm() }
const openTemplateEditor = async (id?: number) => { if (id) await loadTemplate(id); else resetForm(); editorDialog.value = true }
const deleteTemplate = async () => {
  if (!templateToDelete.value) return
  try { isLoading.value = true; await ContractTemplateApi.delete(templateToDelete.value); templates.value = templates.value.filter(item => item.id !== templateToDelete.value); toast.success(tr('messages.templateDeleted')); resetForm() }
  catch (error) { toast.warning(tr('messages.templateInUse')); console.error(error) }
  finally { isLoading.value = false; deleteDialog.value = false; templateToDelete.value = null }
}

watch(contractTypeId, async () => {
  if (!isEmployment.value) positionId.value = null
  if (!isServiceType.value) builtInService.value = null
  await loadPlaceholders()
})
watch(filterMenuOpen, open => { if (open) syncTemplateFilterDraft() })
watch(locale, async () => {
  await nextTick()
  contentHtml.value = hydratePlaceholders(serializePlaceholders(contentHtml.value), placeholderGroups.value, knownKeys.value)
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, { emitUpdate: false })
})
onMounted(async () => {
  isLoading.value = true
  await Promise.all([loadTemplates(), loadPolicies(), PositionApi.getOptions().then(rows => { positions.value = rows }), ContractTypeApi.getAll({ include_inactive: true }).then(rows => { contractTypes.value = rows }), MasterDataApi.list('countries').then(rows => { countries.value = rows }), MasterDataApi.list('languages').then(rows => { languages.value = rows }), MasterDataApi.list('tests').then(rows => { tests.value = rows })])
  isLoading.value = false
  if (route.query.positionId) {
    positionId.value = Number(route.query.positionId)
    const template = templates.value.find(item => item.position_id === positionId.value)
    if (template) { await loadTemplate(template.id); editorDialog.value = true }
    else {
      const position = positions.value.find(item => item.id === positionId.value)
      contractName.value = position ? tr('employmentContractName', { position: position.title }) : ''
      contractTypeId.value = contractTypes.value.find(type => type.slug === 'employment')?.id ?? null
    }
  }
  else if (route.params.id) { await loadTemplate(Number(route.params.id)); editorDialog.value = true }
  markTemplatePristine()
})
defineExpose({ openEditor: () => openTemplateEditor() })
</script>

<template>
  <main class="template-workspace">
    <DataTablePageKpis v-if="!props.embedded" :items="templates" :title="t('pages.settings.policies.policies.contractTemplates')" :description="tr('heroDescription')" icon="tabler-file-description" />
    <component :is="props.embedded ? 'div' : 'VCard'" :class="['template-library', { 'template-library--embedded': props.embedded }]">
      <VCardText class="pb-0">
        <VRow align="center" class="table-toolbar contract-template-toolbar ma-0">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="templateSearch" :placeholder="t('pages.settings.policies.policies.contractTemplates')" /></VCol>
          <VCol cols="12" md="6" lg="7" class="d-flex flex-wrap align-center justify-md-end ga-2">
            <VBtn size="small" variant="text" color="primary" class="contract-template-toolbar__filter" @click="filterMenuOpen = true">{{ t('common.messages.filters') }}</VBtn>
            <VBtn color="primary" class="contract-template-toolbar__add" prepend-icon="tabler-plus" :disabled="isLoading" @click="openTemplateEditor()">{{ tr('createNewTemplate') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <slot name="tabs" />

      <ResponsiveDataTable
        :items="filteredTemplates"
        :headers="[{ title: t('pages.settings.policies.policies.contractName'), key: 'name' }, { title: t('common.fields.type'), key: 'type_slug' }, { title: tr('position'), key: 'position.title' }, { title: tr('scope'), key: 'scope' }, { title: tr('versions.title'), key: 'versions' }, { title: t('pages.settings.policies.policies.policyLibrary.actions'), key: 'actions', sortable: false, align: 'end' }]"
        item-value="id"
        class="contract-template-table"
        :loading="isLoading"
        :skeleton-rows="6"
        mobile-cards
        mobile-expand-all
        mobile-title-key="name"
        mobile-action-key="actions"
        :sticky-key-column="false"
        :sticky-action-column="false"
      >
        <template #item.type_slug="{ item }"><VChip size="small" color="secondary" variant="tonal">{{ templateTypeName(item) }}</VChip></template>
        <template #item.position.title="{ item }">{{ item.position?.title || '—' }}</template>
        <template #item.versions="{ item }">{{ item.versions?.length || 0 }}</template>
        <template #item.scope="{ item }"><VChip v-if="item.service" size="small" color="success" variant="tonal">{{ builtInLabel(item.service) }}</VChip><span v-else>—</span></template>
        <template #item.actions="{ item }">
          <VMenu location="bottom end">
            <template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" size="small" variant="tonal" class="contract-template-actions-trigger" :aria-label="t('common.messages.actions')" /></template>
            <VList density="compact" min-width="180" class="contract-template-actions-menu">
              <VListItem prepend-icon="tabler-pencil" :title="t('common.actions.edit')" @click="openTemplateEditor(item.id)" />
              <VListItem v-if="!item.service" prepend-icon="tabler-trash" class="contract-template-actions-menu__delete" :title="t('common.actions.delete')" @click="confirmDelete(item.id)" />
            </VList>
          </VMenu>
        </template>
        <template #no-data><div class="contract-template-empty-state"><TableEmptyState icon="tabler-file-description" :min-height="0" :title="tr('noMatchingTemplates')" :description="tr('heroDescription')" /></div></template>
      </ResponsiveDataTable>
    </component>

    <VDialog v-model="filterMenuOpen" max-width="860" content-class="contract-template-filter-dialog" scroll-strategy="block">
      <VCard class="contract-template-filter-card">
        <VCardTitle class="contract-template-filter-dialog__title px-5 py-4"><span>{{ t('common.messages.filters') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="contract-template-filter-dialog__close" :aria-label="t('common.actions.close')" @click="filterMenuOpen = false" /></VCardTitle>
        <VCardText class="pa-5"><VRow><VCol cols="12" sm="6" md="4"><ErpField v-model="templateFilterDraft.type" kind="select" :items="contractTypes.map(type => ({ title: contractTypeName(type), value: type.id }))" :label="t('common.fields.type')" :placeholder="t('common.fields.type')" clearable /></VCol><VCol cols="12" sm="6" md="4"><ErpField v-model="templateFilterDraft.service" kind="select" :items="builtInServices.map(value => ({ value, title: builtInLabel(value) }))" :label="t('educational.service')" :placeholder="t('educational.service')" clearable /></VCol><VCol cols="12" sm="6" md="4"><ErpField v-model="templateFilterDraft.position" kind="select" :items="positions.map(position => ({ title: position.title, value: position.id }))" :label="tr('position')" :placeholder="tr('position')" clearable /></VCol><VCol cols="12" md="6"><ErpField v-model="templateFilterDraft.country" kind="select" :items="countries.map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" :placeholder="t('common.fields.country')" clearable /></VCol><VCol cols="12" md="6"><ErpField v-model="templateFilterDraft.language" kind="select" :items="languages.map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" :placeholder="t('common.fields.language')" clearable /></VCol><VCol cols="12" sm="6"><ErpField v-model="templateFilterDraft.dateFrom" kind="date" :label="t('erp.fromDate')" :placeholder="t('erp.fromDate')" :config="{ dateFormat: 'Y-m-d', maxDate: templateFilterDraft.dateTo || undefined, static: false }" /></VCol><VCol cols="12" sm="6"><ErpField v-model="templateFilterDraft.dateTo" kind="date" :label="t('erp.toDate')" :placeholder="t('erp.toDate')" :config="{ dateFormat: 'Y-m-d', minDate: templateFilterDraft.dateFrom || undefined, static: false }" /></VCol></VRow></VCardText>
        <VCardActions class="contract-template-filter-dialog__actions px-5 py-4"><VBtn class="contract-template-filter-reset" variant="flat" @click="resetTemplateFilters">{{ t('common.messages.reset') }}</VBtn><VBtn color="primary" class="contract-template-filter-apply" @click="applyTemplateFilters">{{ t('educational.apply') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="editorDialog" max-width="1280" scrollable content-class="contract-template-editor-dialog">
      <VCard class="contract-template-editor">
        <VForm ref="formRef" class="app-form contract-template-form" @submit.prevent="saveContract">
          <VCardTitle class="contract-template-editor__header px-5 py-4"><span class="contract-template-editor__title">{{ templateId ? tr('updateTemplate') : tr('createNewTemplate') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="contract-template-editor__close" :disabled="isLoading" :aria-label="t('common.actions.close')" @click="closeTemplateEditor" /></VCardTitle>
          <VCardText class="contract-template-editor__body pa-5 pa-sm-6">
            <section v-if="templateId" class="contract-template-editor__section contract-template-editor__section--versions">
              <div class="contract-template-editor__section-heading"><h2>{{ isEmployment ? tr('versions.titleEmployment') : tr('versions.title') }}</h2><VBtn v-if="canCreateVersions" color="primary" prepend-icon="tabler-plus" :disabled="versionsLoading" @click="openVersionDialog">{{ tr('versions.createVersion') }}</VBtn></div>
              <VRow class="mt-1" dense>
                <VCol v-if="!isEmployment && isTestOffer" cols="12" md="6"><ErpField v-model="versionTestFilter" kind="select" :items="tests.filter(test => test.is_active).map(test => ({ title: test.name, value: test.id }))" :label="t('educational.masterData.tests')" :placeholder="t('educational.masterData.tests')" clearable /></VCol>
                <VCol v-else-if="!isEmployment" cols="12" md="6"><ErpField v-model="versionCountryFilter" kind="select" :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" :placeholder="t('common.fields.country')" clearable /></VCol>
                <VCol v-if="!isEmployment" cols="12" md="6"><ErpField v-model="versionLanguageFilter" kind="select" :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" :placeholder="t('common.fields.language')" clearable /></VCol>
              </VRow>
              <div v-if="versionsLoading" class="contract-template-version-skeleton"><TableLoadingSkeleton :columns="isEmployment ? 3 : 5" :rows="4" :label="t('common.messages.loading')" /></div>
              <div v-else class="contract-template-version-table"><VTable density="comfortable"><thead><tr><th>{{ tr('versions.version') }}</th><th v-if="!isEmployment">{{ isTestOffer ? t('educational.masterData.tests') : t('common.fields.country') }}</th><th v-if="!isEmployment">{{ t('common.fields.language') }}</th><th>{{ t('common.fields.status') }}</th><th class="text-end">{{ t('pages.settings.policies.policies.policyLibrary.actions') }}</th></tr></thead><tbody><tr v-for="version in visibleVersions" :key="version.id"><td class="font-weight-medium">{{ version.name }} <span class="text-medium-emphasis">· v{{ version.version_number }}</span></td><td v-if="!isEmployment">{{ isTestOffer ? versionTestName(version) : versionCountryName(version) }}</td><td v-if="!isEmployment">{{ versionLanguageName(version) }}</td><td><VChip size="small" :color="version.is_current ? 'success' : version.status === 'draft' ? 'warning' : 'secondary'" variant="tonal">{{ version.is_current ? tr('versions.active') : translateBackendValue(version.status) }}</VChip></td><td class="text-end"><div class="contract-template-version-actions"><VBtn size="small" variant="text" @click="previewVersion = version">{{ tr('versions.preview') }}</VBtn><VBtn v-if="canEditDraftVersions && version.status === 'draft'" size="small" variant="text" @click="editVersion(version)">{{ tr('versions.editDraft') }}</VBtn><VBtn v-if="canActivateVersions && version.status === 'draft'" size="small" color="primary" variant="text" :loading="activatingVersionId === version.id" @click="activateVersion(version)">{{ tr('versions.activate') }}</VBtn><VBtn v-if="canArchiveVersions && version.status === 'draft'" size="small" color="error" variant="text" @click="archiveVersion(version)">{{ tr('versions.archive') }}</VBtn></div></td></tr><tr v-if="!visibleVersions.length"><td :colspan="isEmployment ? 3 : 5" class="text-center text-medium-emphasis py-6">{{ tr('versions.empty') }}</td></tr></tbody></VTable></div>
            </section>

            <section class="contract-template-editor__section">
              <VRow>
                <VCol cols="12" :md="templateId && isOtherType ? 6 : 4"><ErpField v-model="contractName" required :label="t('pages.settings.policies.policies.contractName')" :placeholder="t('pages.settings.policies.policies.eGStandardNDA2026')" prepend-inner-icon="tabler-file-text" :rules="[value => !!String(value || '').trim() || tr('validation.nameRequired')]" /></VCol>
                <VCol cols="12" :md="templateId && isOtherType ? 6 : 4"><ErpField v-model="contractTypeId" kind="select" required :items="contractTypes.filter(type => type.is_active || type.id === contractTypeId).map(type => ({ title: contractTypeName(type), value: type.id }))" :label="tr('contractType')" :placeholder="tr('selectContractType')" :disabled="serviceBindingLocked || isLoading" :rules="[value => !!value || tr('validation.contractTypeRequired')]" clearable /></VCol>
                <VCol v-if="!templateId" cols="12" md="4"><ErpField v-model="initialVersion.name" required :label="tr('versions.versionName')" :placeholder="tr('versions.versionName')" :rules="[value => !!String(value || '').trim() || tr('validation.versionNameRequired')]" /></VCol>
                <VCol v-if="isServiceType" cols="12" md="4"><ErpField v-model="builtInService" kind="select" required :items="serviceItems" :label="t('common.fields.service')" :placeholder="t('common.fields.service')" :disabled="serviceBindingLocked || isLoading" :rules="[value => !!value || t('common.validation.required')]" /></VCol>
                <VCol v-if="isEmployment" cols="12" md="4"><ErpField v-model="positionId" kind="select" required :items="positions" item-title="title" item-value="id" :label="tr('position')" :placeholder="tr('position')" :rules="[value => !!value || tr('validation.positionRequired')]" /></VCol>
                <template v-if="!templateId && !isEmployment">
                  <VCol cols="12" md="4"><ErpField v-if="isTestOffer" v-model="initialVersion.test_id" kind="select" required :items="tests.filter(test => test.is_active).map(test => ({ title: test.name, value: test.id }))" :label="t('educational.masterData.tests')" :placeholder="t('educational.masterData.tests')" :rules="[value => !!value || t('common.validation.required')]" /><ErpField v-else v-model="initialVersion.country_id" kind="select" required :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="tr('initialVersionCountry')" :placeholder="tr('initialVersionCountry')" :rules="[value => !!value || tr('validation.initialVersionCountryRequired')]" /></VCol>
                  <VCol cols="12" md="4"><ErpField v-model="initialVersion.language_id" kind="select" required :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))" :label="tr('initialVersionLanguage')" :placeholder="tr('initialVersionLanguage')" :rules="[value => !!value || tr('validation.initialVersionLanguageRequired')]" /></VCol>
                </template>
              </VRow>
            </section>

            <section class="contract-template-editor__section contract-template-editor__section--content">
              <VRow>
                <VCol v-if="editingVersion" cols="12"><ErpField v-model="editingVersion.name" required :label="tr('versions.versionName')" :placeholder="tr('versions.versionName')" :rules="[value => !!String(value || '').trim() || tr('validation.versionNameRequired')]" /></VCol>
                <VCol cols="12"><ErpField v-model="coverText" class="contract-template-cover-field" kind="textarea" persistent-placeholder :label="t('pages.settings.policies.policies.coverIntroductionText')" :placeholder="t('pages.settings.policies.policies.briefOverviewOrIntroductoryStatementOfTheContract')" rows="1" /></VCol>
                <VCol cols="12"><div class="contract-template-editor__content-heading"><h2>{{ t('pages.settings.policies.policies.contractPOLICIESTERMS') }}</h2><ErpField v-if="policies.length" v-model="selectedPolicyId" kind="autocomplete" :items="policies.map(policy => ({ title: policyTypeName(policy) + ' · ' + policy.label, value: policy.id }))" :label="tr('insertSavedPolicy')" :placeholder="tr('insertSavedPolicy')" clearable :disabled="!contractTypeId" @update:model-value="value => { if (value) insertPolicy() }" /></div><ContractContentEditor ref="editorRef" v-model="contentHtml" :placeholder-groups="placeholderGroups" :disabled="!!templateId && !editingVersion" variant="policy" min-height="520px" /></VCol>
              </VRow>
            </section>
          </VCardText>
          <VCardActions class="contract-template-editor__footer justify-end px-5 py-4"><VBtn v-if="templateId" class="contract-template-editor__delete" :disabled="isLoading" prepend-icon="tabler-trash" @click="confirmDelete(templateId)">{{ t('common.actions.delete') }}</VBtn><VBtn class="contract-template-editor__cancel" :disabled="isLoading" @click="closeTemplateEditor">{{ t('common.actions.cancel') }}</VBtn><VBtn type="submit" class="contract-template-editor__save" :loading="isLoading" prepend-icon="tabler-device-floppy">{{ templateId ? tr('updateTemplate') : tr('saveTemplate') }}</VBtn></VCardActions>
        </VForm>
      </VCard>
    </VDialog>
  </main>

  <VDialog v-model="versionDialog" max-width="680" content-class="contract-version-dialog">
    <VCard class="contract-version-dialog__card"><VCardTitle class="contract-version-dialog__header px-5 py-4"><span>{{ tr('versions.dialogTitle') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="contract-version-dialog__close" :disabled="versionsLoading" :aria-label="t('common.actions.close')" @click="versionDialog = false" /></VCardTitle><VCardText class="contract-version-dialog__body pa-5"><VRow><VCol cols="12"><ErpField v-model="versionForm.name" required :label="tr('versions.versionName')" :placeholder="tr('versions.versionName')" :rules="[value => !!String(value || '').trim() || tr('validation.versionNameRequired')]" /></VCol><VCol v-if="!isEmployment && isTestOffer" cols="12" md="6"><ErpField v-model="versionForm.test_id" kind="select" required :items="tests.filter(test => test.is_active).map(test => ({ title: test.name, value: test.id }))" :label="t('educational.masterData.tests')" :placeholder="t('educational.masterData.tests')" :rules="[value => !!value || t('common.validation.required')]" /></VCol><VCol v-else-if="!isEmployment" cols="12" md="6"><ErpField v-model="versionForm.country_id" kind="select" required :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" :placeholder="t('common.fields.country')" :rules="[value => !!value || tr('versions.countryRequired')]" /></VCol><VCol v-if="!isEmployment" cols="12" md="6"><ErpField v-model="versionForm.language_id" kind="select" required :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" :placeholder="t('common.fields.language')" :rules="[value => !!value || tr('versions.languageRequired')]" /></VCol></VRow></VCardText><VCardActions class="contract-version-dialog__footer justify-end px-5 py-4"><VBtn class="contract-version-dialog__cancel" :disabled="versionsLoading" @click="versionDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="contract-version-dialog__save" :loading="versionsLoading" :disabled="hasTemplateChanges" @click="createVersion">{{ tr('versions.createVersion') }}</VBtn></VCardActions></VCard>
  </VDialog>

  <VDialog :model-value="!!previewVersion" max-width="900" content-class="contract-version-preview-dialog" @update:model-value="value => { if (!value) previewVersion = null }">
    <VCard class="contract-version-preview-dialog__card"><VCardTitle class="contract-version-preview-dialog__header px-5 py-4"><div><div class="contract-version-preview-dialog__title">{{ previewVersion ? previewVersion.name + ' · v' + previewVersion.version_number : '' }}</div><div v-if="previewVersion && !isEmployment" class="contract-version-preview-dialog__meta">{{ isTestOffer ? versionTestName(previewVersion) : versionCountryName(previewVersion) }} · {{ versionLanguageName(previewVersion) }}</div></div></VCardTitle><VCardText class="contract-version-preview-dialog__body pa-5 pa-sm-6"><article class="contract-version-preview" v-html="previewVersion?.content" /></VCardText><VCardActions class="contract-version-preview-dialog__footer px-5 py-4"><VSpacer /><VBtn class="contract-version-preview-dialog__close" prepend-icon="tabler-x" @click="previewVersion = null">{{ t('common.actions.close') }}</VBtn></VCardActions></VCard>
  </VDialog>

  <VDialog v-model="deleteDialog" max-width="480" content-class="contract-template-delete-overlay">
    <VCard class="contract-template-delete-dialog"><VCardText class="contract-template-delete-dialog__content"><div class="contract-template-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div><h2 class="contract-template-delete-dialog__title">{{ t('pages.settings.policies.policies.deleteTemplate2') }}</h2><p class="contract-template-delete-dialog__description">{{ t('common.confirmations.areYouSureYouWantToDeleteThisContractTemplateThisActionCannotBeUndone') }}</p></VCardText><VCardActions class="contract-template-delete-dialog__actions"><VBtn class="contract-template-delete-dialog__cancel" :disabled="isLoading" @click="deleteDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="contract-template-delete-dialog__confirm" :loading="isLoading" @click="deleteTemplate">{{ t('common.actions.delete') }}</VBtn></VCardActions></VCard>
  </VDialog>
</template>

<style lang="scss">
.template-workspace { margin-inline: auto; }
.template-library { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .9rem; }
.template-library--embedded { border: 0; border-radius: 0; }
.contract-template-toolbar { padding: .25rem 0; }
.contract-template-toolbar__add, .contract-template-editor__save, .contract-version-dialog__save { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }.contract-template-toolbar__filter { min-block-size: 40px !important; background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }
.contract-template-filter-reset { background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }
.contract-template-filter-apply { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.contract-template-filter-reset:hover { color: rgb(var(--v-theme-on-primary)) !important; }
.contract-template-filter-card { overflow: hidden; }.contract-template-filter-dialog__title, .contract-template-filter-dialog__actions { display: flex; align-items: center; border-color: rgba(var(--v-border-color), .52); border-style: solid; border-width: 0; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }.contract-template-filter-dialog__title { border-block-end-width: 1px; font-size: 1.2rem; font-weight: 700; }.contract-template-filter-dialog__actions { gap: .75rem; justify-content: flex-end; border-block-start-width: 1px; }.contract-template-filter-dialog__close { border: 1px solid rgba(var(--v-theme-error), .4) !important; border-radius: 999px !important; }
.contract-template-table .v-table__wrapper { overflow-x: auto; }.contract-template-table .v-table__wrapper > table { min-inline-size: 72rem; }.contract-template-table .v-table__wrapper > table > thead > tr > th:first-child { min-inline-size: 22rem; }.contract-template-table .v-table__wrapper > table > thead > tr > th:last-child { min-inline-size: 7rem; }
.contract-template-table .v-table__wrapper > table > thead > tr > th, .contract-template-version-table th { background: rgba(var(--v-theme-on-surface), .045); color: rgb(var(--v-theme-on-surface)); font-size: .74rem; font-weight: 700; letter-spacing: .035em; text-transform: uppercase; white-space: nowrap; }
.contract-template-table .v-table__wrapper > table > tbody > tr > td { white-space: nowrap; }
.contract-template-table .v-table__wrapper:has(.contract-template-empty-state) { overflow-x: hidden; }
.contract-template-table .v-table__wrapper > table:has(.contract-template-empty-state) { min-inline-size: 100%; }
.contract-template-table .v-table__wrapper > table:has(.contract-template-empty-state) > tbody > tr > td { padding: 0 !important; }
.contract-template-empty-state { display: grid; min-block-size: 17rem; place-items: center; }
.contract-template-actions-trigger { border-radius: 999px !important; background: rgba(var(--v-theme-on-surface), .05) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
.contract-template-actions-menu__delete { color: rgb(var(--v-theme-error)) !important; }
.contract-template-editor, .contract-version-dialog__card, .contract-version-preview-dialog__card { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .45); background: rgb(var(--v-theme-surface)); }
.contract-template-editor__header, .contract-template-editor__footer, .contract-version-dialog__header, .contract-version-dialog__footer, .contract-version-preview-dialog__header, .contract-version-preview-dialog__footer { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.contract-template-editor__header, .contract-version-dialog__header, .contract-version-preview-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .52); }
.contract-template-editor__footer, .contract-version-dialog__footer, .contract-version-preview-dialog__footer { gap: .75rem; border-block-start: 1px solid rgba(var(--v-border-color), .52); }
.contract-template-editor__title { color: rgb(var(--v-theme-on-surface)); font-size: 1.25rem; font-weight: 700; }
.contract-template-editor__close, .contract-version-dialog__close { border: 1px solid rgba(var(--v-theme-error), .4) !important; border-radius: 999px !important; background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }
.contract-template-editor__body, .contract-version-dialog__body, .contract-version-preview-dialog__body { background: rgba(var(--v-theme-on-surface), .018); }
.contract-template-editor__section + .contract-template-editor__section { margin-block-start: 1.75rem; padding-block-start: 1.75rem; border-block-start: 1px solid rgba(var(--v-border-color), .58); }
.contract-template-editor__section-heading, .contract-template-editor__content-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; }
.contract-template-editor__section-heading h2, .contract-template-editor__content-heading h2 { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1rem; font-weight: 700; }
.contract-template-editor__content-heading { align-items: end; margin-block-end: 1rem; }
.contract-template-editor__content-heading .erp-field { inline-size: min(100%, 24rem); }
.contract-template-cover-field .v-textarea .v-field, .contract-template-cover-field .v-textarea .v-field__input { min-block-size: 104px !important; }
.contract-template-version-skeleton { margin-block-start: 1rem; overflow: hidden; border: 1px solid rgba(var(--v-border-color), .48); border-radius: .75rem; }
.contract-template-version-skeleton .table-loading-skeleton { min-block-size: 16rem; }
.contract-template-version-table { margin-block-start: 1rem; overflow-x: auto; border: 1px solid rgba(var(--v-border-color), .48); border-radius: .75rem; }
.contract-template-version-table table { min-inline-size: 48rem; }
.contract-template-version-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .15rem; min-inline-size: 20rem; }
.contract-template-editor__cancel, .contract-version-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .5) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.contract-template-editor__delete { background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }
.contract-version-preview-dialog__title { color: rgb(var(--v-theme-on-surface)); font-size: 1.15rem; font-weight: 700; }
.contract-version-preview-dialog__meta { margin-block-start: .25rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .82rem; }
.contract-version-preview { max-block-size: 62vh; overflow: auto; padding: clamp(1rem, 3vw, 2rem); border: 1px solid rgba(var(--v-border-color), .48); border-radius: .8rem; background: rgb(var(--v-theme-surface)); color: rgb(var(--v-theme-on-surface)); line-height: 1.8; }
.contract-version-preview-dialog__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }
.contract-template-delete-dialog { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, .18) !important; text-align: center; }
.contract-template-delete-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; }
.contract-template-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); place-items: center; }
.contract-template-delete-dialog__title { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1.2rem; font-weight: 700; line-height: 1.45; }
.contract-template-delete-dialog__description { max-inline-size: 24rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.65; }
.contract-template-delete-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }
.contract-template-delete-dialog__actions .v-btn { min-block-size: 2.85rem; border-radius: 10px !important; font-weight: 700; }
.contract-template-delete-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.contract-template-delete-dialog__confirm { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.v-overlay:has(.contract-template-editor-dialog) .v-overlay__scrim, .v-overlay:has(.contract-template-filter-dialog) .v-overlay__scrim, .v-overlay:has(.contract-version-dialog) .v-overlay__scrim, .v-overlay:has(.contract-version-preview-dialog) .v-overlay__scrim, .v-overlay:has(.contract-template-delete-overlay) .v-overlay__scrim { background: rgba(20, 25, 35, .3) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
@media (max-width: 700px) { .contract-template-editor__section-heading, .contract-template-editor__content-heading { align-items: stretch; flex-direction: column; } .contract-template-editor__content-heading .erp-field { inline-size: 100%; } .contract-template-editor__footer { flex-wrap: wrap; } .contract-template-editor__footer .v-spacer { display: none; } .contract-template-editor__footer .v-btn { flex: 1 1 auto; } .contract-template-delete-dialog__content, .contract-template-delete-dialog__actions { padding-inline: 1.25rem !important; } }
</style>
