<script setup lang="ts">
import { computed } from 'vue'
import api from '@/api/axios'

const props = defineProps<{
  kind: 'phone' | 'email'
  value: string | null | undefined
  contactableType?: 'visitor' | 'lead' | 'customer'
  contactableId?: number
}>()

const dialog = ref(false)
const pendingAction = ref<ContactAction | null>(null)
const saving = ref(false)
const form = ref({ title: '', notes: '', follow_up_at: null as string | null })

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
    await api.post(`/crm/${props.contactableType}/${props.contactableId}/interactions`, {
      channel: props.kind === 'email' ? 'email' : pendingAction.value.label === 'Call' ? 'call' : 'message',
      title: form.value.title.trim(),
      notes: form.value.notes.trim(),
      follow_up_at: form.value.follow_up_at,
    })
    dialog.value = false
    pendingAction.value = null
  }
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
const phoneDigits = computed(() => phoneValue.value.replace(/\D/g, ''))

const actions = computed<ContactAction[]>(() => {
  if (props.kind === 'email') {
    return contactValue.value
      ? [{ label: 'Email', icon: 'tabler-mail', href: `mailto:${encodeURIComponent(contactValue.value)}` }]
      : []
  }

  if (!phoneDigits.value)
    return []

  return [
    { label: 'WhatsApp', icon: 'tabler-brand-whatsapp', href: `https://wa.me/${phoneDigits.value}`, external: true },
    { label: 'Telegram', icon: 'tabler-brand-telegram', href: `https://t.me/+${phoneDigits.value}`, external: true },
    { label: 'Call', icon: 'tabler-phone', href: `tel:${encodeURIComponent(phoneValue.value)}` },
  ]
})
</script>

<template>
  <VMenu
    v-if="contactValue"
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
          variant="tonal"
          size="small"
          rounded="lg"
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
      min-width="150"
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
  min-block-size: 36px;
  max-inline-size: 100%;
  text-transform: none;
  letter-spacing: normal;
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
