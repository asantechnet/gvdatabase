<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { type PlaceholderGroup, getPlaceholders } from '@/api/contractPlaceholders'
import ContractPolicyApi, { type ContractPolicy, type ContractPolicyPayload } from '@/api/contractPolicy'
import ContractTypeApi, { type ContractType } from '@/api/contractType'
import ContractContentEditor from '@/components/contract/ContractContentEditor.vue'
import { extractPlaceholderKeys, hydratePlaceholders, legacyContentToHtml, serializePlaceholders } from '@/utils/contractPlaceholders'

const { t } = useI18n()
const toast = useToast()
const policies = ref<ContractPolicy[]>([])
const contractTypes = ref<ContractType[]>([])
const placeholderGroups = ref<PlaceholderGroup[]>([])
const loading = ref(false)
const dialog = ref(false)
const editing = ref<ContractPolicy | null>(null)
const editorRef = ref<InstanceType<typeof ContractContentEditor> | null>(null)
const form = ref<ContractPolicyPayload>({ label: '', type: 'employment', header: '', content: '' })

const policyTypes = [
  { title: 'Employment', value: 'employment', icon: 'tabler-briefcase' },
  { title: 'Company', value: 'company', icon: 'tabler-building' },
  { title: 'Customer', value: 'customer', icon: 'tabler-users' },
]

const selectedType = computed(() => contractTypes.value.find(type => type.slug === form.value.type) ?? null)
const knownKeys = computed(() => new Set(placeholderGroups.value.flatMap(group => group.fields.map(field => field.key))))
const policyTypeLabel = (type: string) => policyTypes.find(item => item.value === type)?.title ?? type
const hasPolicyContent = computed(() => form.value.content.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, '').trim().length > 0)

async function load() {
  loading.value = true
  try {
    const [items, types] = await Promise.all([ContractPolicyApi.getAll(), ContractTypeApi.getAll({ include_inactive: true })])

    policies.value = items
    contractTypes.value = types
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
  editing.value = policy ?? null
  form.value = policy
    ? { label: policy.label, type: policy.type, header: policy.header ?? '', content: legacyContentToHtml(policy.content) }
    : { label: '', type: 'employment', header: '', content: '' }
  dialog.value = true
  await loadPlaceholders()
  form.value.content = hydratePlaceholders(serializePlaceholders(form.value.content), placeholderGroups.value, knownKeys.value)
}

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
  form.value.content = hydratePlaceholders(serializePlaceholders(form.value.content), placeholderGroups.value, knownKeys.value)
  editorRef.value?.getEditor()?.commands.setContent(form.value.content, { emitUpdate: false })
})

onMounted(load)
</script>

<template>
  <DataTablePageKpis
    :items="policies"
    title="Policy library"
    description="Keep reusable contract clauses organized and ready for every agreement."
    icon="tabler-book-2"
  />
  <VCard :loading="loading">
    <VCardItem
      :title="t('pages.settings.policies.policies.policyLibrary.title')"
      :subtitle="t('pages.settings.policies.policies.policyLibrary.subtitle')"
    >
      <template #append>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="open"
        >
          {{ t('pages.settings.policies.policies.policyLibrary.add') }}
        </VBtn>
      </template>
    </VCardItem>
    <ResponsiveDataTable
      :items="policies"
      :headers="[
        { title: t('pages.settings.policies.policies.policyLibrary.type'), key: 'type' },
        { title: t('pages.settings.policies.policies.policyLibrary.label'), key: 'label' },
        { title: t('pages.settings.policies.policies.policyLibrary.header'), key: 'header' },
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
          {{ policyTypeLabel(item.type) }}
        </VChip>
      </template>
      <template #item.header="{ item }">
        {{ item.header || '—' }}
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
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="1120"
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
        <VCardSubtitle>Write a reusable clause with fields that are resolved in the right contract context.</VCardSubtitle>
        <template #append>
          <VChip
            color="secondary"
            size="small"
            variant="tonal"
          >
            Clause studio
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
                placeholder="e.g. Confidentiality"
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
                :items="policyTypes"
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
            <VCol cols="12">
              <VTextField
                v-model="form.header"
                :label="t('pages.settings.policies.policies.policyLibrary.clauseHeader')"
                placeholder="Optional heading shown above the clause"
                prepend-inner-icon="tabler-heading"
                variant="outlined"
              />
            </VCol>
          </VRow>
          <div class="policy-studio__editor-label">
            <div>
              <div class="text-subtitle-2 font-weight-bold">
                Clause content
              </div><div class="text-caption text-medium-emphasis">
                Policy fields are highlighted in purple so this editor stays distinct from full contract templates.
              </div>
            </div><VChip
              size="small"
              color="secondary"
              variant="tonal"
            >
              {{ policyTypeLabel(form.type) }} context
            </VChip>
          </div>
          <ContractContentEditor
            ref="editorRef"
            v-model="form.content"
            :placeholder-groups="placeholderGroups"
            variant="policy"
            min-height="370px"
          />
        </VForm>
      </VCardText>
      <VCardActions class="policy-studio__actions">
        <span class="text-caption text-medium-emphasis">Use the merge-field menu to insert details safely.</span><VSpacer /><VBtn
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
