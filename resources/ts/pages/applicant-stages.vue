<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type ApplicantStage, type BuiltInService } from '@/api/applicants'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'
import { translateApplicantStage } from '@/utils/localization'

const { t } = useI18n()
const toast = useToast()
// Each built-in service owns its own stage list; the selector scopes the page.
const service = ref<BuiltInService>('scholarship')
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])
const stages = ref<ApplicantStage[]>([])
const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const loading = ref(false)
const loadFailed = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editing = ref<ApplicantStage | null>(null)
const canManage = computed(() => canAccess('manage_applicant_stages'))
const form = ref<Partial<ApplicantStage>>({ key: '', name: '', service: service.value, sort_order: 0, is_active: true, is_terminal: false, allowed_next_keys: [] })
const serviceTitle = (value?: BuiltInService | null) => serviceOptions.value.find(option => option.value === value)?.title ?? '—'
const stageNameByKey = computed(() => Object.fromEntries(stages.value.map(stage => [stage.key, translateApplicantStage(stage)])))
const nextStagesLabel = (stage: ApplicantStage) => (stage.allowed_next_keys ?? []).map(key => stageNameByKey.value[key] ?? key).join(', ') || '—'
const scopeLabel = (stage: ApplicantStage) => {
  const country = countries.value.find(row => row.id === stage.country_id)?.name
  const university = universities.value.find(row => row.id === stage.university_id)?.name

  return [country, university].filter(Boolean).join(' · ') || '—'
}
const nextStageOptions = computed(() => stages.value
  .filter(stage => stage.id !== editing.value?.id)
  .map(stage => ({ title: `${translateApplicantStage(stage)} (${stage.key})`, value: stage.key })))

// The stage list is what the page exists for, so a failing master-data
// catalogue request must never leave it empty: load the catalogues separately
// and only the stage request decides the empty/error state.
async function loadStages() {
  loading.value = true
  loadFailed.value = false
  try {
    stages.value = await ApplicantApi.stageDefinitions(service.value)
  }
  catch {
    stages.value = []
    loadFailed.value = true
  }
  finally {
    loading.value = false
  }
}

async function loadCatalogs() {
  const [countryRows, universityRows] = await Promise.allSettled([MasterDataApi.list('countries'), MasterDataApi.list('universities')])

  if (countryRows.status === 'fulfilled')
    countries.value = countryRows.value

  if (universityRows.status === 'fulfilled')
    universities.value = universityRows.value
}

const load = async () => {
  await Promise.all([loadStages(), loadCatalogs()])
}

function open(stage?: ApplicantStage) {
  editing.value = stage ?? null
  form.value = stage
    ? { ...stage, allowed_next_keys: [...(stage.allowed_next_keys ?? [])] }
    : { key: '', name: '', service: service.value, sort_order: 0, is_active: true, is_terminal: false, allowed_next_keys: [] }
  dialog.value = true
}

async function save() {
  saving.value = true
  try {
    if (editing.value) {
      // The service is part of the stage's identity: an existing stage is
      // edited inside its own service and never reparented.
      await ApplicantApi.updateStageDefinition(editing.value.id, {
        name: form.value.name,
        country_id: form.value.country_id ?? null,
        university_id: form.value.university_id ?? null,
        sort_order: form.value.sort_order ?? 0,
        allowed_next_keys: form.value.allowed_next_keys ?? [],
        is_terminal: !!form.value.is_terminal,
        is_active: !!form.value.is_active,
      })
    }
    else {
      await ApplicantApi.createStage({ ...form.value, service: service.value })
    }

    dialog.value = false
    await loadStages()
    toast.success(t('educational.masterData.saved'))
  }
  catch {
    toast.error(t('educational.masterData.saveFailed'))
  }
  finally {
    saving.value = false
  }
}

async function remove(stage: ApplicantStage) {
  if (!confirm(t('educational.masterData.removeConfirm', { name: translateApplicantStage(stage) })))
    return

  try {
    await ApplicantApi.removeStageDefinition(stage.id)
    await loadStages()
  }
  catch {
    toast.error(t('educational.masterData.inUse'))
  }
}

watch(service, loadStages)
onMounted(load)
</script>

