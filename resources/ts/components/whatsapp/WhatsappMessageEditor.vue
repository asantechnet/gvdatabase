<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import type { WhatsappPlaceholderField } from '@/api/whatsappTemplates'
import ErpField from '@/components/erp/ErpField.vue'
import {
  WHATSAPP_FORMAT_TOKENS,
  WHATSAPP_MESSAGE_LIMIT,
  renderWhatsappMessage,
  toggleWhatsappWrapping,
  whatsappPreviewHtml,
  whatsappSampleRecord,
} from '@/utils/whatsappTemplates'

/**
 * The WhatsApp message editor.
 *
 * WhatsApp renders no markup: a message is plain text plus line breaks, links,
 * emoji and four inline markers. So this editor is deliberately not a rich-text
 * editor — it is a plain textarea with a toolbar that wraps the selection in
 * those markers, a placeholder palette, and a preview that renders exactly what
 * WhatsApp would. Pasting is flattened to plain text, which is the one way HTML
 * could otherwise sneak into a message that cannot display it.
 */
const props = withDefaults(defineProps<{
  modelValue: string
  /** The placeholders the message's audience declares. */
  fields?: WhatsappPlaceholderField[]
  disabled?: boolean
  label?: string
  errorMessages?: string | string[]
}>(), { fields: () => [], errorMessages: undefined })

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const { t } = useI18n()

// Vuetify's VTextarea uses a function ref internally, so the raw control is not
// on `$refs`: it is reached through the field's own root element. Auto-grow adds
// a second, readonly measuring textarea, which is excluded here.
interface TextareaField { $el?: Element }

const textareaField = ref<TextareaField | null>(null)
const placeholderMenu = ref(false)
const placeholderSearch = ref('')
const previewWithSamples = ref(true)

const message = computed({
  get: () => props.modelValue,
  set: (value: string) => emit('update:modelValue', value),
})

const formatTokens = computed(() => WHATSAPP_FORMAT_TOKENS.map(token => ({
  ...token,
  label: t(token.labelKey),
})))

const filteredFields = computed(() => {
  const query = placeholderSearch.value.trim().toLowerCase()

  return query
    ? props.fields.filter(field => field.token.toLowerCase().includes(query) || field.label.toLowerCase().includes(query))
    : props.fields
})

const overLimit = computed(() => props.modelValue.length > WHATSAPP_MESSAGE_LIMIT)

// The preview shows the message twice over: as written (tokens visible, so the
// author can see they are inserted correctly) and filled with the audience's
// sample values (so the author can read it the way the contact will).
const previewMessage = computed(() => (previewWithSamples.value
  ? renderWhatsappMessage(props.modelValue, props.fields, whatsappSampleRecord(props.fields))
  : props.modelValue))

const previewHtml = computed(() => whatsappPreviewHtml(previewMessage.value))

const textarea = (): HTMLTextAreaElement | null =>
  (textareaField.value?.$el?.querySelector('textarea:not(.v-textarea__sizer)') as HTMLTextAreaElement | null) ?? null

// Clicking a toolbar button can move focus off the message and collapse the
// selection before the handler runs, so the last selection the control reported
// is remembered and used whenever the live one is gone.
const lastSelection = ref({ start: 0, end: 0 })
const selectionCleanups: Array<() => void> = []

const rememberSelection = () => {
  const element = textarea()
  if (!element)
    return
  lastSelection.value = { start: element.selectionStart ?? 0, end: element.selectionEnd ?? 0 }
}

onMounted(() => {
  const element = textarea()
  if (!element)
    return
  for (const event of ['select', 'keyup', 'mouseup', 'input', 'blur']) {
    element.addEventListener(event, rememberSelection)
    selectionCleanups.push(() => element.removeEventListener(event, rememberSelection))
  }
})

onBeforeUnmount(() => selectionCleanups.forEach(remove => remove()))

/** The selection to act on: the live one when the control still owns it. */
const currentSelection = () => {
  const element = textarea()

  if (!element)
    return { start: props.modelValue.length, end: props.modelValue.length }
  if (document.activeElement === element || element.selectionStart !== element.selectionEnd)
    return { start: element.selectionStart ?? 0, end: element.selectionEnd ?? 0 }

  return lastSelection.value
}

