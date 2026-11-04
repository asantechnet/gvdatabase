<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantStagesApi, { type ApplicantStage } from '@/api/applicantStages'
import type { BuiltInService } from '@/api/applicants'
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
const tests = ref<MasterDataRecord[]>([])
const loading = ref(false)
const loadFailed = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editing = ref<ApplicantStage | null>(null)
const canManage = computed(() => canAccess('manage_applicant_stages'))
const blankForm = () => ({
  name: '',
  sort_order: 0,
  is_active: true,
  country_id: null as number | null,
  university_id: null as number | null,
  test_id: null as number | null,
  allowed_next_keys: [] as string[],
})
const form = ref(blankForm())
const formService = computed<BuiltInService>(() => editing.value?.service ?? service.value)
// Destination scope belongs to the admission workflows only.
const usesAdmissionScope = computed(() => formService.value === 'scholarship' || formService.value === 'private')
const usesTestScope = computed(() => formService.value === 'test_offer')
const serviceTitle = (value?: BuiltInService | null) => serviceOptions.value.find(option => option.value === value)?.title ?? '—'
const stageNameByKey = computed(() => Object.fromEntries(stages.value.map(stage => [stage.key, translateApplicantStage(stage)])))
const nextStagesLabel = (stage: ApplicantStage) => (stage.allowed_next_keys ?? [])
  .map(key => stageNameByKey.value[key])
  .filter(Boolean)
  .join(', ') || '—'
const testNameById = computed(() => Object.fromEntries(tests.value.map(row => [row.id, row.name])))
const testOptions = computed(() => [
  { title: t('educational.masterData.allTests'), value: null },
  ...tests.value.filter(row => row.is_active).map(row => ({ title: row.name, value: row.id })),
])
const countryOptions = computed(() => countries.value.filter(row => row.is_active).map(row => ({ title: row.name, value: row.id })))
const universityOptions = computed(() => universities.value
  .filter(row => row.is_active && (!form.value.country_id || row.country_id === form.value.country_id))
  .map(row => ({ title: row.name, value: row.id })))
// The table shows one dependency column only where the service has one.
const dependencyHeader = computed(() => {
  if (formService.value === 'academy')
    return null
  if (usesTestScope.value)
    return t('educational.masterData.testScope')

  return `${t('educational.country')} / ${t('educational.university')}`
})
const columnCount = computed(() => 6 + (dependencyHeader.value ? 1 : 0))
const scopeLabel = (stage: ApplicantStage) => {
  if (stage.service === 'academy')
    return t('educational.masterData.noDependency')
  if (stage.service === 'test_offer') {
    return stage.test_id
      ? testNameById.value[stage.test_id] ?? '—'
      : t('educational.masterData.allTests')
  }
  const country = countries.value.find(row => row.id === stage.country_id)?.name
  const university = universities.value.find(row => row.id === stage.university_id)?.name

  return [country, university].filter(Boolean).join(' · ') || '—'
}
const nextStageOptions = computed(() => stages.value
  .filter(stage => stage.id !== editing.value?.id)
  .map(stage => ({ title: translateApplicantStage(stage), value: stage.key })))

