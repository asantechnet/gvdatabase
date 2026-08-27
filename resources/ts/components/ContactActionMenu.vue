<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  kind: 'phone' | 'email'
  value: string | null | undefined
}>()

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

  if (!phoneDigits.value) return []

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
    open-on-hover
    location="top"
    transition="scale-transition"
  >
    <template #activator="{ props: activatorProps }">
      <span
        v-bind="activatorProps"
        class="contact-action-menu__trigger text-primary"
        role="button"
        tabindex="0"
        :aria-label="`Contact ${contactValue}`"
      >
        {{ contactValue }}
      </span>
    </template>

    <VList density="compact" min-width="150">
      <VListItem
        v-for="action in actions"
        :key="action.label"
        :href="action.href"
        :target="action.external ? '_blank' : undefined"
        :rel="action.external ? 'noopener noreferrer' : undefined"
        link
        @click.stop
      >
        <template #prepend>
          <VIcon :icon="action.icon" size="18" />
        </template>
        <VListItemTitle>{{ action.label }}</VListItemTitle>
      </VListItem>
    </VList>
  </VMenu>
</template>

<style scoped>
.contact-action-menu__trigger {
  cursor: pointer;
  text-decoration: underline;
  text-decoration-style: dotted;
  text-underline-offset: 3px;
}
</style>
