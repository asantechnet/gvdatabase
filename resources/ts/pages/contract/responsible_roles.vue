<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ContractResponsibleRolesApi, { type ResponsibleRolePosition } from '@/api/contractResponsibleRoles'
import ErpField from '@/components/erp/ErpField.vue'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const positions = ref<ResponsibleRolePosition[]>([])
const selected = ref<number[]>([])
const initial = ref<number[]>([])
const canManage = computed(() => canAccess('manage_contract_templates'))
const options = computed(() => positions.value.map(position => ({ title: position.title, value: position.id })))
const dirty = computed(() => JSON.stringify([...selected.value].sort((a, b) => a - b)) !== JSON.stringify(initial.value))

function apply(config: { positions: ResponsibleRolePosition[]; selected_ids: number[] }) {
  positions.value = config.positions
  selected.value = [...config.selected_ids]
  initial.value = [...config.selected_ids].sort((a, b) => a - b)
}

async function load() {
  loading.value = true
  try {
    apply(await ContractResponsibleRolesApi.get())
  }
  catch {
    toast.error(t('contractSettings.loadFailed'))
  }
  finally {
    loading.value = false
  }
}

async function save() {
  if (!canManage.value)
    return

  saving.value = true
  try {
    apply(await ContractResponsibleRolesApi.update(selected.value))
    toast.success(t('contractSettings.saved'))
  }
  catch {
    toast.error(t('contractSettings.saveFailed'))
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="contract-responsible-roles">
    <slot name="tabs" />
    <VCardText class="pa-5">
      <div class="contract-responsible-roles__intro">
        <h2>{{ t('contractSettings.title') }}</h2>
        <p>{{ t('contractSettings.description') }}</p>
      </div>

      <VAlert v-if="!loading && !positions.length" color="warning" variant="tonal" class="mb-4">
        {{ t('contractSettings.empty') }}
      </VAlert>

      <ErpField
        v-model="selected"
        kind="select"
        multiple
        chips
        closable-chips
        :items="options"
        :label="t('contractSettings.selectLabel')"
        :placeholder="t('contractSettings.selectLabel')"
        :loading="loading"
        :disabled="!canManage || loading"
        class="contract-responsible-roles__field"
      />
      <p class="contract-responsible-roles__hint">{{ t('contractSettings.hint') }}</p>

      <div class="contract-responsible-roles__actions">
        <span class="text-caption text-medium-emphasis">
          {{ t('contractSettings.selectedCount', { count: selected.length }) }}
        </span>
        <VSpacer />
        <VBtn
          color="primary"
          variant="flat"
          :loading="saving"
          :disabled="!canManage || !dirty || loading"
          @click="save"
        >
          {{ t('contractSettings.save') }}
        </VBtn>
      </div>
    </VCardText>
  </div>
</template>

<style scoped>
.contract-responsible-roles__intro h2 {
  font-size: 1.15rem;
  font-weight: 700;
  margin-block-end: 0.35rem;
}

.contract-responsible-roles__intro p,
.contract-responsible-roles__hint {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
  line-height: 1.55;
}

.contract-responsible-roles__intro p {
  margin-block-end: 1.25rem;
}

.contract-responsible-roles__field {
  max-inline-size: 44rem;
}

.contract-responsible-roles__hint {
  margin-block: 0.35rem 1.25rem;
}

.contract-responsible-roles__actions {
  display: flex;
  align-items: center;
  gap: 1rem;
  max-inline-size: 44rem;
}
</style>
