<script setup lang="ts">
import type { Lock, Place } from '~/types/place'

// Administration: which place each Nuki lock opens.
definePageMeta({ admin: true })
const api = useApi()
const toast = useToast()
useHead({ title: `Serrures Nuki · ${useAppConfig().rocket.name}` })
const { data, refresh } = await useAsyncData('all-locks', () => api<{ demo: boolean, locks: Lock[] }>('/api/locks'))
const { data: properties } = await useAsyncData('properties', () => api<Place[]>('/api/places'), { default: () => [] })
// The select refuses an empty value: "none" stands for no place
const options = computed(() => [{ label: '— aucun lieu —', value: 'none' }, ...properties.value.map(p => ({ label: p.name, value: p.id }))])
async function link(lock: Lock, value: string) {
  try {
    await api(`/api/locks/${lock.id}`, { method: 'PUT', body: { place: value === 'none' ? null : value } })
    await refresh()
    toast.add({ title: 'Enregistré', color: 'success' })
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <UDashboardPanel id="locks">
    <template #header>
      <UDashboardNavbar title="Serrures Nuki">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <p class="text-sm text-muted">Choisis le lieu que chaque serrure ouvre : ses codes clavier sont alors prévus pour chaque séjour à venir. Une nouvelle serrure apparaît après « Synchroniser » (page Logements).</p>
      <UCard v-for="l in data?.locks ?? []" :key="l.id">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <b>{{ l.name }}</b>
            <p class="text-sm text-muted">{{ l.state }} · batterie {{ l.battery === null ? 'inconnue' : `${l.battery} %` }}</p>
          </div>
          <USelect :model-value="l.placeId ?? 'none'" :items="options" class="w-64" @update:model-value="link(l, String($event))" />
        </div>
      </UCard>
      <UCard v-if="data && !data.locks.length"><p class="text-sm text-muted">Aucune serrure Nuki trouvée.</p></UCard>
    </template>
  </UDashboardPanel>
</template>
