import type { ExplorerAdapter, ExplorerItem } from '#file-explorer'

/** A document (folder or file) of a place, proxied by the API (never Rocket Cloud directly). */
export interface PlaceDocument {
  id: string // "folder:<id>" or "file:<id>"
  kind: 'folder' | 'file'
  name: string
  size: number | null
  updatedAt: string
}

/** The place is the only space of the explorer (one space per page, no cross-place navigation). */
export const PLACE_SPACE = 'place'

/**
 * Adapter of @rocket/file-explorer on the "Documents" tab of a place: every call goes through our own API
 * (/api/places/{id}/documents…), scoped by the backend to that place's Rocket Cloud folder, so the browser
 * never needs a Rocket Cloud account or token. Read-only for non-admins (the backend also enforces it).
 */
export function usePlaceDocuments(placeId: string): ExplorerAdapter {
  const api = useApi()
  const auth = useAuth()
  const config = useRuntimeConfig()
  const base = `/api/places/${placeId}/documents`
  const item = (d: PlaceDocument): ExplorerItem => ({ id: d.id, kind: d.kind, name: d.name, size: d.size ?? undefined, updatedAt: d.updatedAt, spaceId: PLACE_SPACE })

  return {
    rootLabel: 'Documents',
    async list(location) {
      const space = { id: PLACE_SPACE, name: 'Documents', icon: 'i-lucide-folder' }
      const folder = location.folder ? String(location.folder) : ''
      const { items } = await api<{ items: PlaceDocument[] }>(base, { query: folder ? { folder } : {} })

      return { space, spaces: [space], items: items.map(item) }
    },
    async createFolder(location, name) {
      await api(`${base}/folders`, { method: 'POST', body: { name, folder: location.folder ? String(location.folder) : undefined } })
    },
    async upload(location, files) {
      const errors: string[] = []
      for (const file of files) {
        const body = new FormData()
        body.append('file', file)
        if (location.folder) body.append('folder', String(location.folder))
        try {
          await api(`${base}/upload`, { method: 'POST', body })
        }
        catch (error) {
          errors.push(`${file.name} : ${apiErrorMessage(error)}`)
        }
      }
      return { errors }
    },
    async rename(item, name) {
      await api(`${base}/${item.id}`, { method: 'PATCH', body: { name } })
    },
    async move(items, target) {
      for (const it of items) await api(`${base}/${it.id}`, { method: 'PATCH', body: { folder: target.folder ? String(target.folder) : null } })
    },
    async remove(items) {
      for (const it of items) await api(`${base}/${it.id}`, { method: 'DELETE' })
    },
    async resolveFileUrl(item, download) {
      const blob = await $fetch<Blob>(`${base}/${item.id}/content`, {
        baseURL: config.public.apiBase,
        query: download ? { download: 1 } : {},
        headers: { Authorization: auth.authorizationHeader() ?? '' },
        responseType: 'blob',
      })
      return URL.createObjectURL(blob)
    },
    errorMessage: apiErrorMessage,
  }
}
