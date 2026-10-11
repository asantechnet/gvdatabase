<script setup lang="ts">
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { type PlaceholderGroup, getPlaceholders } from '@/api/contractPlaceholders'
import ContractPolicyApi, { type ContractPolicy, type ContractPolicyPayload } from '@/api/contractPolicy'
import ContractTypeApi, { type ContractType } from '@/api/contractType'
import { canAccess } from '@/utils/authorization'
import PositionApi, { type Position } from '@/api/position'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import ContractContentEditor from '@/components/contract/ContractContentEditor.vue'
import { extractPlaceholderKeys, hydratePlaceholders, legacyContentToHtml, localizePlaceholderGroups, serializePlaceholders } from '@/utils/contractPlaceholders'
import { translateContractPolicyType } from '@/utils/localization'

const { t } = useI18n()
const props = withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })
const toast = useToast()
const policies = ref<ContractPolicy[]>([])
const contractTypes = ref<ContractType[]>([])
const placeholderGroups = ref<PlaceholderGroup[]>([])
const loading = ref(false)
const dialog = ref(false)
const editing = ref<ContractPolicy | null>(null)
const editorRef = ref<InstanceType<typeof ContractContentEditor> | null>(null)
const form = ref<ContractPolicyPayload>({ label: '', type: 'employment', service: null, position_id: null, country_id: null, language_id: null, is_active: true, header: '', content: '' })
const countries = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const positions = ref<Position[]>([])
const serviceFilter = ref<string | null>(null)
const positionFilter = ref<number | null>(null)
// Academy is not offered in Templates & Policies; it is ensured by a hidden seeded template.
const serviceItems = computed(() => ['private', 'scholarship', 'test_offer'].map(value => ({ value, title: t(`educational.builtInServices.${value === 'test_offer' ? 'testOffer' : value}`) })))
const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const resetFilters = () => { search.value = ''; dateFrom.value = ''; dateTo.value = ''; serviceFilter.value = null; positionFilter.value = null; typeFilter.value = null; countryFilter.value = null; languageFilter.value = null; statusFilter.value = null }
const typeFilter = ref<string | null>(null)
const statusFilter = ref<'active' | 'inactive' | null>(null)
const countryFilter = ref<number | null>(null)
const languageFilter = ref<number | null>(null)

const policyTypes = computed(() => contractTypes.value.filter(type => type.is_active).map(type => ({ value: type.slug, icon: type.slug === 'employment' ? 'tabler-briefcase' : type.slug === 'customer' ? 'tabler-users' : 'tabler-file-description' })))

const selectedType = computed(() => contractTypes.value.find(type => type.slug === form.value.type) ?? null)
const knownKeys = computed(() => new Set(placeholderGroups.value.flatMap(group => group.fields.map(field => field.key))))
const localizedPlaceholderGroups = computed(() => localizePlaceholderGroups(placeholderGroups.value))
const policyTypeLabel = (type: string) => translateContractPolicyType(type, contractTypes.value.find(item => item.slug === type)?.name)
const policyTypeItems = computed(() => policyTypes.value.map(item => ({ ...item, title: translateContractPolicyType(item.value, contractTypes.value.find(type => type.slug === item.value)?.name) })))
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
const hasPolicyContent = computed(() => form.value.content.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, '').trim().length > 0)

async function load() {
  loading.value = true
  try {
  const [items, types, countryRows, languageRows, positionRows] = await Promise.all([ContractPolicyApi.getAll(true), ContractTypeApi.getAll({ include_inactive: true }), MasterDataApi.list('countries'), MasterDataApi.list('languages'), canAccess('view_positions') ? PositionApi.getOptions() : Promise.resolve([])])

    policies.value = items
    contractTypes.value = types
    countries.value = countryRows
    languages.value = languageRows
    positions.value = positionRows
  }
  catch { toast.error(t('pages.settings.policies.policies.policyLibrary.loadError')) }
  finally { loading.value = false }
}

async function loadPlaceholders() {
  if (!selectedType.value) {
    placeholderGroups.value = []

    return
  }
  try { placeholderGroups.value = await getPlaceholders({ contract_type_id: selectedType.value.id }) }
  catch (error) { console.error('Unable to load policy placeholders:', error); placeholderGroups.value = [] }
}

