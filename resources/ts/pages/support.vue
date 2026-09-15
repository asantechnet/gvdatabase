<script setup lang="ts">
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import { type CreateSupportTicketData, type SupportTicket, createSupportTicket, getSupportTickets } from '@/api/supportTicket'
import { currentLocale } from '@/utils/localization'

const toast = useToast()
const { t } = useI18n()
const form = ref()
const submitting = ref(false)
const loading = ref(true)
const tickets = ref<SupportTicket[]>([])
const attachments = ref<File[]>([])

const categoryOptions = computed(() => ['technical', 'account', 'billing', 'feature_request', 'other'].map(value => ({
  value,
  title: t(`pages.support.categories.${value}`),
})))

const severityOptions = computed(() => ['low', 'normal', 'high', 'critical'].map(value => ({
  value,
  title: t(`pages.support.severities.${value}`),
})))

const severityHelp = computed(() => t(`pages.support.severityHelp.${ticketForm.value.severity}`))

const blankForm = (): CreateSupportTicketData => ({
  category: 'technical',
  severity: 'normal',
  subject: '',
  description: '',
})

const ticketForm = ref<CreateSupportTicketData>(blankForm())
const severityColor = (severity: SupportTicket['severity']) => ({ low: 'info', normal: 'primary', high: 'warning', critical: 'error' }[severity])
const severityLabel = (severity: SupportTicket['severity']) => t(`pages.support.severities.${severity}`)
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(currentLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'
const formatFileSize = (bytes: number) => bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`

async function loadTickets() {
  loading.value = true
  try {
    tickets.value = await getSupportTickets()
  }
  catch (error: any) {
    toast.error(error.message || t('pages.support.messages.loadFailed'))
  }
  finally {
    loading.value = false
  }
}

async function submit() {
  const result = await form.value?.validate()
  if (result && !result.valid)
    return

  submitting.value = true
  try {
    const ticket = await createSupportTicket(ticketForm.value, attachments.value)
    tickets.value.unshift(ticket)
    ticketForm.value = blankForm()
    attachments.value = []
    form.value?.resetValidation()
    toast.success(t('pages.support.messages.sent', { ticket: ticket.ticket_number }))
  }
  catch (error: any) {
    toast.error(error.message || t('pages.support.messages.sendFailed'))
  }
  finally {
    submitting.value = false
  }
}

onMounted(loadTickets)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div data-page-title>
        <h4 class="text-h4 mb-1">
          {{ $t('pages.support.title') }}
        </h4>
        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ $t('pages.support.intro') }}
        </p>
      </div>
    </VCol>

    <VCol cols="12" lg="7">
      <VCard>
        <VCardItem>
          <VCardTitle>{{ $t('pages.support.formTitle') }}</VCardTitle>
          <VCardSubtitle>{{ $t('pages.support.formSubtitle') }}</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <VAlert
            v-if="ticketForm.severity === 'critical'"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-5"
          >
            {{ $t('pages.support.criticalNotice') }}
          </VAlert>
          <VForm ref="form" @submit.prevent="submit">
            <VRow>
              <VCol cols="12" sm="6">
                <AppSelect
                  v-model="ticketForm.category"
                  name="category"
                  :label="$t('pages.support.category')"
                  :items="categoryOptions"
                  :rules="[requiredValidator]"
                />
                <p class="text-caption text-medium-emphasis mt-2 mb-0">
                  {{ severityHelp }}
                </p>
              </VCol>
              <VCol cols="12" sm="6">
                <AppSelect
                  v-model="ticketForm.severity"
                  name="severity"
                  :label="$t('pages.support.severity')"
                  :items="severityOptions"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="ticketForm.subject"
                  name="subject"
                  :label="$t('pages.support.subject')"
                  :placeholder="$t('pages.support.subjectPlaceholder')"
                  :rules="[requiredValidator, (value: string) => value.length >= 5 || $t('pages.support.validation.subjectMin')]"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="ticketForm.description"
                  name="description"
                  :label="$t('pages.support.description')"
                  :placeholder="$t('pages.support.descriptionPlaceholder')"
                  rows="6"
                  counter="5000"
                  :rules="[requiredValidator, (value: string) => value.length >= 20 || $t('pages.support.validation.descriptionMin')]"
                />
              </VCol>
              <VCol cols="12">
                <VFileInput
                  v-model="attachments"
                  :label="$t('pages.support.attachments')"
                  :hint="$t('pages.support.attachmentsHint')"
                  persistent-hint
                  multiple
                  show-size
                  chips
                  counter
                  accept="image/png,image/jpeg,image/webp,application/pdf,text/plain,text/csv,.doc,.docx,.xls,.xlsx"
                  prepend-icon="tabler-paperclip"
                />
              </VCol>
              <VCol cols="12" class="d-flex align-center justify-space-between flex-wrap gap-3">
                <span class="text-caption text-medium-emphasis">{{ $t('pages.support.attachmentsPrivacy') }}</span>
                <VBtn type="submit" :loading="submitting">
                  {{ $t('pages.support.send') }}
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12" lg="5">
      <VCard data-page-guide="results" :loading="loading" class="h-100">
        <VCardItem>
          <VCardTitle>{{ $t('pages.support.historyTitle') }}</VCardTitle>
          <VCardSubtitle>{{ $t('pages.support.historySubtitle') }}</VCardSubtitle>
        </VCardItem>
        <VList v-if="tickets.length" lines="three" class="py-0">
          <template v-for="(ticket, index) in tickets" :key="ticket.id">
            <VListItem>
              <VListItemTitle class="d-flex align-center flex-wrap gap-2">
                <span>{{ ticket.subject }}</span>
                <VChip :color="severityColor(ticket.severity)" size="x-small" label>
                  {{ severityLabel(ticket.severity) }}
                </VChip>
              </VListItemTitle>
              <VListItemSubtitle class="support-ticket-summary">
                {{ ticket.ticket_number }} · {{ $t(`pages.support.categories.${ticket.category}`) }} · {{ $t('pages.support.open') }}<br>
                {{ formatDate(ticket.submitted_at) }}
              </VListItemSubtitle>
              <div v-if="ticket.attachments.length" class="d-flex flex-wrap ga-2 mt-2">
                <VBtn
                  v-for="attachment in ticket.attachments"
                  :key="attachment.id"
                  size="x-small"
                  variant="tonal"
                  prepend-icon="tabler-paperclip"
                  :href="attachment.download_url"
                  target="_blank"
                >
                  {{ attachment.name }} ({{ formatFileSize(attachment.size) }})
                </VBtn>
              </div>
            </VListItem>
            <VDivider v-if="index < tickets.length - 1" />
          </template>
        </VList>
        <VCardText v-else-if="!loading" class="text-medium-emphasis">
          {{ $t('pages.support.empty') }}
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.support-ticket-summary { white-space: normal; }
</style>
