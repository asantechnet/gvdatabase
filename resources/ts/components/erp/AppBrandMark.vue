<script setup lang="ts">
/**
 * Company brand mark, rendered by every layout that shows the logo.
 *
 * The packaged fallback (`resources/images/logo.svg`) is a single-colour vector
 * drawn with `currentColor`. An SVG loaded through `<img>` has no inherited
 * colour, so `currentColor` resolves to black and the mark becomes invisible on
 * a dark header. Rendering the same asset as a CSS mask paints the shape with
 * the surrounding text colour, so one asset stays legible in light and dark
 * theme without inventing a second, unapproved logo.
 *
 * An uploaded logo may be full colour, so it is never masked: it renders as an
 * image exactly as uploaded and keeps the company's own colours.
 */
withDefaults(defineProps<{
  src: string
  alt: string
  /** True only for the packaged single-colour mark. */
  monochrome?: boolean
}>(), { monochrome: false })

defineOptions({ inheritAttrs: false })

defineEmits<{ load: [event: Event] }>()
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
