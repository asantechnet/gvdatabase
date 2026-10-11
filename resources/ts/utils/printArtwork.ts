/**
 * Artwork availability for printed documents.
 *
 * A configured letterhead or cover design is only usable when its file can
 * actually be loaded. A stored path can outlive its file: a re-seeded database,
 * a wiped `storage/app/public`, or a missing `php artisan storage:link` all
 * leave the API publishing a URL that resolves to a broken image.
 *
 * A broken image is not a cosmetic problem in print: the cover page is an
 * `<img>` that then prints as a blank A4 sheet, and `contract_page_template`
 * hides its built-in header/footer whenever a letterhead URL is present, so a
 * dead URL removes the default letterhead too. Every print path therefore
 * resolves availability *before* it renders (and clones) its print body, and
 * falls back to the built-in cover and letterhead chrome.
 */

/** Artwork that is still loading when the print dialog opens is a failure to be reported. */
const LOAD_TIMEOUT_MS = 8000

/** True only when `url` points at an image the browser can actually decode. */
export const canLoadImage = (url?: string | null): Promise<boolean> => {
  if (!url)
    return Promise.resolve(false)

  return new Promise<boolean>(resolve => {
    const image = new Image()
    let settled = false
    let timer: number | undefined

    const finish = (loaded: boolean) => {
      if (settled)
        return

      settled = true
      window.clearTimeout(timer)
      image.onload = null
      image.onerror = null
      resolve(loaded)
    }

    timer = window.setTimeout(() => finish(false), LOAD_TIMEOUT_MS)
    image.onload = () => finish(image.naturalWidth > 0)
    image.onerror = () => finish(false)
    image.src = url
  })
}

export interface ContractArtwork {
  cover?: string | null
  letterhead?: string | null
}

export interface ContractArtworkAvailability {
  cover: boolean
  letterhead: boolean
}

/** Resolves which of the configured contract artwork files are actually printable. */
export const resolveContractArtwork = async (artwork: ContractArtwork): Promise<ContractArtworkAvailability> => {
  const [cover, letterhead] = await Promise.all([
    canLoadImage(artwork.cover),
    canLoadImage(artwork.letterhead),
  ])

  return { cover, letterhead }
}
