<script lang="ts" setup>
import { clearFieldError, mergeFieldErrorMessages } from '@/utils/formErrors'

defineOptions({
  name: 'AppAutocomplete',
  inheritAttrs: false,
})

// const { class: _class, label, variant: _, ...restAttrs } = useAttrs()

const elementId = computed (() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id
  const _id = useId()

  return _elementIdToken ? `app-autocomplete-${_elementIdToken}` : _id
})

const label = computed(() => useAttrs().label as string | undefined)
const attrs = useAttrs()
const fieldName = computed(() => (attrs.name || attrs.id) as string | undefined)
const externalErrors = computed(() => mergeFieldErrorMessages(fieldName.value, attrs.errorMessages))

watch(() => attrs.modelValue, () => clearFieldError(fieldName.value))
</script>

<template>
  <div
    class="app-autocomplete flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2"
      :text="label"
    />
    <VAutocomplete
      v-bind="{
        ...$attrs,
        class: null,
        label: undefined,
        id: elementId,
        errorMessages: externalErrors,
        variant: 'outlined',
        menuProps: {
          contentClass: [
            'app-inner-list',
            'app-autocomplete__content',
            'v-autocomplete__content',
          ],
        },
      }"
    >
      <template
        v-for="(_, name) in $slots"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </VAutocomplete>
  </div>
</template>
