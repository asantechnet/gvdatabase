declare module '*.svg?url' {
  const src: string
  export default src
}

declare module 'frappe-gantt'
declare module 'quill'
declare module 'vue-shepherd'
declare module 'vue-prism-component' {
  import type { ComponentOptions } from 'vue'

  const component: ComponentOptions
  export default component
}
