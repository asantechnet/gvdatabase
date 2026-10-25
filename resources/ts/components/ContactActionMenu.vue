<script setup lang="ts">
import { translate } from '@/utils/localization'
import { computed, onMounted, ref } from 'vue'
import CrmApi from '@/api/crmRelationships'
import { useToast } from 'vue-toastification'
import { apiErrorMessage } from '@/utils/apiErrors'
import type { WhatsappAudience, WhatsappPlaceholderField, WhatsappTemplate } from '@/api/whatsappTemplates'
import { activeWhatsappTemplates, whatsappPlaceholderFields } from '@/composables/useWhatsappTemplates'
import { buildWhatsappUrl, renderWhatsappMessage, whatsappPhoneDigits } from '@/utils/whatsappTemplates'

const props = defineProps<{
  kind: 'phone' | 'email'
  value: string | null | undefined
  contactableType?: 'visitor' | 'applicant'
  contactableId?: number
  /** Open the menu automatically, e.g. when deep-linked from another page. */
  autoOpen?: boolean
  /**
   * The record type the row shows. When set, the phone menu also offers the
   * active WhatsApp templates for that audience — and only those, so a visitor
   * row never offers an applicant's message.
   */
  audience?: WhatsappAudience
  /** The row itself: the template placeholders are filled from it. */
  context?: unknown
}>()

const toast = useToast()
const { t } = useI18n()
const menuOpen = ref(false)
const dialog = ref(false)

onMounted(() => {
  if (props.autoOpen)
    menuOpen.value = true
  void loadWhatsappTemplates()
})
const pendingAction = ref<ContactAction | null>(null)
const saving = ref(false)
const form = ref({ title: '', notes: '', follow_up_at: null as string | null })
const whatsappTemplates = ref<WhatsappTemplate[]>([])
const placeholderFields = ref<WhatsappPlaceholderField[]>([])

/** The audience's active templates and the fields that fill them, loaded once. */
const loadWhatsappTemplates = async () => {
  if (props.kind !== 'phone' || !props.audience)
    return

  const [templates, fields] = await Promise.all([
    activeWhatsappTemplates(props.audience),
    whatsappPlaceholderFields(props.audience),
  ])

  whatsappTemplates.value = templates
  placeholderFields.value = fields
}

/**
 * Send a template: fill its placeholders from this row, open WhatsApp with the
 * message ready, then offer the usual CRM dialog so the contact is recorded.
 * Employees have no conversation record, so there the link alone is opened.
 */
const sendWhatsappTemplate = (template: WhatsappTemplate, event: MouseEvent | KeyboardEvent) => {
  event.preventDefault()
  const message = renderWhatsappMessage(template.message, placeholderFields.value, props.context ?? null)

  window.open(buildWhatsappUrl(contactValue.value, message), '_blank', 'noopener,noreferrer')

  if (!props.contactableType || !props.contactableId)
    return

  pendingAction.value = { label: template.title, icon: 'tabler-brand-whatsapp', href: '' }
  form.value = { title: `${template.title} conversation`, notes: '', follow_up_at: null }
  dialog.value = true
}

const offsetFollowUpCalendar = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
  })
}

const begin = (action: ContactAction, event: MouseEvent | KeyboardEvent) => {
  if (!props.contactableType || !props.contactableId)
    return
  event.preventDefault()
  pendingAction.value = action
  form.value = { title: `${action.label} conversation`, notes: '', follow_up_at: null }
  dialog.value = true

  // Launch the user's communication app from the original click so browser
  // popup protection and mobile deep-link handling continue to work. The CRM
  // dialog remains in this app for recording the outcome after the contact.
  if (action.external)
    window.open(action.href, '_blank', 'noopener,noreferrer')
  else window.location.href = action.href
}

const saveConversation = async () => {
  if (!pendingAction.value || !props.contactableType || !props.contactableId || !form.value.title.trim() || !form.value.notes.trim())
    return
  saving.value = true
  try {
    await CrmApi.createInteraction(props.contactableType, props.contactableId, {
      channel: props.kind === 'email' ? 'email' : pendingAction.value.label === translate('common.ui.call') ? 'call' : 'message',
      title: form.value.title.trim(),
      notes: form.value.notes.trim(),
      follow_up_at: form.value.follow_up_at,
    })
    dialog.value = false
    pendingAction.value = null
  }
  catch (error) { toast.error(apiErrorMessage(error, t('common.crmDetails.conversationSaveFailed'))) }
  finally { saving.value = false }
}

interface ContactAction {
  label: string
  icon: string
  href: string
  external?: boolean
}

const contactValue = computed(() => props.value?.trim() ?? '')
const phoneValue = computed(() => contactValue.value.replace(/[^\d+]/g, ''))
const phoneDigits = computed(() => whatsappPhoneDigits(contactValue.value))

const actions = computed<ContactAction[]>(() => {
  if (props.kind === 'email') {
    return contactValue.value
      ? [{ label: translate('serviceContracts.email'), icon: 'tabler-mail', href: `mailto:${encodeURIComponent(contactValue.value)}` }]
      : []
  }

  if (!phoneDigits.value)
    return []

  return [
    { label: 'WhatsApp', icon: 'tabler-brand-whatsapp', href: `https://wa.me/${phoneDigits.value}`, external: true },
    { label: 'Telegram', icon: 'tabler-brand-telegram', href: `https://t.me/+${phoneDigits.value}`, external: true },
    { label: translate('common.ui.call'), icon: 'tabler-phone', href: `tel:${encodeURIComponent(phoneValue.value)}` },
  ]
})

