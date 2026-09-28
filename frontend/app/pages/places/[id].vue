<script setup lang="ts">
import type { Place } from '~/types/place'

// A place: locks and access grants, domotique connectors, documents, cleanings.
const route = useRoute()
const api = useApi()
const id = computed(() => String(route.params.id))
const { data: place } = await useAsyncData(`place-${id.value}`, () => api<Place>(`/api/places/${id.value}`))
useHead({ title: () => `${place.value?.name ?? 'Lieu'} · ${useAppConfig().rocket.name}` })

const tabs = [
  { label: 'Serrures', value: 'locks', icon: 'i-lucide-lock' },
  { label: 'Domotique', value: 'domotique', icon: 'i-lucide-house-wifi' },
  { label: 'Documents', value: 'documents', icon: 'i-lucide-folder' },
  { label: 'Ménage', value: 'menage', icon: 'i-lucide-sparkles' },
]
const tab = computed({
  get: () => tabs.some(t => t.value === route.query.tab) ? String(route.query.tab) : 'locks',
  set: (v: string) => navigateTo({ query: { ...route.query, tab: v === 'locks' ? undefined : v } }, { replace: true }),
})
</script>

<template>
  <UDashboardPanel id="place">
    <template #header>
      <UDashboardNavbar>
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #title>
          <span class="flex items-center gap-2"><PlaceDot :color="place?.color" /> {{ place?.name }}</span>
        </template>
        <template #right>
          <span v-if="place?.address" class="hidden text-sm text-muted sm:inline">{{ place.address }}</span>
        </template>
      </UDashboardNavbar>
      <UDashboardToolbar>
        <UTabs v-model="tab" :items="tabs" :content="false" variant="link" />
      </UDashboardToolbar>
    </template>
    <template #body>
      <LocksInbox v-if="tab === 'locks'" :place-id="id" />
      <DomotiqueTab v-else-if="tab === 'domotique'" :place-id="id" />
      <DocumentsTab v-else-if="tab === 'documents'" :place-id="id" />
      <CleaningTab v-else-if="tab === 'menage'" :place-id="id" />
    </template>
  </UDashboardPanel>
</template>
