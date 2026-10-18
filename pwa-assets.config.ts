import type { AssetType, ResolvedAssetSize } from '@vite-pwa/assets-generator/config'
import { defineConfig, minimal2023Preset } from '@vite-pwa/assets-generator/config'

/**
 * Generates the committed PNG icon set from `public/favicon.svg`.
 * Run with `npm run pwa:assets`. Names are fixed because the dynamic manifest
 * (`PwaManifestController`) and the Blade shell link them directly.
 */
const assetName = (type: AssetType, size: ResolvedAssetSize) => {
  if (type === 'apple')
    return 'apple-touch-icon.png'

  if (type === 'maskable')
    return 'maskable-512.png'

  return `icon-${size.width}.png`
}

export default defineConfig({
  preset: {
    ...minimal2023Preset,
    assetName,
  },
  images: ['public/favicon.svg'],
})
