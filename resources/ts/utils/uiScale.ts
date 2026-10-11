import { computed } from 'vue'
import { translate } from '@/utils/localization'

const KEY = 'educational-ui-scale'
export const uiScaleOptions = computed(() => [
  { title: translate('common.ui.small'), value: 0.9 }, { title: translate('pages.support.severities.normal'), value: 1 }, { title: translate('common.ui.large'), value: 1.1 }, { title: translate('common.ui.extraLarge'), value: 1.2 },
])

export function applyUiScale(value: number | null | undefined) {
  const scale = Math.min(1.25, Math.max(0.85, Number(value) || 1))
  document.documentElement.style.setProperty('--app-ui-scale', String(scale))
  document.documentElement.style.fontSize = `calc(16px * ${scale})`
  localStorage.setItem(KEY, String(scale))
}

export function restoreUiScale() {
  applyUiScale(Number(localStorage.getItem(KEY)) || 1)
}