const restoreSelection = (start: number, end: number) => {
  void nextTick(() => {
    const element = textarea()
    if (!element)
      return
    element.focus()
    element.setSelectionRange(start, end)
  })
}

/** Wrap (or unwrap) the current selection in one WhatsApp marker. */
const applyFormat = (marker: string) => {
  const element = textarea()
  if (props.disabled)
    return

  if (!element) {
    emit('update:modelValue', `${props.modelValue}${marker}${marker}`)

    return
  }

  const { start: selectionStart, end: selectionEnd } = currentSelection()
  const { value, start, end } = toggleWhatsappWrapping(element.value, selectionStart, selectionEnd, marker)

  emit('update:modelValue', value)
  restoreSelection(start, end)
}

/** Insert a `{{token}}` at the caret, replacing any selection. */
const insertPlaceholder = (token: string) => {
  const element = textarea()
  const snippet = `{{${token}}}`

  if (!element) {
    emit('update:modelValue', `${props.modelValue}${snippet}`)
    placeholderMenu.value = false

    return
  }

  const { start, end } = currentSelection()

  emit('update:modelValue', `${element.value.slice(0, start)}${snippet}${element.value.slice(end)}`)
  placeholderMenu.value = false
  placeholderSearch.value = ''
  restoreSelection(start + snippet.length, start + snippet.length)
}

// Keep pasted content text-only: `text/plain` is the entire WhatsApp vocabulary.
const onPaste = (event: ClipboardEvent) => {
  const text = event.clipboardData?.getData('text/plain')
  if (text === undefined || text === null)
    return

  event.preventDefault()
  const element = textarea()
  if (!element) {
    emit('update:modelValue', `${props.modelValue}${text}`)

    return
  }

  const start = element.selectionStart ?? element.value.length
  const end = element.selectionEnd ?? element.value.length

  emit('update:modelValue', `${element.value.slice(0, start)}${text}${element.value.slice(end)}`)
  restoreSelection(start + text.length, start + text.length)
}

const openPlaceholderMenu = () => {
  placeholderSearch.value = ''
}
</script>

