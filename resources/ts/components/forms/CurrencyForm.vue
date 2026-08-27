<script setup lang="ts">
import { ref, watch } from 'vue'

interface CurrencyFormData { name: string; code: string; symbol: string; decimalPlaces: number; rateToBase: number; isBase: boolean; isActive: boolean }
const props = defineProps<{ modelValue: boolean; initialData: CurrencyFormData; currentBaseCurrency?: { code: string }; errors: Record<string, string>; loading: boolean; editing: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [value: CurrencyFormData] }>()
const form = ref<CurrencyFormData>({ ...props.initialData })
watch(() => props.modelValue, open => { if (open) form.value = { ...props.initialData } })
function close() { if (!props.loading) emit('update:modelValue', false) }
function submit() { emit('submit', { ...form.value, decimalPlaces: Number(form.value.decimalPlaces), rateToBase: form.value.isBase ? 1 : Number(form.value.rateToBase) }) }
</script>
<template>
  <v-dialog :model-value="modelValue" max-width="540" @update:model-value="emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between pa-5"><span>{{ editing ? 'Edit Currency' : 'Add New Currency' }}</span><v-btn icon="tabler-x" variant="text" density="compact" @click="close" /></v-card-title>
      <v-divider />
      <v-form @submit.prevent="submit"><v-card-text class="pa-5"><v-row density="comfortable"><v-col cols="12" sm="8"><v-text-field v-model="form.name" :label="$t('pages.finance.currencies.currencies.currencyName')" variant="outlined" required :error-messages="errors.name" /></v-col><v-col cols="12" sm="4"><v-text-field v-model="form.code" :label="$t('pages.finance.currencies.currencies.codeShortName')" variant="outlined" maxlength="5" required :error-messages="errors.code" /></v-col><v-col cols="12" sm="6"><v-text-field v-model="form.symbol" :label="$t('pages.finance.currencies.currencies.symbol')" variant="outlined" :error-messages="errors.symbol" /></v-col><v-col cols="12" sm="6"><v-text-field v-model.number="form.decimalPlaces" :label="$t('pages.finance.currencies.currencies.decimalPrecision')" type="number" min="0" max="6" variant="outlined" :error-messages="errors.decimal_places" /></v-col><v-col cols="12"><v-text-field v-model.number="form.rateToBase" :label="$t('pages.finance.currencies.rateToBase', { currency: currentBaseCurrency?.code || $t('common.messages.notAvailable') })" type="number" step="0.000001" min="0" variant="outlined" :disabled="form.isBase" :error-messages="errors.rate_to_base" /></v-col><v-col cols="12" sm="6"><v-switch v-model="form.isBase" :label="$t('pages.finance.currencies.currencies.setAsPrimaryBase')" color="primary" hide-details :error-messages="errors.is_base" /></v-col><v-col cols="12" sm="6"><v-switch v-model="form.isActive" :label="$t('pages.finance.currencies.currencies.activeStatus')" color="success" :disabled="form.isBase" hide-details :error-messages="errors.is_active" /></v-col></v-row></v-card-text><v-divider /><v-card-actions class="pa-4 justify-end gap-2"><v-btn variant="outlined" color="secondary" :disabled="loading" @click="close">{{ $t('common.actions.cancel') }}</v-btn><v-btn color="primary" elevation="1" type="submit" :loading="loading">{{ editing ? 'Save Changes' : 'Create Currency' }}</v-btn></v-card-actions></v-form>
    </v-card>
  </v-dialog>
</template>
