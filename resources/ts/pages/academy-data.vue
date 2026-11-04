<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'

/**
 * Academy data extracted from Options → Contracts → Educational data.
 *
 * Times, durations, teachers and classes are the academy's own scheduling
 * records, so they live on this page under the contracts submenu while the
 * remaining shared catalogs stay in the educational data page.
 */
type AcademyCatalog = 'times' | 'durations' | 'teachers' | 'classes'

const toast = useToast()
const { t } = useI18n()
const route = useRoute()

const catalogs: AcademyCatalog[] = ['times', 'durations', 'teachers', 'classes']
const requested = String(route.query.catalog ?? '')
const catalog = ref<AcademyCatalog>(catalogs.includes(requested as AcademyCatalog) ? requested as AcademyCatalog : 'times')
const rows = ref<MasterDataRecord[]>([])
const loading = ref(false)
const dialog = ref(false)
const editing = ref<MasterDataRecord | null>(null)
const form = ref<Partial<MasterDataRecord>>({ name: '', description: '', sort_order: 0, is_active: true })

const labels = computed<Record<AcademyCatalog, string>>(() => ({
  times: t('educational.masterData.times'),
  durations: t('educational.masterData.durations'),
  teachers: t('educational.masterData.teachers'),
  classes: t('erp.class'),
}))

const isTeacherCatalog = computed(() => catalog.value === 'teachers')
const detailFor = (row: MasterDataRecord) => row.contact || row.email || row.description || '—'

async function load() {
  loading.value = true
  try {
    rows.value = await MasterDataApi.list(catalog.value)
  }
  catch { toast.error(t('educational.masterData.loadFailed')) }
  finally { loading.value = false }
}

function open(row?: MasterDataRecord) {
  editing.value = row ?? null
  form.value = row
    ? { ...row }
    : { name: '', description: '', sort_order: 0, is_active: true, contact: '', email: '' }
  dialog.value = true
}

async function save() {
  if (!form.value.name?.trim())
    return

  try {
    if (editing.value)
      await MasterDataApi.update(catalog.value, editing.value.id, form.value)
    else
      await MasterDataApi.create(catalog.value, form.value)
    dialog.value = false
    await load()
    toast.success(t('educational.masterData.saved'))
  }
  catch { /* API failure feedback belongs to the global notifier (Task-018). */ }
}

async function remove(row: MasterDataRecord) {
  if (!confirm(t('educational.masterData.removeConfirm', { name: row.name })))
    return

  try {
    await MasterDataApi.remove(catalog.value, row.id)
    await load()
  }
  catch { /* The server's specific conflict message is shown once by the notifier. */ }
}

watch(catalog, load)
onMounted(load)
</script>

<template>
  <div class="education-workspace">
    <VCard class="education-panel">
      <VCardTitle class="d-flex justify-space-between">
        <span>{{ $t('educational.masterData.academyTitle', { catalog: labels[catalog] }) }}</span>
        <VBtn prepend-icon="tabler-plus" @click="open()">{{ $t('common.actions.add') }}</VBtn>
      </VCardTitle>
      <VCardText>
        <VTabs v-model="catalog" show-arrows>
          <VTab v-for="(label, key) in labels" :key="key" :value="key">{{ label }}</VTab>
        </VTabs>
        <VProgressLinear v-if="loading" indeterminate class="my-4" />
        <div class="education-table-scroll">
          <VTable density="compact" class="education-table">
            <thead>
              <tr>
                <th>{{ $t('educational.masterData.displayOrder') }}</th>
                <th>{{ $t('common.fields.name') }}</th>
                <th>{{ $t('educational.masterData.details') }}</th>
                <th>{{ $t('common.fields.status') }}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>{{ row.sort_order ?? 0 }}</td>
                <td>{{ row.name }}</td>
                <td>{{ detailFor(row) }}</td>
                <td>
                  <VChip :color="row.is_active ? 'success' : 'secondary'" size="small">
                    {{ row.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}
                  </VChip>
                </td>
                <td class="text-no-wrap">
                  <VBtn icon="tabler-pencil" size="small" variant="text" @click="open(row)" />
                  <VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(row)" />
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>
    </VCard>
  </div>

  <VDialog v-model="dialog" max-width="560">
    <VCard>
      <VCardTitle>{{ $t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: labels[catalog] }) }}</VCardTitle>
      <VCardText>
        <VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-3" />
        <VTextField v-model.number="form.sort_order" type="number" min="0" :label="$t('educational.masterData.displayOrder')" :hint="$t('educational.masterData.displayOrderHint')" persistent-hint class="mb-3" />
        <VTextField v-if="isTeacherCatalog" v-model="form.contact" :label="$t('educational.masterData.contact')" class="mb-3" />
        <VTextField v-if="isTeacherCatalog" v-model="form.email" type="email" :label="$t('common.fields.email')" class="mb-3" />
        <VTextarea v-model="form.description" :label="$t('common.fields.description')" />
        <VSwitch v-model="form.is_active" :label="$t('common.statuses.active')" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :disabled="!form.name?.trim()" @click="save">{{ $t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
