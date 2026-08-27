<script setup lang="ts">
import ContractPolicyApi, { type ContractPolicy, type ContractPolicyPayload } from '@/api/contractPolicy'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const { t } = useI18n()
const toast = useToast()
const policies = ref<ContractPolicy[]>([])
const loading = ref(false)
const dialog = ref(false)
const editing = ref<ContractPolicy | null>(null)
const form = ref<ContractPolicyPayload>({ label: '', type: '', header: '', content: '' })

async function load() {
  loading.value = true
  try { policies.value = await ContractPolicyApi.getAll() }
  catch { toast.error(t('pages.settings.policies.policies.policyLibrary.loadError')) }
  finally { loading.value = false }
}

function open(policy?: ContractPolicy) {
  editing.value = policy ?? null
  form.value = policy
    ? { label: policy.label, type: policy.type, header: policy.header ?? '', content: policy.content }
    : { label: '', type: '', header: '', content: '' }
  dialog.value = true
}

async function save() {
  if (!form.value.label.trim() || !form.value.type.trim() || !form.value.content.trim()) return toast.error(t('pages.settings.policies.policies.policyLibrary.requiredFields'))
  loading.value = true
  try {
    const payload = { ...form.value, label: form.value.label.trim(), type: form.value.type.trim(), header: form.value.header?.trim() || null }
    const saved = editing.value ? await ContractPolicyApi.update(editing.value.id, payload) : await ContractPolicyApi.create(payload)
    const index = policies.value.findIndex(policy => policy.id === saved.id)
    if (index === -1) policies.value.push(saved); else policies.value[index] = saved
    policies.value.sort((a, b) => a.type.localeCompare(b.type) || a.label.localeCompare(b.label))
    dialog.value = false
    toast.success(t(editing.value ? 'pages.settings.policies.policies.policyLibrary.updated' : 'pages.settings.policies.policies.policyLibrary.created'))
  } catch (error: any) { toast.error(error.message || t('pages.settings.policies.policies.policyLibrary.saveError')) }
  finally { loading.value = false }
}

async function remove(policy: ContractPolicy) {
  if (!confirm(t('pages.settings.policies.policies.policyLibrary.confirmDelete', { label: policy.label }))) return
  loading.value = true
  try { await ContractPolicyApi.delete(policy.id); policies.value = policies.value.filter(item => item.id !== policy.id); toast.success(t('pages.settings.policies.policies.policyLibrary.deleted')) }
  catch (error: any) { toast.error(error.message || t('pages.settings.policies.policies.policyLibrary.deleteError')) }
  finally { loading.value = false }
}

onMounted(load)
</script>

<template>
  <DataTablePageKpis :items="policies" title="Policy library" description="Keep reusable contract clauses organized and ready for every agreement." icon="tabler-book-2" />
  <VCard :loading="loading">
    <VCardItem :title="t('pages.settings.policies.policies.policyLibrary.title')" :subtitle="t('pages.settings.policies.policies.policyLibrary.subtitle')">
      <template #append><VBtn color="primary" prepend-icon="tabler-plus" @click="open()">{{ t('pages.settings.policies.policies.policyLibrary.add') }}</VBtn></template>
    </VCardItem>
    <ResponsiveDataTable :items="policies" :headers="[
      { title: t('pages.settings.policies.policies.policyLibrary.type'), key: 'type' }, { title: t('pages.settings.policies.policies.policyLibrary.label'), key: 'label' }, { title: t('pages.settings.policies.policies.policyLibrary.header'), key: 'header' }, { title: t('pages.settings.policies.policies.policyLibrary.actions'), key: 'actions', sortable: false, align: 'end' },
    ]" item-value="id">
      <template #item.type="{ item }"><VChip size="small" color="primary" variant="tonal">{{ item.type }}</VChip></template>
      <template #item.header="{ item }">{{ item.header || '—' }}</template>
      <template #item.actions="{ item }"><IconBtn :aria-label="t('pages.settings.policies.policies.policyLibrary.edit')" @click="open(item)"><VIcon icon="tabler-pencil" /></IconBtn><IconBtn :aria-label="t('pages.settings.policies.policies.policyLibrary.delete')" @click="remove(item)"><VIcon icon="tabler-trash" /></IconBtn></template>
      <template #no-data><div class="pa-8 text-center text-medium-emphasis">{{ t('pages.settings.policies.policies.policyLibrary.empty') }}</div></template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog v-model="dialog" max-width="720">
    <VCard :title="t(editing ? 'pages.settings.policies.policies.policyLibrary.editTitle' : 'pages.settings.policies.policies.policyLibrary.addTitle')">
      <VCardText><VForm @submit.prevent="save"><VRow>
        <VCol cols="12" md="6"><VTextField v-model="form.label" :label="t('pages.settings.policies.policies.policyLibrary.label')" autofocus /></VCol>
        <VCol cols="12" md="6"><VTextField v-model="form.type" :label="t('pages.settings.policies.policies.policyLibrary.type')" :hint="t('pages.settings.policies.policies.policyLibrary.typeHint')" persistent-hint /></VCol>
        <VCol cols="12"><VTextField v-model="form.header" :label="t('pages.settings.policies.policies.policyLibrary.clauseHeader')" /></VCol>
        <VCol cols="12"><VTextarea v-model="form.content" :label="t('pages.settings.policies.policies.policyLibrary.content')" rows="8" /></VCol>
      </VRow><VCardActions><VSpacer /><VBtn variant="tonal" @click="dialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn type="submit" color="primary" :loading="loading">{{ t('pages.settings.policies.policies.policyLibrary.save') }}</VBtn></VCardActions></VForm></VCardText>
    </VCard>
  </VDialog>
</template>
