<script setup lang="ts">
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import { type CreateSupportTicketData, type SupportTicket, createSupportTicket, getSupportTickets } from '@/api/supportTicket'
import { currentLocale } from '@/utils/localization'
import { SUPPORT_CONTACT } from '@/utils/constants'
import { useSettingsValidation } from '@/composables/useSettingsValidation'
import ErpField from '@/components/erp/ErpField.vue'

// Rendered both as a standalone route and as a tab inside Settings.
const props = withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const toast = useToast()
const { t } = useI18n()
const { inputProps, clear: clearErrors, capture: captureError } = useSettingsValidation()
const requiredField = (field: string) => (value: unknown) => String(value ?? '').trim().length > 0 || t('settingsValidation.required', { field: t(`pages.support.${field}`) })
const form = ref()
const submitting = ref(false)
const loading = ref(true)
const tickets = ref<SupportTicket[]>([])
const attachments = ref<File[]>([])
const attachmentPicker = ref<File[]>([])

const supportAddress = computed(() => ({
  'fa-AF': 'کابل، شهرنو، فاریاب مارکیت',
  'ps-AF': 'کابل، شهرنو، فاریاب مارکېټ',
}[currentLocale()] ?? 'Kabul, Shar-e-Naw, Faryab Market'))
const whatsappHref = computed(() => {
  const message = {
    'fa-AF': 'سلام تیم پشتیبانی آسان‌تک، برای استفاده از سیستم به کمک نیاز دارم.',
    'ps-AF': 'سلام د آسان‌ټېک د ملاتړ ټیم، زه د سیسټم لپاره مرستې ته اړتیا لرم.',
  }[currentLocale()] ?? 'Hello Asantech Support, I need help with the system.'

  return `https://wa.me/93745625625?text=${encodeURIComponent(message)}`
})
const mapsHref = computed(() => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(supportAddress.value)}`)

const contactChannels = computed(() => [
  { key: 'phone', icon: 'tabler-phone', color: 'primary', value: SUPPORT_CONTACT.phone, href: SUPPORT_CONTACT.phoneHref, external: false },
  { key: 'whatsapp', icon: 'tabler-brand-whatsapp', color: 'success', value: SUPPORT_CONTACT.whatsapp, href: whatsappHref.value, external: true },
  { key: 'email', icon: 'tabler-mail', color: 'warning', value: SUPPORT_CONTACT.email, href: SUPPORT_CONTACT.emailHref, external: false },
])

const categoryOptions = computed(() => ['technical', 'account', 'billing', 'feature_request', 'other'].map(value => ({
  value,
  title: t(`pages.support.categories.${value}`),
})))

const severityOptions = computed(() => ['low', 'normal', 'high', 'critical'].map(value => ({
  value,
  title: t(`pages.support.severities.${value}`),
})))

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

function addAttachments(files: File[] | File | null) {
  const selected = Array.isArray(files) ? files : files ? [files] : []
  const available = Math.max(0, 3 - attachments.value.length)

  if (selected.length > available)
    toast.error(t('pages.support.messages.attachmentLimit'))

  attachments.value.push(...selected.slice(0, available))
  attachmentPicker.value = []
}

function removeAttachment(index: number) {
  if (window.confirm(t('pages.support.removeAttachmentConfirm')))
    attachments.value.splice(index, 1)
}

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
  if (submitting.value) return
  clearErrors()
  const result = await form.value?.validate()
  if (result && !result.valid) {
    await captureError({ errors: Object.fromEntries(result.errors.map((entry: { id: string; errorMessages: string[] }) => [entry.id, entry.errorMessages])) }, t('pages.support.messages.sendFailed'))
    return
  }

  submitting.value = true
  try {
    const ticket = await createSupportTicket(ticketForm.value, attachments.value, { quiet: true })
    tickets.value.unshift(ticket)
    ticketForm.value = blankForm()
    attachments.value = []
    attachmentPicker.value = []
    form.value?.resetValidation()
    toast.success(t('pages.support.messages.sent', { ticket: ticket.ticket_number }))
  }
  catch (error) {
    await captureError(error, t('pages.support.messages.sendFailed'))
  }
  finally {
    submitting.value = false
  }
}

onMounted(loadTickets)
</script>

<template>
  <VRow>
    <VCol v-if="!props.embedded" cols="12">
      <div data-page-title>
        <h4 class="text-h4 mb-1">
          {{ $t('pages.support.title') }}
        </h4>
        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ $t('pages.support.intro') }}
        </p>
      </div>
    </VCol>

    <VCol cols="12">
      <VCard class="support-contact-card">
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-headset" />
            </VAvatar>
          </template>
          <VCardTitle>{{ $t('pages.support.contact.title') }}</VCardTitle>
          <VCardSubtitle>{{ $t('pages.support.contact.subtitle') }}</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <div class="support-contact-grid">
            <a
              v-for="channel in contactChannels"
              :key="channel.key"
              class="support-contact-tile"
              :href="channel.href"
              :target="channel.external ? '_blank' : undefined"
              :rel="channel.external ? 'noopener noreferrer' : undefined"
            >
              <VAvatar
                :color="channel.color"
                variant="tonal"
                size="40"
              >
                <VIcon :icon="channel.icon" />
              </VAvatar>
              <div class="support-contact-tile__body">
                <span class="support-contact-tile__label">{{ $t(`pages.support.contact.${channel.key}`) }}</span>
                <span
                  class="support-contact-tile__value"
                  dir="ltr"
                >{{ channel.value }}</span>
              </div>
              <VIcon
                v-if="channel.external"
                icon="tabler-external-link"
                size="18"
                class="support-contact-tile__arrow"
              />
            </a>
          </div>
          <a
            class="support-contact-address"
            :href="mapsHref"
            target="_blank"
            rel="noopener noreferrer"
          >
            <VAvatar
              color="secondary"
              variant="tonal"
              size="40"
            >
              <VIcon icon="tabler-map-pin" />
            </VAvatar>
            <div class="support-contact-tile__body">
              <span class="support-contact-tile__label">{{ $t('pages.support.contact.address') }}</span>
              <span class="support-contact-tile__value">{{ supportAddress }}</span>
            </div>
            <VIcon icon="tabler-external-link" size="18" class="support-contact-tile__arrow" />
          </a>
        </VCardText>
      </VCard>
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
                <ErpField
                  v-model="ticketForm.category"
                  kind="select"
                  v-bind="inputProps('category')"
                  name="category"
                  :label="$t('pages.support.category')"
                  :placeholder="$t('pages.support.category')"
                  :items="categoryOptions"
                  :rules="[requiredField('category')]"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <ErpField
                  v-model="ticketForm.severity"
                  kind="select"
                  v-bind="inputProps('severity')"
                  name="severity"
                  :label="$t('pages.support.severity')"
                  :placeholder="$t('pages.support.severity')"
                  :items="severityOptions"
                  :rules="[requiredField('severity')]"
                />
              </VCol>
              <VCol cols="12">
                <ErpField
                  v-model="ticketForm.subject"
                  v-bind="inputProps('subject')"
                  name="subject"
                  :label="$t('pages.support.subject')"
                  :placeholder="$t('pages.support.subjectPlaceholder')"
                  :rules="[requiredField('subject')]"
                />
              </VCol>
              <VCol cols="12">
                <ErpField
                  v-model="ticketForm.description"
                  kind="textarea"
                  v-bind="inputProps('description')"
                  name="description"
                  :label="$t('pages.support.descriptionOptional')"
                  :placeholder="$t('pages.support.descriptionPlaceholder')"
                  rows="6"
                  counter="5000"
                />
              </VCol>
              <VCol cols="12">
                <VFileInput
                  v-model="attachmentPicker"
                  v-bind="inputProps('attachments')"
                  :label="$t('pages.support.attachments')"
                  multiple
                  :placeholder="$t('pages.support.attachmentsHint')"
                  hide-details="auto"
                  accept="image/png,image/jpeg,image/webp,application/pdf,text/plain,text/csv,.doc,.docx,.xls,.xlsx"
                  prepend-icon="tabler-paperclip"
                  @update:model-value="addAttachments"
                />
                <div v-if="attachments.length" class="support-attachment-list mt-3">
                  <div v-for="(attachment, index) in attachments" :key="`${attachment.name}-${attachment.lastModified}-${index}`" class="support-attachment-item">
                    <VIcon icon="tabler-paperclip" size="18" />
                    <span class="support-attachment-item__name">{{ attachment.name }}</span>
                    <span class="text-caption text-medium-emphasis">{{ formatFileSize(attachment.size) }}</span>
                    <VBtn icon variant="text" color="error" size="x-small" :aria-label="$t('pages.support.removeAttachment')" @click="removeAttachment(index)">
                      <VIcon icon="tabler-trash" size="18" />
                    </VBtn>
                  </div>
                </div>
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
        <VCardText v-if="tickets.length" class="support-history-list">
          <article v-for="ticket in tickets" :key="ticket.id" class="support-history-item">
            <div class="d-flex align-start justify-space-between gap-3">
              <div>
              <div class="d-flex align-center flex-wrap gap-2 font-weight-medium">
                <span>{{ ticket.subject }}</span>
                <VChip :color="severityColor(ticket.severity)" size="x-small" label>
                  {{ severityLabel(ticket.severity) }}
                </VChip>
              </div>
              <p class="support-ticket-summary mb-0 mt-1">
                {{ ticket.ticket_number }} · {{ $t(`pages.support.categories.${ticket.category}`) }} · {{ $t('pages.support.open') }}<br>
                {{ formatDate(ticket.submitted_at) }}
              </p>
              </div>
              <VIcon icon="tabler-message-circle" size="20" class="text-medium-emphasis" />
            </div>
            <p v-if="ticket.description" class="support-history-item__description mb-0 mt-3">{{ ticket.description }}</p>
              <div v-if="ticket.attachments.length" class="d-flex flex-wrap ga-2 mt-3">
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
          </article>
        </VCardText>
        <VCardText v-else-if="!loading" class="support-history-empty">
          <VAvatar color="primary" variant="tonal" size="48"><VIcon icon="tabler-message-circle-2" /></VAvatar>
          <div>
            <div class="font-weight-medium">{{ $t('pages.support.emptyTitle') }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('pages.support.empty') }}</div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.support-ticket-summary { white-space: normal; }

.support-contact-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.support-contact-tile,
.support-contact-address {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.support-contact-tile:hover,
.support-contact-address:hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
  border-color: rgba(var(--v-theme-primary), 0.35);
}

.support-contact-tile__body {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.support-contact-tile__label {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.support-contact-tile__value {
  font-weight: 600;
  overflow-wrap: anywhere;
}

.support-contact-tile__arrow {
  margin-inline-start: auto;
  opacity: 0.4;
  flex: 0 0 auto;
}

.support-contact-address {
  margin-block-start: 12px;
}

.support-attachment-list,
.support-history-list {
  display: grid;
  gap: 8px;
}

.support-attachment-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.support-attachment-item__name {
  overflow: hidden;
  flex: 1;
  font-size: 0.875rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.support-history-item {
  padding: 14px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
}

.support-history-item__description {
  display: -webkit-box;
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
}

.support-history-empty {
  display: flex;
  align-items: center;
  gap: 12px;
  min-block-size: 132px;
}
</style>