<template>
  <div class="education-workspace">
    <VCard class="education-panel">
      <VCardTitle class="d-flex flex-wrap align-center ga-3">
        <span>{{ $t('educational.stage') }}</span>
        <VSpacer />
        <VSelect
          v-model="service"
          :items="serviceOptions"
          :label="$t('educational.service')"
          class="stage-service-select"
          density="compact"
          hide-details
        />
        <VBtn v-if="canManage" color="primary" prepend-icon="tabler-plus" @click="open()">
          {{ $t('common.actions.add') }}
        </VBtn>
      </VCardTitle>
      <VCardText>
        <VProgressLinear v-if="loading" indeterminate class="mb-3" />
        <VAlert v-if="loadFailed" type="error" variant="tonal" class="mb-3">
          {{ $t('educational.masterData.loadFailed') }}
          <template #append>
            <VBtn size="small" variant="text" @click="loadStages">{{ $t('common.actions.refresh') }}</VBtn>
          </template>
        </VAlert>
        <div class="education-table-scroll">
          <VTable density="compact" class="education-table">
            <thead>
              <tr>
                <th>{{ $t('common.fields.name') }}</th>
                <th>{{ $t('common.fields.code') }}</th>
                <th>{{ $t('educational.service') }}</th>
                <th>{{ $t('educational.masterData.displayOrder') }}</th>
                <th>{{ $t('educational.masterData.allowedNextStages') }}</th>
                <th>{{ $t('educational.country') }} / {{ $t('educational.university') }}</th>
                <th>{{ $t('common.fields.status') }}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="stage in stages" :key="stage.id">
                <td>{{ translateApplicantStage(stage) }}</td>
                <td><code>{{ stage.key }}</code></td>
                <td>{{ serviceTitle(stage.service) }}</td>
                <td>{{ stage.sort_order ?? 0 }}</td>
                <td>{{ nextStagesLabel(stage) }}</td>
                <td>{{ scopeLabel(stage) }}</td>
                <td>
                  <VChip size="small" :color="stage.is_active ? 'success' : 'secondary'">
                    {{ stage.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}
                  </VChip>
                  <VChip v-if="stage.is_terminal" class="ms-2" size="small" color="warning">
                    {{ $t('educational.masterData.terminalStage') }}
                  </VChip>
                </td>
                <td class="text-no-wrap">
                  <template v-if="canManage">
                    <VBtn icon="tabler-pencil" size="small" variant="text" @click="open(stage)" />
                    <VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(stage)" />
                  </template>
                </td>
              </tr>
              <tr v-if="!loading && !stages.length">
                <td colspan="8" class="text-center text-medium-emphasis py-6">
                  {{ loadFailed ? $t('educational.masterData.loadFailed') : $t('educational.noActiveStages') }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>
    </VCard>

    <VDialog v-model="dialog" max-width="640">
      <VCard>
        <VCardTitle>{{ $t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: $t('educational.stage') }) }}</VCardTitle>
        <VCardText>
          <VRow dense>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.key" :label="$t('common.fields.code')" :disabled="!!editing" :hint="$t('educational.masterData.stageKeyHint')" persistent-hint class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect v-model="form.service" :items="serviceOptions" :label="$t('educational.service')" :disabled="!!editing" class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="form.sort_order" type="number" min="0" :label="$t('educational.masterData.displayOrder')" :hint="$t('educational.masterData.stageOrderHint')" persistent-hint class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect v-model="form.country_id" :items="countries.filter(row => row.is_active).map(row => ({ title: row.name, value: row.id }))" :label="$t('educational.country')" :hint="$t('educational.masterData.countryScopeHint')" clearable class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect v-model="form.university_id" :items="universities.filter(row => !form.country_id || row.country_id === form.country_id).map(row => ({ title: row.name, value: row.id }))" :label="$t('educational.university')" :hint="$t('educational.masterData.universityScopeHint')" clearable class="mb-4" />
            </VCol>
            <VCol cols="12">
              <VCombobox v-model="form.allowed_next_keys" :items="nextStageOptions" item-title="title" item-value="value" multiple chips :label="$t('educational.masterData.allowedNextStages')" :hint="$t('educational.masterData.allowedNextStagesHint')" persistent-hint class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VSwitch v-model="form.is_terminal" :label="$t('educational.masterData.terminalStage')" />
              <div class="text-caption text-medium-emphasis">{{ $t('educational.masterData.terminalStageHint') }}</div>
            </VCol>
            <VCol cols="12" sm="6">
              <VSwitch v-model="form.is_active" :label="$t('common.statuses.active')" />
              <div class="text-caption text-medium-emphasis">{{ $t('educational.masterData.activeStageHint') }}</div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" :loading="saving" :disabled="!form.name || (!editing && !form.key)" @click="save">{{ $t('common.actions.save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.stage-service-select {
  inline-size: 220px;
  max-inline-size: 100%;
}
</style>
