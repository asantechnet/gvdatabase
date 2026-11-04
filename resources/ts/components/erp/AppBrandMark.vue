<script setup lang="ts">
/**
 * Company brand mark, rendered by every layout that shows the logo.
 *
 * Uploaded logos render exactly as supplied. Layouts show the company name
 * when no logo exists or its image cannot be loaded.
 */
withDefaults(defineProps<{
  src: string
  alt: string
  monochrome?: boolean
}>(), { monochrome: false })

defineOptions({ inheritAttrs: false })

defineEmits<{ load: [event: Event], error: [event: Event] }>()
</script>

<template>
  <span
    v-if="monochrome"
    v-bind="$attrs"
    class="app-brand-mark app-brand-mark--masked"
    role="img"
    :aria-label="alt"
    :style="{ '--app-brand-mark-source': `url(${src})` }"
  />
  <img
    v-else
    v-bind="$attrs"
    :src="src"
    :alt="alt"
    class="app-brand-mark"
    @load="$emit('load', $event)"
    @error="$emit('error', $event)"
  >
</template>

<style scoped>
.app-brand-mark {
  display: block;
  flex: 0 0 auto;
  block-size: 2.1rem;
  inline-size: auto;
  max-inline-size: 8rem;
  object-fit: contain;
}

.app-brand-mark--masked {
  aspect-ratio: 34 / 24;
  background-color: currentcolor;
  mask-image: var(--app-brand-mark-source);
  mask-position: center;
  mask-repeat: no-repeat;
  mask-size: contain;
  -webkit-mask-image: var(--app-brand-mark-source);
  -webkit-mask-position: center;
  -webkit-mask-repeat: no-repeat;
  -webkit-mask-size: contain;
}
</style>
