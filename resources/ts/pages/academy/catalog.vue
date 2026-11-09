<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
const toast = useToast()
const rows = ref<MasterDataRecord[]>([])
const loading = ref(false)
const failed = ref(false)
const canManage = computed(() => canAccess('manage_academy_classes'))
const dialogOpen = ref(false)
const editing = ref<MasterDataRecord | null>(null)
const form = ref<Partial<MasterDataRecord>>({ name: '', description: '', sort_order: 0, is_active: true })

async function load() {
  loading.value = true
  failed.value = false
  try {
    rows.value = await MasterDataApi.list('programs')
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

function open(row?: MasterDataRecord) {
  editing.value = row ?? null
  form.value = row ? { ...row } : { name: '', description: '', sort_order: 0, is_active: true }
  dialogOpen.value = true
}

async function save() {
  if (!form.value.name?.trim())
    return
  try {
    if (editing.value)
      await MasterDataApi.update('programs', editing.value.id, form.value)
    else
      await MasterDataApi.create('programs', form.value)
    dialogOpen.value = false
    toast.success(t('academy.catalog.saved'))
    await load()
  }
  catch { /* Global notifier owns the API error. */ }
}

async function remove(row: MasterDataRecord) {
  if (!confirm(t('academy.catalog.removeConfirm', { name: row.name })))
    return
  try {
    await MasterDataApi.remove('programs', row.id)
    toast.success(t('academy.catalog.removed'))
    await load()
  }
  catch { /* Global notifier owns the API error. */ }
}

onMounted(load)
</script>

<template>
  <div class="academy-page pa-4 pa-sm-6">
    <ErpPageHeader :title="t('academy.catalog.title')" :description="t('academy.catalog.description')" section="academy">
      <template #actions>
        <VBtn v-if="canManage" color="primary" prepend-icon="tabler-plus" @click="open()">{{ t('academy.catalog.add') }}</VBtn>
      </template>
    </ErpPageHeader>

    <VCard>
      <VCardText>
        <TableLoadingSkeleton v-if="loading" :columns="4" :label="t('common.messages.loading')" />
        <VAlert v-else-if="failed" type="error" variant="tonal">{{ t('academy.catalog.loadFailed') }}</VAlert>
        <TableEmptyState v-else-if="rows.length === 0" icon="tabler-book-2" :title="t('academy.catalog.emptyTitle')" :description="t('academy.catalog.emptyDescription')" />
        <VTable v-else density="compact">
          <thead><tr><th>{{ t('common.fields.name') }}</th><th>{{ t('common.fields.details') }}</th><th>{{ t('common.fields.status') }}</th><th class="text-end">{{ t('common.fields.actions') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.name }}</td>
              <td>{{ row.description || '—' }}</td>
              <td><VChip size="small" :color="row.is_active ? 'success' : 'secondary'">{{ t(row.is_active ? 'common.statuses.active' : 'common.statuses.inactive') }}</VChip></td>
              <td class="text-end">
                <VBtn v-if="canManage" icon="tabler-pencil" size="small" variant="text" :aria-label="t('common.actions.edit')" @click="open(row)" />
                <VBtn v-if="canManage" icon="tabler-trash" size="small" variant="text" color="error" :aria-label="t('common.actions.delete')" @click="remove(row)" />
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <VDialog v-model="dialogOpen" max-width="560">
      <VCard>
        <VCardTitle>{{ t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: t('academy.catalog.programs') }) }}</VCardTitle>
        <VCardText>
          <VTextField v-model="form.name" :label="t('common.fields.name')" class="mb-3" />
          <VTextField v-model="form.sort_order" type="number" min="0" :label="t('academy.fields.sortOrder')" class="mb-3" />
          <VTextarea v-model="form.description" :label="t('common.fields.description')" />
          <VSwitch v-model="form.is_active" :label="t('common.statuses.active')" />
        </VCardText>
        <VCardActions><VSpacer /><VBtn @click="dialogOpen = false">{{ t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="!form.name?.trim()" @click="save">{{ t('common.actions.save') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
