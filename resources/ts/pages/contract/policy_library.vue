<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { type PlaceholderGroup, getPlaceholders } from '@/api/contractPlaceholders'
import ContractPolicyApi, { type ContractPolicy, type ContractPolicyPayload } from '@/api/contractPolicy'
import ContractTypeApi, { type ContractType } from '@/api/contractType'
import PositionApi, { type Position } from '@/api/position'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import ContractContentEditor from '@/components/contract/ContractContentEditor.vue'
import { extractPlaceholderKeys, hydratePlaceholders, legacyContentToHtml, localizePlaceholderGroups, serializePlaceholders } from '@/utils/contractPlaceholders'
import { translateContractPolicyType } from '@/utils/localization'

const { t } = useI18n()
const toast = useToast()
const props = withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })
const policies = ref<ContractPolicy[]>([])
const contractTypes = ref<ContractType[]>([])
const placeholderGroups = ref<PlaceholderGroup[]>([])
const loading = ref(false)
const dialog = ref(false)
const deleteDialog = ref(false)
const policyToDelete = ref<ContractPolicy | null>(null)
const editing = ref<ContractPolicy | null>(null)
const editorRef = ref<InstanceType<typeof ContractContentEditor> | null>(null)
const form = ref<ContractPolicyPayload>({ label: '', type: 'employment', service: null, position_id: null, country_id: null, language_id: null, is_active: true, header: '', content: '' })
const countries = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const positions = ref<Position[]>([])
const filterMenuOpen = ref(false)
const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const serviceFilter = ref<string | null>(null)
const positionFilter = ref<number | null>(null)
const typeFilter = ref<string | null>(null)
const statusFilter = ref<'active' | 'inactive' | null>(null)
const countryFilter = ref<number | null>(null)
const languageFilter = ref<number | null>(null)

const serviceItems = computed(() => ['private', 'scholarship', 'test_offer'].map(value => ({ value, title: t(`educational.builtInServices.${value === 'test_offer' ? 'testOffer' : value}`) })))
const policyTypes = computed(() => contractTypes.value.filter(type => type.is_active).map(type => ({ value: type.slug, title: translateContractPolicyType(type.slug, type.name) })))
const selectedType = computed(() => contractTypes.value.find(type => type.slug === form.value.type) ?? null)
const knownKeys = computed(() => new Set(placeholderGroups.value.flatMap(group => group.fields.map(field => field.key))))
const localizedPlaceholderGroups = computed(() => localizePlaceholderGroups(placeholderGroups.value))
const policyTypeLabel = (type: string) => translateContractPolicyType(type, contractTypes.value.find(item => item.slug === type)?.name)
const hasPolicyContent = computed(() => form.value.content.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, '').trim().length > 0)
const hasActiveFilters = computed(() => Boolean(serviceFilter.value || positionFilter.value || typeFilter.value || countryFilter.value || languageFilter.value || statusFilter.value || dateFrom.value || dateTo.value))
const filteredPolicies = computed(() => policies.value.filter(policy => {
  const query = search.value.trim().toLocaleLowerCase()
  return (!dateFrom.value || policy.created_at.slice(0, 10) >= dateFrom.value)
    && (!dateTo.value || policy.created_at.slice(0, 10) <= dateTo.value)
    && (!serviceFilter.value || policy.service === serviceFilter.value)
    && (!positionFilter.value || policy.position_id === positionFilter.value)
    && (!typeFilter.value || policy.type === typeFilter.value)
    && (!statusFilter.value || (statusFilter.value === 'active' ? policy.is_active : !policy.is_active))
    && (!countryFilter.value || policy.country_id === countryFilter.value)
    && (!languageFilter.value || policy.language_id === languageFilter.value)
    && (!query || [policy.label, policy.header, policy.content].filter(Boolean).some(value => String(value).toLocaleLowerCase().includes(query)))
}))

