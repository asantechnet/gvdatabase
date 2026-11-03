<script setup lang="ts">
import { translate } from '@/utils/localization'
import { useToast } from 'vue-toastification'
import DepartmentApi, { type Department } from '@/api/department'
import EmployeeApi, { type Employee } from '@/api/employee'
import { apiFieldErrors } from '@/utils/apiErrors'

const props = defineProps<{
  modelValue: boolean
  department: Department | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: [department: Department]
  closed: []
}>()

const toast = useToast()

const selectedIds = ref<number[]>([])
const initialIds = ref<number[]>([])
const employees = ref<Employee[]>([])
const loadingEmployees = ref(false)
const saving = ref(false)
const addModel = ref<number | null>(null)
const memberErrors = ref<Record<number, string>>({})
const generalError = ref<string | null>(null)
const submittedOrder = ref<number[]>([])

const open = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const managerId = computed(() => props.department?.manager_employee_id ?? null)

const currentMembers = computed(() => {
  const members = props.department?.members ?? []

  return members.filter(member => selectedIds.value.includes(member.id))
})

const managerNotMember = computed(() => {
  const id = managerId.value

  return id !== null && !selectedIds.value.includes(id)
})

const managerName = computed(() => {
  const id = managerId.value
  if (id === null)
    return null

  return props.department?.manager_name
    ?? currentMembers.value.find(member => member.id === id)?.name
    ?? null
})

const availableOptions = computed(() => employees.value
  .filter(employee => employee.status === 'active' && !selectedIds.value.includes(employee.id))
  .map(employee => ({
    title: employee.name,
    value: employee.id,
    subtitle: [employee.position, employee.department_name].filter(Boolean).join(' · '),
  })))

const addedCount = computed(() => selectedIds.value.filter(id => !initialIds.value.includes(id)).length)
const removedCount = computed(() => initialIds.value.filter(id => !selectedIds.value.includes(id)).length)

const selectionSummary = computed(() => {
  const parts: string[] = []

  if (addedCount.value)
    parts.push(`+${addedCount.value}`)

  if (removedCount.value)
    parts.push(`-${removedCount.value}`)

  return parts.join(' / ')
})

function removeMember(id: number) {
  if (id === managerId.value)
    return

  selectedIds.value = selectedIds.value.filter(memberId => memberId !== id)
  delete memberErrors.value[id]
  generalError.value = null
}

function addEmployee() {
  const id = addModel.value
  if (id === null || selectedIds.value.includes(id))
    return

  selectedIds.value = [...selectedIds.value, id]
  addModel.value = null
  delete memberErrors.value[id]
  generalError.value = null
}

function statusLabel(status: 'active' | 'inactive'): string {
  return status === 'active' ? translate('serviceContracts.active') : translate('common.statuses.inactive')
}

function resetState() {
  selectedIds.value = [...(props.department?.members ?? []).map(member => member.id)]
  initialIds.value = [...selectedIds.value]
  memberErrors.value = {}
  generalError.value = null
  addModel.value = null
}

watch(() => props.modelValue, (value) => {
  if (!value)
    return

  resetState()
  void loadEmployees()
}, { immediate: true })

watch(() => props.department?.id, () => {
  if (props.modelValue)
    resetState()
})

async function loadEmployees() {
  if (employees.value.length)
    return

  loadingEmployees.value = true
  try {
    const options = await EmployeeApi.getOptions()
    employees.value = Array.isArray(options) ? options : []
  }
  catch {
    employees.value = []
  }
  finally {
    loadingEmployees.value = false
  }
}

async function save() {
  const department = props.department

  if (!department)
    return

  submittedOrder.value = [...selectedIds.value]
  memberErrors.value = {}
  generalError.value = null
  saving.value = true

  try {
    const saved = await DepartmentApi.update(department.id, { employee_ids: selectedIds.value })
    toast.success(translate('common.ui.departmentMembersSaved').replace('{department}', department.name))
    emit('saved', saved)
    open.value = false
  }
  catch (error) {
    const fields = apiFieldErrors(error)

    for (const [field, message] of Object.entries(fields)) {
      const indexed = field.match(/^employee_ids\.(\d+)$/)

      if (indexed) {
        const id = submittedOrder.value[Number(indexed[1])]
        if (id !== undefined)
          memberErrors.value[id] = message

        continue
      }

      generalError.value = message
    }

    if (!generalError.value && !Object.keys(memberErrors.value).length)
      generalError.value = translate('common.ui.departmentMembersInlineError')
    // The shared notifier owns the toast; the dialog stays open with inline errors.
  }
  finally {
    saving.value = false
  }
}

function close() {
  open.value = false
}

function onAfterLeave() {
  emit('closed')
}
</script>

