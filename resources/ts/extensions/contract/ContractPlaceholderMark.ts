import { Mark, mergeAttributes } from '@tiptap/core'

/**
 * First-class placeholder node for contract templates.
 *
 * Placeholders are inserted as dedicated inline marks showing a friendly
 * "[ Employee Full Name ]" chip instead of raw {{ machine_key }} text.
 * The stable machine key is preserved in a data attribute and serialized
 * on save (see utils/contractPlaceholders.ts).
 */
export const ContractPlaceholderMark = Mark.create({
  name: 'contractPlaceholder',
  inclusive: false,

  addAttributes() {
    return {
      key: {
        default: null,
        parseHTML: element => element.getAttribute('data-placeholder-key'),
        renderHTML: attributes => ({ 'data-placeholder-key': attributes.key }),
      },
      label: {
        default: null,
        parseHTML: element => element.getAttribute('data-placeholder-label'),
        renderHTML: attributes => ({ 'data-placeholder-label': attributes.label }),
      },
    }
  },

  parseHTML() {
    return [{ tag: 'span[data-placeholder-key]' }]
  },

  renderHTML({ HTMLAttributes }) {
    const label = HTMLAttributes['data-placeholder-label'] || HTMLAttributes['data-placeholder-key']

    return ['span', mergeAttributes(HTMLAttributes, { class: 'contract-placeholder', dir: 'auto' }), `[ ${label} ]`]
  },
})

export interface InsertContractPlaceholderOptions {
  key: string
  label: string
}
