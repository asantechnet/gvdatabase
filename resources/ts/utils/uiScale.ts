const KEY = 'educational-ui-scale'
export const uiScaleOptions = [
  { title: 'Small', value: 0.9 }, { title: 'Normal', value: 1 }, { title: 'Large', value: 1.1 }, { title: 'Extra large', value: 1.2 },
]

export function applyUiScale(value: number | null | undefined) {
  const scale = Math.min(1.25, Math.max(0.85, Number(value) || 1))
  document.documentElement.style.setProperty('--app-ui-scale', String(scale))
  document.documentElement.style.fontSize = `calc(16px * ${scale})`
  localStorage.setItem(KEY, String(scale))
}

export function restoreUiScale() {
  applyUiScale(Number(localStorage.getItem(KEY)) || 1)
}