// The stage list is what the page exists for, so a failing master-data
// catalogue request must never leave it empty: load the catalogues separately
// and only the stage request decides the empty/error state.
async function loadStages() {
  loading.value = true
  loadFailed.value = false
  try {
    stages.value = await ApplicantStagesApi.catalogue(service.value)
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
  const [countryRows, universityRows, testRows] = await Promise.allSettled([
    MasterDataApi.list('countries'),
    MasterDataApi.list('universities'),
    MasterDataApi.list('tests'),
  ])

  if (countryRows.status === 'fulfilled')
    countries.value = countryRows.value

  if (universityRows.status === 'fulfilled')
    universities.value = universityRows.value

  if (testRows.status === 'fulfilled')
    tests.value = testRows.value
}

const load = async () => {
  await Promise.all([loadStages(), loadCatalogs()])
}

function open(stage?: ApplicantStage) {
  // The API refuses a locked row; the editor should never offer the attempt.
  if (stage?.is_locked)
    return

  editing.value = stage ?? null
  form.value = stage
    ? {
        name: stage.name,
        sort_order: stage.sort_order ?? 0,
        is_active: stage.is_active ?? true,
        country_id: stage.country_id ?? null,
        university_id: stage.university_id ?? null,
        test_id: stage.test_id ?? null,
        allowed_next_keys: [...(stage.allowed_next_keys ?? [])],
      }
    : blankForm()
  dialog.value = true
}

async function save() {
  saving.value = true
  try {
    // Scope values outside this service are omitted so the server's
    // normalization is the only rule; `is_terminal` is never sent because the
    // terminal set is system-owned.
    const payload = {
      name: form.value.name,
      country_id: usesAdmissionScope.value ? form.value.country_id : null,
      university_id: usesAdmissionScope.value ? form.value.university_id : null,
      test_id: usesTestScope.value ? form.value.test_id : null,
      sort_order: form.value.sort_order ?? 0,
      allowed_next_keys: form.value.allowed_next_keys ?? [],
      is_active: !!form.value.is_active,
    }

    if (editing.value) {
      // The service is part of the stage's identity: an existing stage is
      // edited inside its own service and never reparented.
      await ApplicantStagesApi.update(editing.value.id, payload)
    }
    else {
      await ApplicantStagesApi.create({ ...payload, service: service.value })
    }

    dialog.value = false
    await loadStages()
    toast.success(t('educational.masterData.saved'))
  }
  catch {
    // The global API notifier owns failure feedback; a page toast here would be
    // a second message for the same request (Task-018).
  }
  finally {
    saving.value = false
  }
}

async function remove(stage: ApplicantStage) {
  if (stage.is_locked)
    return

  if (!confirm(t('educational.masterData.removeConfirm', { name: translateApplicantStage(stage) })))
    return

  try {
    await ApplicantStagesApi.remove(stage.id)
    await loadStages()
  }
  catch {
    // A locked or in-use row is explained by the server's specific 409 message.
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
                <th>{{ $t('educational.service') }}</th>
                <th>{{ $t('educational.masterData.displayOrder') }}</th>
                <th>{{ $t('educational.masterData.allowedNextStages') }}</th>
                <th v-if="dependencyHeader">
                  {{ dependencyHeader }}
                </th>
                <th>{{ $t('common.fields.status') }}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="stage in stages" :key="stage.id">
                <td>{{ translateApplicantStage(stage) }}</td>
                <td>{{ serviceTitle(stage.service) }}</td>
                <td>{{ stage.sort_order ?? 0 }}</td>
                <td>{{ nextStagesLabel(stage) }}</td>
                <td v-if="dependencyHeader">
                  {{ scopeLabel(stage) }}
                </td>
                <td>
                  <VChip size="small" :color="stage.is_active ? 'success' : 'secondary'">
                    {{ stage.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}
                  </VChip>
                  <VChip v-if="stage.is_terminal" class="ms-2" size="small" color="warning">
                    {{ $t('educational.masterData.terminalStage') }}
                  </VChip>
                  <VChip
                    v-if="stage.is_locked"
                    class="ms-2"
                    size="small"
                    color="secondary"
                    prepend-icon="tabler-lock"
                    :title="$t('educational.masterData.lockedStageHint')"
                  >
                    {{ $t('educational.masterData.lockedStage') }}
                  </VChip>
                </td>
                <td class="text-no-wrap">
                  <template v-if="canManage && !stage.is_locked">
                    <VBtn icon="tabler-pencil" size="small" variant="text" @click="open(stage)" />
                    <VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(stage)" />
                  </template>
                </td>
              </tr>
              <tr v-if="!loading && !stages.length">
                <td :colspan="columnCount" class="text-center text-medium-emphasis py-6">
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
              <VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="form.sort_order" type="number" min="0" :label="$t('educational.masterData.displayOrder')" :hint="$t('educational.masterData.stageOrderHint')" persistent-hint class="mb-4" />
            </VCol>
            <VCol v-if="usesAdmissionScope" cols="12" sm="6">
              <VSelect v-model="form.country_id" :items="countryOptions" :label="$t('educational.country')" :hint="$t('educational.masterData.countryScopeHint')" clearable class="mb-4" />
            </VCol>
            <VCol v-if="usesAdmissionScope" cols="12" sm="6">
              <VSelect v-model="form.university_id" :items="universityOptions" :label="$t('educational.university')" :hint="$t('educational.masterData.universityScopeHint')" clearable class="mb-4" />
            </VCol>
            <VCol v-if="usesTestScope" cols="12" sm="6">
              <VSelect v-model="form.test_id" :items="testOptions" :label="$t('educational.masterData.testScope')" :hint="$t('educational.masterData.testScopeHint')" clearable class="mb-4" />
            </VCol>
            <VCol v-if="formService === 'academy'" cols="12">
              <VAlert type="info" variant="tonal" density="compact" class="mb-4">
                {{ $t('educational.masterData.noDependency') }}
              </VAlert>
            </VCol>
            <VCol cols="12">
              <!-- A multi-select, not a combobox: Vuetify's combobox returns the item object,
                   and the API stores stage keys as a string array. -->
              <VSelect v-model="form.allowed_next_keys" :items="nextStageOptions" item-title="title" item-value="value" multiple chips closable-chips :label="$t('educational.masterData.allowedNextStages')" :hint="$t('educational.masterData.allowedNextStagesHint')" persistent-hint class="mb-4" />
            </VCol>
            <VCol cols="12" sm="6">
              <VSwitch v-model="form.is_active" :label="$t('common.statuses.active')" />
            </VCol>
            <VCol cols="12">
              <div class="text-caption text-medium-emphasis">{{ $t('educational.masterData.terminalManaged') }}</div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" :loading="saving" :disabled="!form.name" @click="save">{{ $t('common.actions.save') }}</VBtn>
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
