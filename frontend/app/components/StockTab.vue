<script setup lang="ts">
import type { StockItem, StockLevel } from '~/types/place'

// "Stock" tab of a place: catalogue items it tracks, with their level (OK/Bas/Vide), settable by anyone; an admin
// can also start/stop tracking an item (creates/deletes the StockLevel row) from the full catalogue.
const props = defineProps<{ placeId: string }>()
const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()

const { data: items } = await useAsyncData('stock-items', () => api<StockItem[]>('/api/stock-items'), { default: () => [] })
const { data: levels, refresh: refreshLevels } = await useAsyncData(`stock-levels-${props.placeId}`, () => api<StockLevel[]>('/api/stock-levels', { query: { place: `/api/places/${props.placeId}` } }), { default: () => [] })

const levelOf = (itemId: string) => levels.value.find(l => l.item === `/api/stock-items/${itemId}`)

async function setLevel(item: StockItem, level: string) {
  const existing = levelOf(item.id)
  try {
    if (existing) {
      await api(`/api/stock-levels/${existing.id}`, { method: 'PATCH', body: { level } })
    }
    else {
      await api('/api/stock-levels', { method: 'POST', body: { place: `/api/places/${props.placeId}`, item: `/api/stock-items/${item.id}`, level } })
    }
    await refreshLevels()
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}

async function untrack(item: StockItem) {
  const existing = levelOf(item.id)
  if (!existing) return
  try {
    await api(`/api/stock-levels/${existing.id}`, { method: 'DELETE' })
    await refreshLevels()
  }
  catch (error) {
    toast.add({ title: 'Non retiré', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <div class="space-y-2">
    <UCard v-for="item in items" :key="item.id">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <b class="text-sm">{{ item.name }}</b>
          <p class="text-xs text-muted">Réassort × {{ item.reorderQty }}<span v-if="item.subscription"> · abonnement</span></p>
        </div>
        <div v-if="levelOf(item.id)" class="flex items-center gap-1.5">
          <UBadge
            v-for="lvl in ['ok', 'low', 'empty']" :key="lvl" :color="levelOf(item.id)?.level === lvl ? STOCK_LEVEL_COLOR[lvl] : 'neutral'"
            :variant="levelOf(item.id)?.level === lvl ? 'solid' : 'subtle'" class="cursor-pointer" @click="setLevel(item, lvl)"
          >
            {{ STOCK_LEVEL_LABEL[lvl] }}
          </UBadge>
          <UButton v-if="isAdmin" size="xs" variant="ghost" color="error" icon="i-lucide-x" @click="untrack(item)" />
        </div>
        <UButton v-else-if="isAdmin" size="sm" variant="soft" label="Suivre" @click="setLevel(item, 'ok')" />
        <span v-else class="text-xs text-muted">Non suivi</span>
      </div>
    </UCard>
    <UCard v-if="!items.length"><p class="text-sm text-muted">Aucun article au catalogue.</p></UCard>
  </div>
</template>