const resetFilters = () => {
  search.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  serviceFilter.value = null
  positionFilter.value = null
  typeFilter.value = null
  countryFilter.value = null
  languageFilter.value = null
  statusFilter.value = null
  filterMenuOpen.value = false
}
const applyFilters = () => { filterMenuOpen.value = false }
const load = async () => {
  loading.value = true
  try {
    const [items, types, countryRows, languageRows, positionRows] = await Promise.all([
      ContractPolicyApi.getAll(true),
      ContractTypeApi.getAll({ include_inactive: true }),
      MasterDataApi.list('countries'),
      MasterDataApi.list('languages'),
      PositionApi.getOptions(),
    ])
    policies.value = items
    contractTypes.value = types
    countries.value = countryRows
    languages.value = languageRows
    positions.value = positionRows
  }
  catch { toast.error(t('pages.settings.policies.policies.policyLibrary.loadError')) }
  finally { loading.value = false }
}
const loadPlaceholders = async () => {
  if (!selectedType.value) { placeholderGroups.value = []; return }
  try { placeholderGroups.value = await getPlaceholders({ contract_type_id: selectedType.value.id }) }
  catch (error) { console.error('Unable to load policy placeholders:', error); placeholderGroups.value = [] }
}
const open = async (policy?: ContractPolicy) => {
  editing.value = policy ?? null
  form.value = policy
    ? { label: policy.label, type: policy.type, service: policy.service ?? null, position_id: policy.position_id ?? null, country_id: policy.country_id, language_id: policy.language_id, is_active: policy.is_active, header: policy.header ?? '', content: legacyContentToHtml(policy.content) }
    : { label: '', type: 'employment', service: null, position_id: null, country_id: null, language_id: null, is_active: true, header: '', content: '' }
  dialog.value = true
  await loadPlaceholders()
  form.value.content = hydratePlaceholders(serializePlaceholders(form.value.content), localizedPlaceholderGroups.value, knownKeys.value)
}
const close = () => { dialog.value = false }
const save = async () => {
  if (!form.value.label.trim() || !form.value.type || !hasPolicyContent.value) {
    toast.error(t('pages.settings.policies.policies.policyLibrary.requiredFields'))
    return
  }
  const serializedContent = serializePlaceholders(form.value.content)
  const invalidKeys = extractPlaceholderKeys(serializedContent).filter(key => !knownKeys.value.has(key))
  if (invalidKeys.length) {
    toast.warning(t('pages.settings.policies.policies.contractTemplate.messages.unknownPlaceholders', { placeholders: invalidKeys.join(', ') }))
    return
  }
  loading.value = true
  try {
    const payload = { ...form.value, service: form.value.type === 'customer' ? form.value.service : null, position_id: form.value.type === 'employment' ? form.value.position_id : null, label: form.value.label.trim(), header: form.value.header?.trim() || null, content: serializedContent }
    const wasNew = !editing.value
    const saved = editing.value ? await ContractPolicyApi.update(editing.value.id, payload) : await ContractPolicyApi.create(payload)
    const index = policies.value.findIndex(policy => policy.id === saved.id)
    if (index >= 0) policies.value.splice(index, 1)
    if (wasNew) policies.value.unshift(saved)
    else {
      policies.value.push(saved)
      policies.value.sort((a, b) => a.type.localeCompare(b.type) || a.label.localeCompare(b.label))
    }
    dialog.value = false
    toast.success(t(editing.value ? 'pages.settings.policies.policies.policyLibrary.updated' : 'pages.settings.policies.policies.policyLibrary.created'))
  }
  catch (error: any) { toast.error(error.message || t('pages.settings.policies.policies.policyLibrary.saveError')) }
  finally { loading.value = false }
}
const requestDelete = (policy: ContractPolicy) => { policyToDelete.value = policy; deleteDialog.value = true }
const remove = async () => {
  if (!policyToDelete.value) return
  loading.value = true
  try {
    await ContractPolicyApi.delete(policyToDelete.value.id)
    policies.value = policies.value.filter(item => item.id !== policyToDelete.value?.id)
    toast.success(t('pages.settings.policies.policies.policyLibrary.deleted'))
  }
  catch (error: any) { toast.error(error.message || t('pages.settings.policies.policies.policyLibrary.deleteError')) }
  finally { loading.value = false; deleteDialog.value = false; policyToDelete.value = null }
}

