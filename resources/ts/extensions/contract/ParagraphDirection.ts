import { Extension } from '@tiptap/core'

/**
 * Paragraph-level text direction (RTL/LTR) for contract documents.
 *
 * Contracts routinely mix Persian (RTL) and English (LTR) paragraphs inside
 * one document, so direction is stored per block as a `dir` attribute
 * instead of being applied globally to the editor. No official TipTap
 * extension ships with the project's pinned version, therefore this small
 * in-project extension provides the attribute; toggling uses
 * editor.chain().focus().updateAttributes('paragraph', { dir }).
 */
export const ParagraphDirection = Extension.create({
  name: 'paragraphDirection',

  addGlobalAttributes() {
    return [
      {
        types: ['paragraph', 'heading'],
        attributes: {
          dir: {
            default: null as string | null,
            parseHTML: element => element.getAttribute('dir'),
            renderHTML: attributes => {
              const dir = (attributes as { dir?: string | null }).dir

              return dir ? { dir } : {}
            },
          },
        },
      },
    ]
  },
})
