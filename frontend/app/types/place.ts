export interface Place {
  id: string
  name: string
  address: string | null
  color: string
  latitude: number | null
  longitude: number | null
  cloudFolderId: string | null
}

export interface AccessGrant {
  id: string
  placeId: string
  lockId: number
  label: string
  code: string
  validFrom: string
  validUntil: string
  status: 'planned' | 'created' | 'error' | 'revoked'
  externalRef: string | null
  error: string | null
  sentAt: string | null
  revokedAt: string | null
}

export interface LockLog { date: string, who: string, action: number, trigger: number }

export interface PluginField {
  key: string
  label: string
  type: string
  required?: boolean
  secret?: boolean
  help?: string
  placeholder?: string
  options?: { label: string, value: string }[]
}

export interface Plugin {
  id: string
  name: string
  description: string
  icon: string
  category: string
  fields: PluginField[]
  capabilities: string[]
}

export interface Connector {
  id: string
  placeId: string
  pluginId: string
  name: string
  enabled: boolean
  config: Record<string, string>
  secrets: Record<string, boolean>
  lastRunAt: string | null
  lastResult: string | null
}

export interface DomotiqueSection {
  connectorId: string
  name: string
  pluginId: string
  pluginName: string
  icon: string
  cards: { title: string, icon?: string, items: { label: string, value: string }[] }[]
  error: string | null
}

export interface Lock {
  id: number
  name: string
  state: string
  locked: boolean
  battery: number | null
  batteryCritical: boolean
  keypadBatteryCritical: boolean
  logs: LockLog[]
  placeId: string | null
  place: string | null
  /** Controller providing the live state: 'nuki' (legacy, env token) unless rerouted to a connector (e.g. 'home_assistant', 'homey'). */
  provider: string
}

export interface StockItem {
  id: string
  name: string
  asin: string | null
  reorderQty: number
  subscription: boolean
}

export interface StockLevel {
  id: string
  place: string // IRI
  item: string // IRI
  level: 'ok' | 'low' | 'empty'
}

export type CleaningStatus = 'todo' | 'in_progress' | 'done' | 'cancelled'

export interface CleaningTask {
  id: string
  placeId: string
  placeName: string
  label: string
  scheduledAt: string
  dueAt: string | null
  status: CleaningStatus
  late: boolean
  assignee: { id: string, email: string, name: string } | null
  externalRef: string | null
  notes: string | null
  checklist: { label: string, done: boolean }[]
  photos: { fileId: string, name: string, moment: 'before' | 'after' | 'damage', at: string }[]
  stockReports: { stockLevelId: string, item: string, level: 'ok' | 'low' | 'empty', at: string }[]
  startedAt: string | null
  completedAt: string | null
  /** Public view (secret link) only: stock levels of the place, and when the link expires. */
  stock?: { id: string, name: string, level: 'ok' | 'low' | 'empty' }[]
  expiresAt?: string
}

export interface CleaningAssignee { id: string, email: string, name: string }
