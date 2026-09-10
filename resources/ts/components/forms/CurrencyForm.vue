<script setup lang="ts">
import { ref, watch } from 'vue'

interface CurrencyFormData { name: string; code: string; symbol: string; decimalPlaces: number; rate: number; isBase?: boolean; isActive: boolean }
const props = defineProps<{ modelValue: boolean; initialData: CurrencyFormData; currentBaseCurrency?: { code: string }; errors: Record<string, string>; loading: boolean; editing: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [value: CurrencyFormData] }>()
const form = ref<CurrencyFormData>({ ...props.initialData })

watch(() => props.modelValue, open => {
  if (open)
    form.value = { ...props.initialData }
})
function close() {
  if (!props.loading)
    emit('update:modelValue', false)
}
function submit() { emit('submit', { ...form.value, decimalPlaces: Number(form.value.decimalPlaces), rate: form.value.isBase ? 1 : Number(form.value.rate) }) }
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="540"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5">
        <span>{{ editing ? 'Edit Currency' : 'Add New Currency' }}</span><VBtn
          icon="tabler-x"
          variant="text"
          density="compact"
          @click="close"
        />
      </VCardTitle>
      <VDivider />
      <VForm @submit.prevent="submit">
        <VCardText class="pa-5">
          <VRow density="comfortable">
            <VCol
              cols="12"
              sm="8"
            >
              <VTextField
                v-model="form.name"
                :label="$t('pages.finance.currencies.currencies.currencyName')"
                variant="outlined"
                required
:error-messages="errors.name"
              />
            </VCol><VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="form.code"
                :label="$t('pages.finance.currencies.currencies.codeShortName')"
                variant="outlined"
                maxlength="5"
required :error-messages="errors.code"
              />
            </VCol><VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.symbol"
                :label="$t('pages.finance.currencies.currencies.symbol')"
                variant="outlined"
                :error-messages="errors.symbol"
              />
            </VCol><VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model.number="form.decimalPlaces"
                :label="$t('pages.finance.currencies.currencies.decimalPrecision')"
                type="number"
                min="0"
max="6" variant="outlined" :error-messages="errors.decimal_places"
              />
            </VCol><VCol cols="12">
              <VTextField
                v-model.number="form.rate"
                :label="$t('pages.finance.currencies.rateToBase', { currency: currentBaseCurrency?.code || $t('common.messages.notAvailable') })"
                type="number"
                step="0.00000001"
min="0.00000001" variant="outlined" :disabled="form.isBase" :error-messages="errors.rate"
              /><div class="text-caption text-medium-emphasis mt-1">
                1 {{ currentBaseCurrency?.code || 'BASE' }} = {{ form.rate || 0 }} {{ form.code || 'currency units' }}
              </div>
            </VCol><VCol
              cols="12"
              sm="6"
            >
              <VSwitch
                v-model="form.isBase"
                :label="$t('pages.finance.currencies.currencies.setAsPrimaryBase')"
                color="primary"
                hide-details
:disabled="editing && form.isBase" :error-messages="errors.is_base"
              />
            </VCol><VCol
              cols="12"
              sm="6"
            >
              <VSwitch
                v-model="form.isActive"
                :label="$t('pages.finance.currencies.currencies.activeStatus')"
                color="success"
                :disabled="form.isBase"
hide-details :error-messages="errors.is_active"
              />
            </VCol>
          </VRow>
        </VCardText><VDivider /><VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="loading"
            @click="close"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="primary"
            elevation="1"
            type="submit"
            :loading="loading"
          >
            {{ editing ? 'Save Changes' : 'Create Currency' }}
          </VBtn>
        </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>
