<script lang="ts" setup>
import { clearFieldError, mergeFieldErrorMessages } from '@/utils/formErrors'

defineOptions({
  name: 'AppSelect',
  inheritAttrs: false,
})

const elementId = computed (() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id
  const _id = useId()

  return _elementIdToken ? `app-select-${_elementIdToken}` : _id
})

const label = computed(() => useAttrs().label as string | undefined)
const attrs = useAttrs()
const fieldName = computed(() => (attrs.name || attrs.id) as string | undefined)
const externalErrors = computed(() => mergeFieldErrorMessages(fieldName.value, attrs.errorMessages))

watch(() => attrs.modelValue, () => clearFieldError(fieldName.value))
</script>

<template>
  <div
    class="app-select flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2"
      style="line-height: 15px;"
      :text="label"
    />
    <VSelect
      v-bind="{
        ...$attrs,
        class: null,
        label: undefined,
        variant: 'outlined',
        id: elementId,
        errorMessages: externalErrors,
        menuProps: { contentClass: ['app-inner-list', 'app-select__content', 'v-select__content', $attrs.multiple !== undefined ? 'v-list-select-multiple' : ''] },
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
    </VSelect>
  </div>
</template>