<template>
  <div class="whatsapp-editor">
    <!-- `mousedown.prevent` keeps the caret (and any selection) in the message
         while the toolbar is used, so a marker always lands on what the author
         had selected. -->
    <div
      class="whatsapp-editor__toolbar"
      @mousedown.prevent
    >
      <div class="whatsapp-editor__format-actions">
        <VBtn
          v-for="token in formatTokens"
          :key="token.key"
          class="whatsapp-editor__format-action"
          :disabled="disabled"
          size="small"
          density="compact"
          variant="tonal"
          color="primary"
          :aria-label="token.label"
          @click="applyFormat(token.marker)"
        >
          <VIcon :icon="token.icon" />
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ token.label }}
          </VTooltip>
        </VBtn>
      </div>

      <VMenu
        v-model="placeholderMenu"
        :close-on-content-click="false"
        location="bottom start"
        max-width="420"
        @update:model-value="openPlaceholderMenu"
      >
        <template #activator="{ props: activatorProps }">
          <VBtn
            v-bind="activatorProps"
            class="whatsapp-editor__placeholder-btn"
            variant="tonal"
            density="compact"
            color="secondary"
            size="small"
            :disabled="disabled || fields.length === 0"
            :prepend-icon="'tabler-braces'"
          >
            {{ t('pages.whatsapp.editor.insertPlaceholder') }}
          </VBtn>
        </template>

        <VCard class="whatsapp-editor__placeholder-menu">
          <VCardText class="pb-0">
            <VTextField
              v-model="placeholderSearch"
              density="compact"
              hide-details
              clearable
              autofocus
              :placeholder="t('pages.whatsapp.editor.searchPlaceholders')"
              prepend-inner-icon="tabler-search"
            />
          </VCardText>
          <VList
            density="compact"
            max-height="280"
            class="overflow-y-auto"
          >
            <VListSubheader>{{ t('pages.whatsapp.editor.placeholders') }}</VListSubheader>
            <VListItem
              v-for="field in filteredFields"
              :key="field.token"
              :title="field.label"
              :subtitle="`{{${field.token}}}`"
              @click="insertPlaceholder(field.token)"
            />
            <VListItem
              v-if="!filteredFields.length"
              :title="t('common.messages.noMatchingRecords')"
            />
          </VList>
        </VCard>
      </VMenu>

      <VChip
        size="small"
        variant="tonal"
        :color="overLimit ? 'error' : 'default'"
      >
        {{ t('pages.whatsapp.editor.characters', { count: modelValue.length, limit: WHATSAPP_MESSAGE_LIMIT }) }}
      </VChip>
    </div>

    <ErpField
      ref="textareaField"
      v-model="message"
      kind="textarea"
      class="whatsapp-editor__input"
      rows="5"
      auto-grow
      :disabled="disabled"
      :label="label ?? t('pages.whatsapp.editor.message')"
      :error-messages="errorMessages"
      @paste="onPaste"
    />

    <VAlert
      v-if="overLimit"
      type="error"
      variant="tonal"
      density="compact"
      class="mt-4"
    >
      {{ t('pages.whatsapp.editor.tooLong', { limit: WHATSAPP_MESSAGE_LIMIT }) }}
    </VAlert>

    <div class="whatsapp-editor__preview">
      <div class="whatsapp-editor__preview-header">
        <span class="text-subtitle-2">{{ t('pages.whatsapp.editor.preview') }}</span>
        <VBtnToggle
          v-model="previewWithSamples"
          density="compact"
          variant="text"
          color="primary"
          mandatory
          divided
        >
          <VBtn
            :value="true"
            size="small"
            class="whatsapp-editor__preview-toggle"
          >
            {{ t('pages.whatsapp.editor.previewSample') }}
          </VBtn>
          <VBtn
            :value="false"
            size="small"
            class="whatsapp-editor__preview-toggle"
          >
            {{ t('pages.whatsapp.editor.previewTokens') }}
          </VBtn>
        </VBtnToggle>
      </div>
      <div
        class="whatsapp-editor__bubble"
        dir="auto"
      >
        <div
          v-if="previewMessage.trim()"
          class="whatsapp-editor__bubble-text"
          v-html="previewHtml"
        />
        <span
          v-else
          class="text-caption text-disabled"
        >{{ t('pages.whatsapp.editor.previewEmpty') }}</span>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.whatsapp-editor {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  background: rgba(var(--v-theme-on-surface), 0.025);

  &__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }

  &__format-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
  }

  &__format-action,
  &__placeholder-btn {
    min-block-size: 2.25rem;
    padding-block: 0.375rem;
  }

  &__toolbar > :last-child {
    margin-inline-start: auto;
  }

  /* The placeholder palette keeps a readable measure instead of stretching
     across the whole toolbar on wide screens. */
  &__placeholder-btn {
    text-transform: none;
  }

  &__placeholder-menu {
    inline-size: min(420px, calc(100vw - 32px));
  }

  &__input :deep(textarea) {
    line-height: 1.6;
    /* WhatsApp collapses runs of spaces; showing them keeps the preview honest. */
    white-space: pre-wrap;
  }

  &__preview-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-block-end: 0.375rem;
  }

  &__preview {
    margin-block-start: 0.5rem;
  }

  /* Vuetify stretches each toggle to the widest label; opt out so both options
     size to their own text and keep identical padding. */
  &__preview-toggle {
    flex: 0 0 auto;
    min-inline-size: max-content;
    padding-inline: 0.625rem;
    text-transform: none;
    white-space: nowrap;
  }

  /* A chat bubble in the app's own palette: green tonal surface, rounded on the
     receiving side, wrapped in the current theme's border. */
  &__bubble {
    padding-block: 0.625rem;
    padding-inline: 0.875rem;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 0.75rem;
    border-start-start-radius: 0.25rem;
    background: rgba(var(--v-theme-success), 0.12);
    color: rgb(var(--v-theme-on-surface));
  }

  &__bubble-text {
    font-size: 0.9375rem;
    line-height: 1.6;
    overflow-wrap: anywhere;
    /* WhatsApp preserves the author's line breaks exactly. */
    white-space: normal;
  }

  &__bubble-text :deep(code) {
    padding-block: 0.1rem;
    padding-inline: 0.3rem;
    border-radius: 0.25rem;
    background: rgba(var(--v-theme-on-surface), 0.08);
    font-family: monospace;
  }
}

@media (max-width: 599px) {
  .whatsapp-editor {
    &__toolbar > :last-child {
      margin-inline-start: 0;
    }

    &__preview-toggle {
      padding-inline: 0.5rem;
    }
  }
}
</style>