watch(() => form.value.type, async () => {
  await loadPlaceholders()
  await nextTick()
  form.value.content = hydratePlaceholders(serializePlaceholders(form.value.content), localizedPlaceholderGroups.value, knownKeys.value)
  editorRef.value?.getEditor()?.commands.setContent(form.value.content, { emitUpdate: false })
})
onMounted(load)
defineExpose({ openEditor: () => open() })
</script>

<template>
  <main class="contract-policy-library">
    <DataTablePageKpis v-if="!props.embedded" :items="filteredPolicies" :title="t('pages.settings.policies.policies.policyLibrary.pageTitle')" :description="t('pages.settings.policies.policies.policyLibrary.pageDescription')" icon="tabler-book-2" />
    <component :is="props.embedded ? 'div' : 'VCard'" :class="{ 'contract-policy-library__card': !props.embedded }">
      <VCardText class="pb-0">
        <VRow align="center" class="table-toolbar contract-policy-toolbar ma-0">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="t('common.actions.search')" /></VCol>
          <VCol cols="12" md="6" lg="7" class="d-flex flex-wrap align-center justify-md-end ga-2">
            <VBtn size="small" variant="text" color="primary" class="contract-policy-toolbar__filter" @click="filterMenuOpen = true">{{ t('common.messages.filters') }}</VBtn>
            <VBtn color="primary" class="contract-policy-toolbar__add" prepend-icon="tabler-plus" :disabled="loading" @click="open()">{{ t('pages.settings.policies.policies.policyLibrary.add') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <slot name="tabs" />
      <ResponsiveDataTable
        :items="filteredPolicies"
        :headers="[{ title: t('pages.settings.policies.policies.policyLibrary.type'), key: 'type' }, { title: t('common.fields.country'), key: 'country.name' }, { title: t('common.fields.language'), key: 'language.name' }, { title: t('pages.settings.policies.policies.policyLibrary.label'), key: 'label' }, { title: t('pages.settings.policies.policies.policyLibrary.header'), key: 'header' }, { title: t('pages.settings.policies.policies.policyLibrary.status'), key: 'status' }, { title: t('pages.settings.policies.policies.policyLibrary.actions'), key: 'actions', sortable: false, align: 'end' }]"
        item-value="id"
        class="contract-policy-table"
        :loading="loading"
        :skeleton-rows="6"
        mobile-cards
        mobile-expand-all
        mobile-title-key="label"
        mobile-action-key="actions"
        :sticky-key-column="false"
        :sticky-action-column="false"
      >
        <template #item.type="{ item }"><VChip size="small" color="secondary" variant="tonal">{{ policyTypeLabel(item.type) }}</VChip></template>
        <template #item.country.name="{ item }">{{ item.country?.name ?? '—' }}</template>
        <template #item.language.name="{ item }">{{ item.language?.name ?? '—' }}</template>
        <template #item.header="{ item }">{{ item.header || '—' }}</template>
        <template #item.status="{ item }"><VChip size="small" :color="item.is_active ? 'success' : 'secondary'" variant="tonal">{{ item.is_active ? t('common.statuses.active') : t('common.statuses.inactive') }}</VChip></template>
        <template #item.actions="{ item }"><VMenu location="bottom end"><template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" size="small" variant="tonal" class="contract-policy-actions-trigger" :aria-label="t('common.messages.actions')" /></template><VList density="compact" min-width="180"><VListItem prepend-icon="tabler-pencil" :title="t('pages.settings.policies.policies.policyLibrary.edit')" @click="open(item)" /><VListItem prepend-icon="tabler-trash" class="contract-policy-actions-menu__delete" :title="t('pages.settings.policies.policies.policyLibrary.delete')" @click="requestDelete(item)" /></VList></VMenu></template>
        <template #no-data><div class="contract-policy-empty-state"><TableEmptyState icon="tabler-book-off" :min-height="0" :title="t('pages.settings.policies.policies.policyLibrary.empty')" :description="t('pages.settings.policies.policies.policyLibrary.subtitle')" /></div></template>
      </ResponsiveDataTable>
    </component>

    <VDialog v-model="filterMenuOpen" max-width="860" content-class="contract-policy-filter-dialog" scroll-strategy="block">
      <VCard class="contract-policy-filter-card">
        <VCardTitle class="contract-policy-filter-dialog__title px-5 py-4"><span>{{ t('common.messages.filters') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="contract-policy-filter-dialog__close" :aria-label="t('common.actions.close')" @click="filterMenuOpen = false" /></VCardTitle>
        <VCardText class="pa-5"><VRow><VCol cols="12" sm="6" md="4"><ErpField v-model="typeFilter" kind="select" :items="policyTypes" :label="t('common.fields.type')" :placeholder="t('common.fields.type')" clearable /></VCol><VCol cols="12" sm="6" md="4"><ErpField v-model="serviceFilter" kind="select" :items="serviceItems" :label="t('educational.service')" :placeholder="t('educational.service')" clearable /></VCol><VCol cols="12" sm="6" md="4"><ErpField v-model="positionFilter" kind="select" :items="positions.map(position => ({ title: position.title, value: position.id }))" :label="t('common.fields.position')" :placeholder="t('common.fields.position')" clearable /></VCol><VCol cols="12" sm="6" md="4"><ErpField v-model="countryFilter" kind="select" :items="countries.map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" :placeholder="t('common.fields.country')" clearable /></VCol><VCol cols="12" sm="6" md="4"><ErpField v-model="languageFilter" kind="select" :items="languages.map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" :placeholder="t('common.fields.language')" clearable /></VCol><VCol cols="12" sm="6" md="4"><ErpField v-model="statusFilter" kind="select" :items="[{ title: t('common.statuses.active'), value: 'active' }, { title: t('common.statuses.inactive'), value: 'inactive' }]" :label="t('common.fields.status')" :placeholder="t('common.fields.status')" clearable /></VCol><VCol cols="12" sm="6"><ErpField v-model="dateFrom" kind="date" :label="t('erp.fromDate')" :placeholder="t('erp.fromDate')" :config="{ dateFormat: 'Y-m-d', maxDate: dateTo || undefined, static: false }" /></VCol><VCol cols="12" sm="6"><ErpField v-model="dateTo" kind="date" :label="t('erp.toDate')" :placeholder="t('erp.toDate')" :config="{ dateFormat: 'Y-m-d', minDate: dateFrom || undefined, static: false }" /></VCol></VRow></VCardText>
        <VCardActions class="contract-policy-filter-dialog__actions px-5 py-4"><VBtn class="contract-policy-filter-reset" variant="flat" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn><VBtn color="primary" class="contract-policy-filter-apply" @click="applyFilters">{{ t('educational.apply') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialog" max-width="1120" scrollable content-class="contract-policy-editor-dialog">
      <VCard class="contract-policy-editor">
        <VCardTitle class="contract-policy-editor__header px-5 py-4"><span>{{ t(editing ? 'pages.settings.policies.policies.policyLibrary.editTitle' : 'pages.settings.policies.policies.policyLibrary.addTitle') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="contract-policy-editor__close" :disabled="loading" :aria-label="t('common.actions.close')" @click="close" /></VCardTitle>
        <VCardText class="contract-policy-editor__body pa-5 pa-sm-6">
          <VForm @submit.prevent="save">
            <VRow>
              <VCol cols="12" md="4"><ErpField v-model="form.label" required :label="t('pages.settings.policies.policies.policyLibrary.label')" :placeholder="t('pages.settings.policies.policies.policyLibrary.labelPlaceholder')" /></VCol>
              <VCol cols="12" md="4"><ErpField v-model="form.type" kind="select" required :items="policyTypes" :label="t('pages.settings.policies.policies.policyLibrary.type')" :placeholder="t('pages.settings.policies.policies.policyLibrary.type')" /></VCol>
              <VCol v-if="form.type === 'customer'" cols="12" md="4"><ErpField v-model="form.service" kind="select" :items="serviceItems" :label="t('educational.service')" :placeholder="t('educational.service')" clearable /></VCol>
              <VCol v-if="form.type === 'employment'" cols="12" md="4"><ErpField v-model="form.position_id" kind="select" :items="positions.map(position => ({ title: position.title, value: position.id }))" :label="t('common.fields.position')" :placeholder="t('common.fields.position')" clearable /></VCol>
              <VCol cols="12" md="6"><ErpField v-model="form.country_id" kind="select" :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" :placeholder="t('common.fields.country')" clearable /></VCol>
              <VCol cols="12" md="6"><ErpField v-model="form.language_id" kind="select" :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" :placeholder="t('common.fields.language')" clearable /></VCol>
              <VCol cols="12" md="4"><ErpField v-model="form.header" :label="t('pages.settings.policies.policies.policyLibrary.clauseHeader')" :placeholder="t('pages.settings.policies.policies.policyLibrary.headerPlaceholder')" /></VCol>
              <VCol cols="12"><VSwitch v-model="form.is_active" color="success" inset :label="t('pages.settings.policies.policies.policyLibrary.activeHint')" hide-details /></VCol>
              <VCol cols="12"><div class="contract-policy-editor__content-heading"><h2>{{ t('pages.settings.policies.policies.policyLibrary.content') }}</h2><VChip size="small" color="secondary" variant="tonal">{{ policyTypeLabel(form.type) }}</VChip></div><ContractContentEditor ref="editorRef" v-model="form.content" :placeholder-groups="localizedPlaceholderGroups" variant="policy" min-height="370px" /></VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions class="contract-policy-editor__footer justify-end px-5 py-4"><VBtn class="contract-policy-editor__cancel" :disabled="loading" @click="close">{{ t('common.actions.cancel') }}</VBtn><VBtn class="contract-policy-editor__save" :loading="loading" @click="save">{{ t('pages.settings.policies.policies.policyLibrary.save') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="deleteDialog" max-width="480" content-class="contract-policy-delete-overlay">
      <VCard class="contract-policy-delete-dialog"><VCardText class="contract-policy-delete-dialog__content"><div class="contract-policy-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div><h2 class="contract-policy-delete-dialog__title">{{ t('pages.settings.policies.policies.policyLibrary.delete') }}</h2><p class="contract-policy-delete-dialog__description">{{ t('pages.settings.policies.policies.policyLibrary.confirmDelete', { label: policyToDelete?.label ?? '' }) }}</p></VCardText><VCardActions class="contract-policy-delete-dialog__actions"><VBtn class="contract-policy-delete-dialog__cancel" :disabled="loading" @click="deleteDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="contract-policy-delete-dialog__confirm" :loading="loading" @click="remove">{{ t('common.actions.delete') }}</VBtn></VCardActions></VCard>
    </VDialog>
  </main>
</template>

<style lang="scss">
.contract-policy-library { margin-inline: auto; }
.contract-policy-library__card { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .9rem; }
.contract-policy-toolbar { padding: .25rem 0; }
.contract-policy-toolbar__add, .contract-policy-editor__save { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }.contract-policy-toolbar__filter { min-block-size: 40px !important; background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }
.contract-policy-filter-reset { background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }
.contract-policy-filter-apply { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.contract-policy-filter-reset:hover { color: rgb(var(--v-theme-on-primary)) !important; }
.contract-policy-filter-card { overflow: hidden; }.contract-policy-filter-dialog__title, .contract-policy-filter-dialog__actions { display: flex; align-items: center; border-color: rgba(var(--v-border-color), .52); border-style: solid; border-width: 0; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }.contract-policy-filter-dialog__title { border-block-end-width: 1px; font-size: 1.2rem; font-weight: 700; }.contract-policy-filter-dialog__actions { gap: .75rem; justify-content: flex-end; border-block-start-width: 1px; }.contract-policy-filter-dialog__close { border: 1px solid rgba(var(--v-theme-error), .4) !important; border-radius: 999px !important; }
.contract-policy-table .v-table__wrapper { overflow-x: auto; }.contract-policy-table .v-table__wrapper > table { min-inline-size: 74rem; }.contract-policy-table .v-table__wrapper > table > thead > tr > th:nth-child(4) { min-inline-size: 22rem; }.contract-policy-table .v-table__wrapper > table > thead > tr > th:last-child { min-inline-size: 7rem; }
.contract-policy-table .v-table__wrapper > table > thead > tr > th { background: rgba(var(--v-theme-on-surface), .045); color: rgb(var(--v-theme-on-surface)); font-size: .74rem; font-weight: 700; letter-spacing: .035em; text-transform: uppercase; white-space: nowrap; }
.contract-policy-table .v-table__wrapper > table > tbody > tr > td { white-space: nowrap; }
.contract-policy-table .v-table__wrapper:has(.contract-policy-empty-state) { overflow-x: hidden; }
.contract-policy-table .v-table__wrapper > table:has(.contract-policy-empty-state) { min-inline-size: 100%; }
.contract-policy-table .v-table__wrapper > table:has(.contract-policy-empty-state) > tbody > tr > td { padding: 0 !important; }
.contract-policy-empty-state { display: grid; min-block-size: 17rem; place-items: center; }
.contract-policy-actions-trigger { border-radius: 999px !important; background: rgba(var(--v-theme-on-surface), .05) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
.contract-policy-actions-menu__delete { color: rgb(var(--v-theme-error)) !important; }
.contract-policy-editor { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .45); background: rgb(var(--v-theme-surface)); }
.contract-policy-editor__header, .contract-policy-editor__footer { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.contract-policy-editor__header { border-block-end: 1px solid rgba(var(--v-border-color), .52); color: rgb(var(--v-theme-on-surface)); font-size: 1.25rem; font-weight: 700; }
.contract-policy-editor__footer { gap: .75rem; border-block-start: 1px solid rgba(var(--v-border-color), .52); }
.contract-policy-editor__body { background: rgba(var(--v-theme-on-surface), .018); }
.contract-policy-editor__close { border: 1px solid rgba(var(--v-theme-error), .4) !important; border-radius: 999px !important; background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }
.contract-policy-editor__cancel { border: 1px solid rgba(var(--v-theme-secondary), .5) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.contract-policy-editor__content-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-block-end: 1rem; }
.contract-policy-editor__content-heading h2 { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1rem; font-weight: 700; }
.contract-policy-delete-dialog { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, .18) !important; text-align: center; }
.contract-policy-delete-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; }
.contract-policy-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); place-items: center; }
.contract-policy-delete-dialog__title { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1.2rem; font-weight: 700; }
.contract-policy-delete-dialog__description { max-inline-size: 24rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.65; }
.contract-policy-delete-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }
.contract-policy-delete-dialog__actions .v-btn { min-block-size: 2.85rem; border-radius: 10px !important; font-weight: 700; }
.contract-policy-delete-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.contract-policy-delete-dialog__confirm { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.v-overlay:has(.contract-policy-editor-dialog) .v-overlay__scrim, .v-overlay:has(.contract-policy-filter-dialog) .v-overlay__scrim, .v-overlay:has(.contract-policy-delete-overlay) .v-overlay__scrim { background: rgba(20, 25, 35, .3) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
@media (max-width: 700px) { .contract-policy-editor__footer { flex-wrap: wrap; } .contract-policy-editor__footer .v-spacer { display: none; } .contract-policy-editor__footer .v-btn { flex: 1 1 auto; } .contract-policy-delete-dialog__content, .contract-policy-delete-dialog__actions { padding-inline: 1.25rem !important; } }
</style>
