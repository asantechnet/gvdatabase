<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import PaymentDetailApi, { type PaymentDetail, type PaymentDetailPayload } from '@/api/paymentDetails'
import { apiErrorMessage } from '@/utils/apiErrors'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const records = ref<PaymentDetail[]>([])
const loading = ref(false)
const search = ref('')
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editing = ref<PaymentDetail | null>(null)
const deleting = ref<PaymentDetail | null>(null)
const form = ref<PaymentDetailPayload>({ method: '', instruction: '' })

const headers = [
  { title: translate('pages.paymentDetails.method'), key: 'method' },
  { title: translate('pages.paymentDetails.instruction'), key: 'instruction', sortable: false },
  { title: translate('pages.paymentDetails.updated'), key: 'updated_at' },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const filteredRecords = computed(() => {
  const query = search.value.trim().toLowerCase()

  return records.value.filter(record => !query || `${record.method} ${record.instruction}`.toLowerCase().includes(query))
})

const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))

async function load() {
  loading.value = true
  try {
    records.value = await PaymentDetailApi.getAll()
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.paymentDetails.loadFailed')))
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { method: '', instruction: '' }
  dialogOpen.value = true
}

function openEdit(record: PaymentDetail) {
  editing.value = record
  form.value = { method: record.method, instruction: record.instruction }
  dialogOpen.value = true
}

async function save() {
  if (!form.value.method.trim() || !form.value.instruction.trim())
    return toast.error(translate('pages.paymentDetails.required'))

  loading.value = true

  const payload = { method: form.value.method.trim(), instruction: form.value.instruction.trim() }
  try {
    const saved = editing.value
      ? await PaymentDetailApi.update(editing.value.id, payload)
      : await PaymentDetailApi.create(payload)

    const index = records.value.findIndex(record => record.id === saved.id)
    if (index === -1)
      records.value.unshift(saved)
    else records.value[index] = saved
    dialogOpen.value = false
    toast.success(translate('pages.paymentDetails.saved'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.paymentDetails.saveFailed')))
  }
  finally {
    loading.value = false
  }
}

async function confirmDelete() {
  if (!deleting.value)
    return
  loading.value = true
  try {
    await PaymentDetailApi.delete(deleting.value.id)
    records.value = records.value.filter(record => record.id !== deleting.value?.id)
    deleteDialogOpen.value = false
    toast.success(translate('pages.paymentDetails.deleted'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.paymentDetails.deleteFailed')))
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="payment-details-page">
    <DataTablePageKpis
      :items="filteredRecords"
      title="Payment details"
      description="Review payment activity and keep every transaction easy to trace."
      icon="tabler-credit-card-pay"
    />
    <VCard
      class="payment-details-hero mb-6"
      variant="tonal"
      color="primary"
    >
      <VCardText class="d-flex flex-wrap align-center justify-space-between ga-5 pa-6">
        <div class="d-flex align-center ga-4">
          <VAvatar
            color="primary"
            variant="flat"
            size="52"
            rounded="lg"
          >
            <VIcon
              icon="tabler-credit-card-pay"
              size="27"
            />
          </VAvatar>
          <div>
            <h1 class="text-h4 font-weight-bold mb-1">
              {{ $t('pages.paymentDetails.title') }}
            </h1><p class="text-body-2 text-medium-emphasis mb-0">
              {{ $t('pages.paymentDetails.subtitle') }}
            </p>
          </div>
        </div>
        <VChip
          prepend-icon="tabler-wallet"
          variant="flat"
          color="primary"
        >
          {{ $t('pages.paymentDetails.totalProfiles', { count: records.length }) }}
        </VChip>
      </VCardText>
    </VCard>

    <VCard :loading="loading">
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="7"
          >
            <VTextField
              v-model="search"
              :label="$t('pages.paymentDetails.search')"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="5"
            class="d-flex justify-md-end"
          >
            <VBtn
              v-if="canAccess('create_payment_details')"
              prepend-icon="tabler-plus"
              @click="openCreate"
            >
              {{ $t('pages.paymentDetails.newPaymentDetail') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <ResponsiveDataTable
        :headers="headers"
        :items="filteredRecords"
        item-value="id"
        :loading="loading"
        class="payment-details-table"
      >
        <template #item.method="{ item }">
          <div class="d-flex align-center ga-3">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
            >
              <VIcon
                icon="tabler-credit-card"
                size="19"
              />
            </VAvatar><span class="font-weight-bold">{{ item.method }}</span>
          </div>
        </template>
        <template #item.instruction="{ item }">
          <p class="payment-instruction-preview mb-0">
            {{ item.instruction }}
          </p>
        </template>
        <template #item.updated_at="{ item }">
          <span class="text-medium-emphasis">{{ formatDate(item.updated_at) }}</span>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end ga-1">
            <FullDetailsButton
              :item="item"
              :title="item.method"
            />
            <IconBtn
              v-if="canAccess('edit_payment_details')"
              :aria-label="$t('common.actions.edit')"
              @click="openEdit(item)"
            >
              <VIcon icon="tabler-pencil" />
            </IconBtn>
            <IconBtn
              v-if="canAccess('delete_payment_details')"
              color="error"
              :aria-label="$t('common.actions.delete')"
              @click="deleting = item; deleteDialogOpen = true"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>
        <template #no-data>
          <div class="py-10 text-center">
            <VIcon
              icon="tabler-credit-card-off"
              size="38"
              color="secondary"
              class="mb-2"
            /><div class="text-medium-emphasis">
              {{ $t('pages.paymentDetails.empty') }}
            </div>
          </div>
        </template>
      </ResponsiveDataTable>
    </VCard>

    <VDialog
      v-model="dialogOpen"
      max-width="650"
    >
      <VCard
        class="app-form-dialog"
        :title="$t(editing ? 'pages.paymentDetails.editTitle' : 'pages.paymentDetails.addTitle')"
      >
        <VForm
          class="app-form"
          @submit.prevent="save"
        >
          <VCardText>
            <VRow>
              <VCol cols="12">
                <p class="app-form__section">
                  {{ $t('pages.paymentDetails.formSection') }}
                </p><p class="app-form__section-help">
                  {{ $t('pages.paymentDetails.formHint') }}
                </p>
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="form.method"
                  :label="$t('pages.paymentDetails.method')"
                  :placeholder="$t('pages.paymentDetails.methodPlaceholder')"
                  prepend-inner-icon="tabler-credit-card"
                  maxlength="255"
                  autofocus
                  required
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="form.instruction"
                  :label="$t('pages.paymentDetails.instruction')"
                  :placeholder="$t('pages.paymentDetails.instructionPlaceholder')"
                  prepend-inner-icon="tabler-list-details"
                  rows="5"
                  auto-grow
                  maxlength="5000"
                  counter
                  required
                />
              </VCol>
            </VRow>
          </VCardText>
          <VCardActions>
            <VSpacer /><VBtn
              variant="tonal"
              color="secondary"
              @click="dialogOpen = false"
            >
              {{ $t('common.actions.cancel') }}
            </VBtn><VBtn
              type="submit"
              prepend-icon="tabler-check"
              :loading="loading"
            >
              {{ $t('common.actions.save') }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>

    <VDialog
      v-model="deleteDialogOpen"
      max-width="440"
    >
      <VCard :title="$t('pages.paymentDetails.deleteTitle')">
        <VCardText>
          <VAlert
            type="warning"
            variant="tonal"
          >
            {{ $t('pages.paymentDetails.deleteConfirm', { method: deleting?.method }) }}
          </VAlert>
        </VCardText><VCardActions>
          <VSpacer /><VBtn
            variant="tonal"
            @click="deleteDialogOpen = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="error"
            :loading="loading"
            @click="confirmDelete"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.payment-details-hero { overflow: hidden; }
.payment-instruction-preview { display: -webkit-box; max-inline-size: 560px; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-height: 1.5; white-space: pre-line; }
@media (max-width: 599px) { .payment-details-hero :deep(.v-card-text) { padding: 1rem !important; } }
</style>
