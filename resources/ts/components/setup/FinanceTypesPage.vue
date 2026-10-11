<script setup lang="ts">
import { useToast } from 'vue-toastification'
import { type FinanceType, type FinanceTypeKind, deleteFinanceType, getFinanceTypes, saveFinanceType } from '@/api/financeTypes'
import { financeTypeLabel } from '@/utils/financeTypes'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { canAccess } from '@/utils/authorization'

const props = defineProps<{ kind: FinanceTypeKind }>()
const { t } = useI18n()
const toast = useToast()
const records = ref<FinanceType[]>([])
const search = ref('')
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const editing = ref<FinanceType | null>(null)
const deleting = ref<FinanceType | null>(null)
const name = ref('')
const nameError = ref('')
const canManage = computed(() => canAccess('edit_settings'))
const title = computed(() => t(props.kind === 'income' ? 'common.navigation.incomeTypes' : 'common.navigation.expenseTypes'))
const singular = computed(() => t(props.kind === 'income' ? 'pages.finance.types.incomeType' : 'pages.finance.types.expenseType'))
const headers = computed(() => [
  { title: t('common.fields.name'), key: 'name' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])
const filtered = computed(() => records.value.filter(record => financeTypeLabel(record).toLowerCase().includes(search.value.trim().toLowerCase())))

async function load() {
  loading.value = true
  try { records.value = await getFinanceTypes(props.kind) }
  catch (error) { toast.error(apiErrorMessage(error, t('pages.finance.types.loadFailed'))) }
  finally { loading.value = false }
}

function open(record: FinanceType | null = null) {
  editing.value = record
  name.value = record?.name ?? ''
  nameError.value = ''
  dialog.value = true
}

async function save() {
  if (saving.value) return
  nameError.value = ''
  if (!name.value.trim()) {
    nameError.value = t('pages.finance.types.nameRequired')
    return
  }
  saving.value = true
  try {
    await saveFinanceType(props.kind, name.value.trim(), editing.value?.id)
    dialog.value = false
    toast.success(t('pages.finance.types.saved'))
    await load()
  }
  catch (error) {
    nameError.value = apiFieldErrors(error).name ?? ''
    toast.error(apiErrorMessage(error, t('pages.finance.types.saveFailed')))
  }
  finally { saving.value = false }
}

async function remove() {
  if (!deleting.value || saving.value) return
  saving.value = true
  try {
    await deleteFinanceType(props.kind, deleting.value.id)
    deleting.value = null
    toast.success(t('pages.finance.types.deleted'))
    await load()
  }
  catch (error) { toast.error(apiErrorMessage(error, t('pages.finance.types.deleteFailed'))) }
  finally { saving.value = false }
}

onMounted(load)
</script>

<template>
  <VCard :title="title" :loading="loading">
    <VCardText>
      <p>{{ t('pages.finance.types.description') }}</p>
      <div class="d-flex flex-wrap ga-4 align-center">
        <VTextField v-model="search" :label="t('common.actions.search')" prepend-inner-icon="tabler-search" class="data-table-search flex-grow-1" hide-details clearable />
        <VBtn v-if="canManage" prepend-icon="tabler-plus" @click="open()">
          {{ t('common.actions.addItem', { item: singular }) }}
        </VBtn>
      </div>
    </VCardText>
    <ResponsiveDataTable :headers="headers" :items="filtered" :loading="loading" item-value="id">
      <template #item.name="{ item }">{{ financeTypeLabel(item) }}</template>
      <template #item.actions="{ item }">
        <IconBtn v-if="canManage" :aria-label="t('common.actions.editItem', { item: singular })" @click="open(item)"><VIcon icon="tabler-pencil" /></IconBtn>
        <IconBtn v-if="canManage && !item.is_system" color="error" :aria-label="t('common.actions.deleteItem', { item: singular })" @click="deleting = item"><VIcon icon="tabler-trash" /></IconBtn>
      </template>
      <template #no-data>{{ t('common.messages.noMatchingRecords') }}</template>
    </ResponsiveDataTable>
  </VCard>
  <VDialog v-model="dialog" max-width="500" :persistent="saving">
    <VCard :title="t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: singular })">
      <VCardText>
        <VForm @submit.prevent="save">
          <VTextField v-model="name" :label="t('common.fields.name')" :error-messages="nameError" maxlength="255" autofocus />
          <div class="d-flex justify-end ga-3 mt-4">
            <VBtn variant="tonal" :disabled="saving" @click="dialog = false">{{ t('common.actions.cancel') }}</VBtn>
            <VBtn type="submit" :loading="saving">{{ t('common.actions.save') }}</VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
  <VDialog :model-value="Boolean(deleting)" max-width="500" :persistent="saving" @update:model-value="!$event && (deleting = null)">
    <VCard :title="t('common.actions.deleteItem', { item: singular })">
      <VCardText>{{ t('pages.finance.types.confirmDelete', { name: deleting?.name }) }}</VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn :disabled="saving" @click="deleting = null">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="error" :loading="saving" @click="remove">{{ t('common.actions.delete') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