async function open(policy?: ContractPolicy) {
  const existingPolicy = policy && typeof policy === 'object' && typeof policy.id === 'number' ? policy : null

  editing.value = existingPolicy
  form.value = existingPolicy
    ? { label: existingPolicy.label, type: existingPolicy.type, service: existingPolicy.service ?? null, position_id: existingPolicy.position_id ?? null, country_id: existingPolicy.country_id, language_id: existingPolicy.language_id, is_active: existingPolicy.is_active, header: existingPolicy.header ?? '', content: legacyContentToHtml(existingPolicy.content) }
    : { label: '', type: 'employment', service: null, position_id: null, country_id: null, language_id: null, is_active: true, header: '', content: '' }
  dialog.value = true
  await loadPlaceholders()
  form.value.content = hydratePlaceholders(serializePlaceholders(form.value.content), localizedPlaceholderGroups.value, knownKeys.value)
}

defineExpose({ openEditor: () => open() })

async function save() {
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
    const payload = {
      ...form.value,
      service: form.value.type === 'customer' ? form.value.service : null,
      position_id: form.value.type === 'employment' ? form.value.position_id : null,
      label: form.value.label.trim(),
      header: form.value.header?.trim() || null,
      content: serializedContent,
    }

    const saved = editing.value ? await ContractPolicyApi.update(editing.value.id, payload) : await ContractPolicyApi.create(payload)
    const index = policies.value.findIndex(policy => policy.id === saved.id)
    if (index === -1)
      policies.value.push(saved)
    else policies.value[index] = saved
    policies.value.sort((a, b) => a.type.localeCompare(b.type) || a.label.localeCompare(b.label))
    dialog.value = false
    toast.success(t(editing.value ? 'pages.settings.policies.policies.policyLibrary.updated' : 'pages.settings.policies.policies.policyLibrary.created'))
  }
  catch (error: any) { toast.error(error.message || t('pages.settings.policies.policies.policyLibrary.saveError')) }
  finally { loading.value = false }
}

async function remove(policy: ContractPolicy) {
  if (!confirm(t('pages.settings.policies.policies.policyLibrary.confirmDelete', { label: policy.label })))
    return
  loading.value = true
  try { await ContractPolicyApi.delete(policy.id); policies.value = policies.value.filter(item => item.id !== policy.id); toast.success(t('pages.settings.policies.policies.policyLibrary.deleted')) }
  catch (error: any) { toast.error(error.message || t('pages.settings.policies.policies.policyLibrary.deleteError')) }
  finally { loading.value = false }
}

watch(() => form.value.type, async () => {
  await loadPlaceholders()
  await nextTick()
  form.value.content = hydratePlaceholders(serializePlaceholders(form.value.content), localizedPlaceholderGroups.value, knownKeys.value)
  editorRef.value?.getEditor()?.commands.setContent(form.value.content, { emitUpdate: false })
})

onMounted(load)
</script>

