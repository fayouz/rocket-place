<script setup lang="ts">
import type { ExplorerLocation } from '#file-explorer'

// "Documents" tab of a place: the reusable explorer (@rocket/file-explorer) on the place's Rocket Cloud folder,
// proxied by our own API (usePlaceDocuments). Read-only for non-admins (the explorer still lets them browse and download).
const props = defineProps<{ placeId: string }>()
const { isAdmin } = useAuth()
const adapter = usePlaceDocuments(props.placeId)
const location = ref<ExplorerLocation>({ space: PLACE_SPACE, folder: null })
</script>

<template>
  <UCard :ui="{ body: 'p-0 sm:p-0' }">
    <RocketFileExplorer
      v-model:location="location" :adapter="adapter" :space="PLACE_SPACE" :readonly="!isAdmin"
      height="calc(100vh - 14rem)"
    />
  </UCard>
</template>