<template>
  <VDialog v-model="open" max-width="760" scrollable @after-leave="onAfterLeave">
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between ga-3 flex-wrap">
        <span>{{ $t('common.ui.departmentMembersTitle') }}</span>
        <IconBtn :aria-label="$t('common.ui.departmentMembersClose')" @click="close">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VCardText class="department-members-body">
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ $t('common.ui.departmentMembersDescription', { department: department?.name ?? '' }) }}
        </p>

        <VAlert type="info" variant="tonal" class="mb-4">
          <div class="font-weight-medium mb-1">{{ $t('common.ui.departmentMembersRulesTitle') }}</div>
          <ul class="department-members-rules">
            <li>{{ $t('common.ui.departmentMembersRuleMultiple') }}</li>
            <li>{{ $t('common.ui.departmentMembersRuleManager') }}</li>
            <li>{{ $t('common.ui.departmentMembersRulePrimary') }}</li>
          </ul>
        </VAlert>

        <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-2">
          <div class="text-subtitle-2">{{ $t('common.ui.departmentMembersCurrent') }}</div>
          <VChip size="small" variant="tonal">{{ selectedIds.length }} {{ $t('common.ui.members') }}</VChip>
        </div>

        <VAlert v-if="managerNotMember" type="warning" variant="tonal" density="compact" class="mb-3">
          {{ $t('common.ui.departmentMembersManagerNotMember') }}
          <span v-if="managerName"> — {{ managerName }}</span>
        </VAlert>

        <VAlert v-if="generalError" type="error" variant="tonal" density="compact" class="mb-3">
          {{ generalError }}
        </VAlert>

        <div v-if="!currentMembers.length" class="department-members-empty text-body-2 text-medium-emphasis">
          {{ $t('common.ui.departmentMembersEmpty') }}
        </div>

        <VTable v-else class="department-members-table">
          <thead>
            <tr>
              <th>{{ $t('common.fields.name') }}</th>
              <th>{{ $t('common.fields.position') }}</th>
              <th>{{ $t('common.fields.role') }}</th>
              <th>{{ $t('serviceContracts.status') }}</th>
              <th class="text-end">{{ $t('serviceContracts.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in currentMembers" :key="member.id">
              <td>
                <div class="d-flex align-center flex-wrap ga-2">
                  <span>{{ member.name }}</span>
                  <VChip v-if="member.is_primary" size="x-small" color="primary" variant="tonal">
                    {{ $t('common.ui.departmentMembersPrimary') }}
                  </VChip>
                  <VChip v-if="member.is_manager" size="x-small" color="info" variant="tonal">
                    {{ $t('common.ui.departmentMembersManager') }}
                  </VChip>
                </div>
                <div v-if="member.supervisor_name" class="text-caption text-medium-emphasis">
                  {{ $t('common.fields.supervisor') }}: {{ member.supervisor_name }}
                </div>
                <div v-if="memberErrors[member.id]" class="text-error text-caption mt-1" role="alert">
                  {{ memberErrors[member.id] }}
                </div>
              </td>
              <td>{{ member.position || $t('common.ui.notAssigned') }}</td>
              <td>{{ member.role || $t('common.ui.notAssigned') }}</td>
              <td>{{ statusLabel(member.status) }}</td>
              <td class="text-end">
                <VTooltip :text="$t('common.ui.departmentMembersManagerCannotBeRemoved')" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <span v-bind="tooltipProps">
                      <IconBtn
                        :aria-label="$t('common.ui.departmentMembersRemove', { name: member.name })"
                        :disabled="member.is_manager || saving"
                        color="error"
                        @click="removeMember(member.id)"
                      >
                        <VIcon icon="tabler-user-minus" />
                      </IconBtn>
                    </span>
                  </template>
                </VTooltip>
              </td>
            </tr>
          </tbody>
        </VTable>

        <div class="mt-5">
          <div class="text-subtitle-2 mb-1">{{ $t('common.ui.departmentMembersAdd') }}</div>
          <div class="text-caption text-medium-emphasis mb-2">{{ $t('common.ui.departmentMembersAddHint') }}</div>
          <div class="d-flex align-start ga-2 flex-wrap">
            <VAutocomplete
              v-model="addModel"
              :items="availableOptions"
              :loading="loadingEmployees"
              :label="$t('common.ui.departmentMembersSearch')"
              :no-data-text="$t('common.ui.departmentMembersNoAvailable')"
              item-title="title"
              item-value="value"
              class="flex-grow-1"
              hide-details
              clearable
            >
              <template #item="{ props: itemProps, item }">
                <VListItem v-bind="itemProps" :subtitle="item.raw.subtitle" />
              </template>
            </VAutocomplete>
            <VBtn color="primary" variant="tonal" :disabled="addModel === null" prepend-icon="tabler-plus" @click="addEmployee">
              {{ $t('common.ui.departmentMembersAdd') }}
            </VBtn>
          </div>
          <div v-if="!loadingEmployees && !availableOptions.length" class="text-caption text-medium-emphasis mt-2">
            {{ $t('common.ui.departmentMembersNoAvailable') }}
          </div>
        </div>
      </VCardText>

      <VCardActions class="department-members-actions flex-wrap ga-2">
        <span v-if="selectionSummary" class="text-caption text-medium-emphasis">{{ selectionSummary }}</span>
        <VSpacer />
        <VBtn variant="tonal" :disabled="saving" @click="close">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="saving" @click="save">{{ $t('common.ui.departmentMembersSave') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.department-members-body {
  max-block-size: min(70vh, 620px);
}

.department-members-rules {
  margin-inline-start: 1.1rem;
  padding: 0;
  font-size: 0.8125rem;
  line-height: 1.5;
}

.department-members-empty {
  padding: 1.25rem;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  text-align: center;
}

.department-members-table {
  font-size: 0.8125rem;
}

.department-members-table :deep(th),
.department-members-table :deep(td) {
  padding-block: 0.5rem;
  padding-inline: 0.5rem;
}

.department-members-actions {
  position: sticky;
  inset-block-end: 0;
  background: rgb(var(--v-theme-surface));
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

@media (max-width: 600px) {
  .department-members-table :deep(th:nth-child(2)),
  .department-members-table :deep(td:nth-child(2)),
  .department-members-table :deep(th:nth-child(3)),
  .department-members-table :deep(td:nth-child(3)) {
    display: none;
  }
}
</style>