<template>
  <DataTablePageKpis
    v-if="!props.embedded"
    :items="filteredPolicies"
    :title="t('pages.settings.policies.policies.policyLibrary.pageTitle')"
    :description="t('pages.settings.policies.policies.policyLibrary.pageDescription')"
    icon="tabler-book-2"
  />
  <component :is="props.embedded ? 'div' : 'VCard'" :loading="loading">
    <VCardItem
      v-if="!props.embedded"
      :title="t('pages.settings.policies.policies.policyLibrary.title')"
      :subtitle="t('pages.settings.policies.policies.policyLibrary.subtitle')"
    >
      <template #append>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="open()"
        >
          {{ t('pages.settings.policies.policies.policyLibrary.add') }}
        </VBtn>
      </template>
    </VCardItem>
    <VCardText class="pb-0">
      <div class="d-flex align-center ga-3 mb-4">
        <VTextField v-model="search" :label="t('common.actions.search')" prepend-inner-icon="tabler-search" density="compact" hide-details />
        <DataTableFilterMenu :active="Boolean(serviceFilter || positionFilter || typeFilter || countryFilter || languageFilter || statusFilter || dateFrom || dateTo)" @reset="resetFilters">
          <VSelect v-model="typeFilter" :items="policyTypeItems" clearable :label="t('common.fields.type')" />
          <VSelect v-model="serviceFilter" :items="serviceItems" clearable :label="t('educational.service')" />
          <VSelect v-model="positionFilter" :items="positions.map(position => ({ title: position.title, value: position.id }))" clearable :label="t('common.fields.position')" />
          <VSelect v-model="countryFilter" :items="countries.map(country => ({ title: country.name, value: country.id }))" clearable :label="t('common.fields.country')" />
          <VSelect v-model="languageFilter" :items="languages.map(language => ({ title: language.name, value: language.id }))" clearable :label="t('common.fields.language')" />
          <VSelect v-model="statusFilter" :items="[{ title: t('common.statuses.active'), value: 'active' }, { title: t('common.statuses.inactive'), value: 'inactive' }]" clearable :label="t('common.fields.status')" />
          <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" />
        </DataTableFilterMenu>
      </div>
    </VCardText>
    <ResponsiveDataTable
      :items="filteredPolicies"
      :headers="[
        { title: t('pages.settings.policies.policies.policyLibrary.type'), key: 'type' },
        { title: t('common.fields.country'), key: 'country.name' },
        { title: t('common.fields.language'), key: 'language.name' },
        { title: t('pages.settings.policies.policies.policyLibrary.label'), key: 'label' },
        { title: t('pages.settings.policies.policies.policyLibrary.header'), key: 'header' },
        { title: t('pages.settings.policies.policies.policyLibrary.status'), key: 'status' },
        { title: t('pages.settings.policies.policies.policyLibrary.actions'), key: 'actions', sortable: false, align: 'end' },
      ]"
      item-value="id"
    >
      <template #item.type="{ item }">
        <VChip
          size="small"
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-category"
        >
          {{ translateContractPolicyType(item.type, contractTypes.find(type => type.slug === item.type)?.name) }}
        </VChip>
      </template>
      <template #item.country.name="{ item }">{{ item.country?.name ?? '—' }}</template>
      <template #item.language.name="{ item }">{{ item.language?.name ?? '—' }}</template>
      <template #item.header="{ item }">
        {{ item.header || '—' }}
      </template>
      <template #item.status="{ item }">
        <VChip size="small" :color="item.is_active ? 'success' : 'secondary'" variant="tonal">
          {{ item.is_active ? t('common.statuses.active') : t('common.statuses.inactive') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn
          :aria-label="t('pages.settings.policies.policies.policyLibrary.edit')"
          @click="open(item)"
        >
          <VIcon icon="tabler-pencil" />
        </IconBtn><IconBtn
          :aria-label="t('pages.settings.policies.policies.policyLibrary.delete')"
          @click="remove(item)"
        >
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
      <template #no-data>
        <div class="pa-8 text-center text-medium-emphasis">
          {{ t('pages.settings.policies.policies.policyLibrary.empty') }}
        </div>
      </template>
    </ResponsiveDataTable>
  </component>

  <VDialog
    v-model="dialog"
    max-width="1480"
    scrollable
    content-class="policy-studio-dialog"
  >
    <VCard class="policy-studio">
      <VCardItem class="policy-studio__header">
        <template #prepend>
          <VAvatar
            color="secondary"
            variant="tonal"
            size="42"
          >
            <VIcon icon="tabler-scale" />
          </VAvatar>
        </template>
        <VCardTitle>{{ t(editing ? 'pages.settings.policies.policies.policyLibrary.editTitle' : 'pages.settings.policies.policies.policyLibrary.addTitle') }}</VCardTitle>
        <VCardSubtitle>{{ t('pages.settings.policies.policies.policyLibrary.formSubtitle') }}</VCardSubtitle>
        <template #append>
          <VChip
            color="secondary"
            size="small"
            variant="tonal"
          >
            {{ t('pages.settings.policies.policies.policyLibrary.studioLabel') }}
          </VChip>
        </template>
      </VCardItem>
      <VDivider />
      <VCardText class="pt-5">
        <VForm @submit.prevent="save">
          <VRow>
            <VCol
              cols="12"
              md="7"
            >
              <VTextField
                v-model="form.label"
                :label="t('pages.settings.policies.policies.policyLibrary.label')"
                :placeholder="t('pages.settings.policies.policies.policyLibrary.labelPlaceholder')"
                prepend-inner-icon="tabler-bookmark"
                variant="outlined"
                autofocus
              />
            </VCol>
            <VCol
              cols="12"
              md="5"
            >
              <VSelect
                v-model="form.type"
                :items="policyTypeItems"
                item-title="title"
                item-value="value"
                :label="t('pages.settings.policies.policies.policyLibrary.type')"
                prepend-inner-icon="tabler-category"
                variant="outlined"
              >
                <template #item="{ props, item }">
                  <VListItem
                    v-bind="props"
                    :prepend-icon="item.raw.icon"
                  />
                </template>
              </VSelect>
            </VCol>
            <VCol v-if="form.type === 'customer'" cols="12" md="6"><VSelect v-model="form.service" :items="serviceItems" :label="t('educational.service')" clearable /></VCol>
            <VCol v-if="form.type === 'employment'" cols="12" md="6"><VSelect v-model="form.position_id" :items="positions.map(position => ({ title: position.title, value: position.id }))" :label="t('common.fields.position')" clearable /></VCol>
            <VCol cols="12" md="6">
              <VSelect v-model="form.country_id" :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" clearable variant="outlined" />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect v-model="form.language_id" :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" clearable variant="outlined" />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                color="success"
                inset
                :label="t('pages.settings.policies.policies.policyLibrary.activeHint')"
                hide-details
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="form.header"
                :label="t('pages.settings.policies.policies.policyLibrary.clauseHeader')"
                :placeholder="t('pages.settings.policies.policies.policyLibrary.headerPlaceholder')"
                prepend-inner-icon="tabler-heading"
                variant="outlined"
              />
            </VCol>
          </VRow>
          <div class="policy-studio__editor-label">
            <div>
              <div class="text-subtitle-2 font-weight-bold">
                {{ t('pages.settings.policies.policies.policyLibrary.content') }}
              </div><div class="text-caption text-medium-emphasis">
                {{ t('pages.settings.policies.policies.policyLibrary.contentHint') }}
              </div>
            </div><VChip
              size="small"
              color="secondary"
              variant="tonal"
            >
              {{ t('pages.settings.policies.policies.policyLibrary.context', { type: policyTypeLabel(form.type) }) }}
            </VChip>
          </div>
          <ContractContentEditor
            ref="editorRef"
            v-model="form.content"
            :placeholder-groups="localizedPlaceholderGroups"
            variant="policy"
            min-height="370px"
          />
        </VForm>
      </VCardText>
      <VCardActions class="policy-studio__actions">
        <span class="text-caption text-medium-emphasis">{{ t('pages.settings.policies.policies.policyLibrary.footerHint') }}</span><VSpacer /><VBtn
          variant="tonal"
          @click="dialog = false"
        >
          {{ t('common.actions.cancel') }}
        </VBtn><VBtn
          color="secondary"
          :loading="loading"
          @click="save"
        >
          {{ t('pages.settings.policies.policies.policyLibrary.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.policy-studio-dialog {
  margin: 24px;
  max-block-size: calc(100% - 48px) !important;
}

.policy-studio {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.9rem;

  &__header {
    min-block-size: 92px;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(118deg, rgba(var(--v-theme-secondary), 0.11), transparent 58%);
  }

  &__header :deep(.v-card-item__prepend) { margin-inline-end: 1rem; }
  &__header :deep(.v-card-title) { line-height: 1.3; }
  &__header :deep(.v-card-subtitle) { margin-block-start: 0.2rem; white-space: normal; }
  &__editor-label { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-block: 0.75rem; }
  &__actions { min-block-size: 68px; padding-inline: 1.5rem; border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }

  @media (max-width: 600px) {
    &__header { min-block-size: auto; padding: 1rem; }
    &__header :deep(.v-card-item__append) { display: none; }
    &__editor-label { align-items: flex-start; flex-direction: column; }
    &__actions { flex-wrap: wrap; gap: 0.5rem; padding: 1rem; }
    &__actions > :first-child { flex-basis: 100%; }
  }
}

@media (max-width: 600px) {
  .policy-studio-dialog { margin: 12px; max-block-size: calc(100% - 24px) !important; }
}
</style>