const copyEmail = async (event: MouseEvent | KeyboardEvent) => {
  event.preventDefault()

  if (!contactValue.value)
    return

  try {
    if (navigator.clipboard?.writeText)
      await navigator.clipboard.writeText(contactValue.value)
    else {
      const textarea = document.createElement('textarea')

      textarea.value = contactValue.value
      textarea.setAttribute('readonly', '')
      textarea.style.inset = '0 auto auto -9999px'
      textarea.style.position = 'fixed'
      document.body.append(textarea)
      textarea.select()

      const copied = document.execCommand('copy')

      textarea.remove()
      if (!copied)
        throw new Error('Clipboard copy failed')
    }

    menuOpen.value = false
    toast.success(t('common.crmUi.emailCopied'))
  }
  catch {
    toast.error(t('common.crmUi.emailCopyFailed'))
  }
}
</script>

<template>
  <VMenu
    v-if="contactValue"
    v-model="menuOpen"
    location="bottom start"
    transition="scale-transition"
    :open-delay="0"
  >
    <template #activator="{ props: activatorProps }">
      <div
        class="contact-action-menu__boundary"
        @click.stop
        @pointerdown.stop
        @touchstart.stop
      >
        <VBtn
          v-bind="activatorProps"
          class="contact-action-menu__trigger"
          :prepend-icon="kind === 'phone' ? 'tabler-phone' : 'tabler-mail'"
          color="primary"
          variant="text"
          size="small"
          :ripple="false"
          :aria-label="`Communication options for ${contactValue}`"
        >
          <span class="contact-action-menu__value">{{ contactValue }}</span>
          <VIcon
            icon="tabler-chevron-down"
            size="15"
            class="ml-1"
          />
        </VBtn>
      </div>
    </template>

    <VList
      v-if="actions.length"
      density="compact"
      min-width="220"
    >
      <VListItem
        v-for="action in actions"
        :key="action.label"
        :href="action.href"
        :target="action.external ? '_blank' : undefined"
        :rel="action.external ? 'noopener noreferrer' : undefined"
        link
        @click.stop="begin(action, $event)"
      >
        <template #prepend>
          <VIcon
            :icon="action.icon"
            size="18"
          />
        </template>
        <VListItemTitle>{{ action.label }}</VListItemTitle>
      </VListItem>

      <VListItem
        v-if="kind === 'email'"
        prepend-icon="tabler-copy"
        link
        @click.stop="copyEmail"
      >
        <VListItemTitle>{{ $t('common.crmUi.copyEmail') }}</VListItemTitle>
      </VListItem>

      <!-- The audience's active templates, already filled from this row. Only
           the templates of this page's record type are ever offered. -->
      <template v-if="kind === 'phone' && whatsappTemplates.length">
        <VDivider class="my-1" />
        <VListSubheader>{{ $t('pages.whatsapp.quickContactHeading') }}</VListSubheader>
        <VListItem
          v-for="template in whatsappTemplates"
          :key="template.id"
          link
          @click.stop="sendWhatsappTemplate(template, $event)"
        >
          <template #prepend>
            <VIcon
              icon="tabler-brand-whatsapp"
              size="18"
              color="success"
            />
          </template>
          <VListItemTitle>{{ template.title }}</VListItemTitle>
          <VListItemSubtitle v-if="template.labels.length">
            {{ template.labels.join(' · ') }}
          </VListItemSubtitle>
        </VListItem>
      </template>
    </VList>
    <VList
      v-else
      density="compact"
      min-width="220"
    >
      <VListItem
        prepend-icon="tabler-alert-circle"
        :title="$t('common.crmUi.invalidPhone')"
        :subtitle="$t('common.crmUi.invalidPhoneHint')"
      />
    </VList>
  </VMenu>

  <VDialog
    v-model="dialog"
    max-width="560"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-message-circle"
          color="primary"
        />{{ $t('common.crmUi.logConversation') }}
      </VCardTitle>
      <VCardText>
        <AppDateTimePicker
          v-model="form.follow_up_at"
          :label="$t('common.crmUi.followUp')"
          clearable
          class="mb-3"
          :config="{ enableTime: true, dateFormat: 'Y-m-d H:i', static: false, position: 'below', onOpen: offsetFollowUpCalendar }"
        />
        <VTextField
          v-model="form.title"
          :label="$t('common.crmUi.conversationTitle')"
          autofocus
          class="mb-3"
        />
        <VTextarea
          v-model="form.notes"
          :label="$t('common.crmUi.conversationNotes')"
          rows="5"
          :hint="$t('common.crmUi.conversationHint')"
          persistent-hint
          class="mb-3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer /><VBtn
          variant="text"
          @click="dialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn><VBtn
          prepend-icon="tabler-device-floppy"
          :loading="saving"
          :disabled="!form.title.trim() || !form.notes.trim()"
          @click="saveConversation"
        >
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style>
.flatpickr-calendar.open {
  z-index: 9999 !important;
}

</style>

<style scoped>
.contact-action-menu__trigger {
  --v-activated-opacity: 0;
  --v-focus-opacity: 0;
  --v-hover-opacity: 0;

  background: transparent !important;
  box-shadow: none !important;
  min-block-size: 36px;
  max-inline-size: 100%;
  padding-inline: 0;
  text-transform: none;
  letter-spacing: normal;
}

.contact-action-menu__trigger:hover,
.contact-action-menu__trigger:focus-visible {
  background: transparent !important;
}

.contact-action-menu__boundary,
.contact-action-menu__value {
  min-inline-size: 0;
}

.contact-action-menu__value {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

</style>
