<script setup lang="ts">
import { watch } from 'vue'
import ErpField from '@/components/erp/ErpField.vue'

const props = defineProps<{
  form: { title: string; description?: string | null; salary: number; currency_id: number | null; rate: number | null; status: string }
  currencies: Array<{ id: number; name: string; code: string; symbol: string; rate: number; isActive: boolean }>
  loading: boolean
}>()

const emit = defineEmits<{ submit: [] }>()

const currencyOptions = () => props.currencies
  .filter(currency => currency.isActive)
  .map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id }))

watch(() => props.form.currency_id, currencyId => {
  const currency = props.currencies.find(row => row.id === currencyId)
  if (currency)
    props.form.rate = Number(currency.rate)
})
</script>

<template>
  <VForm
    class="app-form"
    @submit.prevent="emit('submit')"
  >
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <ErpField
          v-model="form.title"
          :label="$t('pages.settings.positions.positionTitle')"
          :placeholder="$t('pages.settings.positions.positionTitle')"
          autofocus
          required
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <ErpField
          v-model="form.status"
          kind="select"
          :label="$t('common.fields.status')"
          :items="['active', 'inactive']"
          :item-title="translateBackendValue"
          required
        />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField
          v-model.number="form.salary"
          :label="$t('pages.settings.positions.defaultSalary2')"
          :placeholder="$t('pages.settings.positions.defaultSalary2')"
          type="number"
          min="0"
          required
        />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField
          v-model="form.currency_id"
          kind="select"
          :items="currencyOptions()"
          :label="$t('common.fields.currency')"
          :placeholder="$t('common.messages.selectACurrency')"
          required
        />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField
          v-model.number="form.rate"
          :label="$t('common.messages.exchangeRate')"
          :placeholder="$t('common.messages.exchangeRate')"
          type="number"
          min="0.00000001"
          step="0.00000001"
          required
        />
      </VCol>
      <VCol cols="12">
        <ErpField
          v-model="form.description"
          kind="textarea"
          :label="$t('common.fields.description')"
          :placeholder="$t('common.fields.description')"
          rows="3"
        />
      </VCol>
    </VRow>
  </VForm>
</template>
