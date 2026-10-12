<script setup lang="ts">
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import { type CreateSupportTicketData, type SupportTicket, createSupportTicket, getSupportTickets } from '@/api/supportTicket'
import { currentLocale } from '@/utils/localization'
import { SUPPORT_CONTACT } from '@/utils/constants'

// Rendered both as a standalone route and as a tab inside Settings.
const props = withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const toast = useToast()
const { t } = useI18n()
const form = ref()
const submitting = ref(false)
const loading = ref(true)
const tickets = ref<SupportTicket[]>([])
const attachments = ref<File[]>([])

const contactChannels = computed(() => [
  { key: 'phone', icon: 'tabler-phone', color: 'primary', value: SUPPORT_CONTACT.phone, href: SUPPORT_CONTACT.phoneHref, external: false },
  { key: 'whatsapp', icon: 'tabler-brand-whatsapp', color: 'success', value: SUPPORT_CONTACT.whatsapp, href: SUPPORT_CONTACT.whatsappHref, external: true },
  { key: 'telegram', icon: 'tabler-brand-telegram', color: 'info', value: SUPPORT_CONTACT.telegram, href: SUPPORT_CONTACT.telegramHref, external: true },
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
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ $t('pages.support.contact.developerNotice') }}
          </VAlert>
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
          <div class="support-contact-address">
            <VAvatar
              color="secondary"
              variant="tonal"
              size="40"
            >
              <VIcon icon="tabler-map-pin" />
            </VAvatar>
            <div class="support-contact-tile__body">
              <span class="support-contact-tile__label">{{ $t('pages.support.contact.address') }}</span>
              <span class="support-contact-tile__value">{{ SUPPORT_CONTACT.address }}</span>
            </div>
          </div>
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

.support-contact-tile:hover {
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
</style>
