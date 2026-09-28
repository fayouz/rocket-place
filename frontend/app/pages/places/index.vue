<script setup lang="ts">
import type { Place } from '~/types/place'

const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()
useHead({ title: `Lieux · ${useAppConfig().rocket.name}` })

const { data: places, refresh } = await useAsyncData('places', () => api<Place[]>('/api/places'), { default: () => [] })

const showCreate = ref(false)
const form = reactive({ name: '', address: '' })
const creating = ref(false)
async function create() {
  creating.value = true
  try {
    await api('/api/places', { method: 'POST', body: { name: form.name, address: form.address || null } })
    showCreate.value = false
    form.name = ''; form.address = ''
    await refresh()
    toast.add({ title: 'Lieu créé', color: 'success' })
  }
  catch (error) {
    toast.add({ title: 'Non créé', description: apiErrorMessage(error), color: 'error' })
  }
  creating.value = false
}

async function setColor(place: Place, color: string) {
  try {
    await api(`/api/places/${place.id}`, { method: 'PATCH', body: { color } })
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Couleur non enregistrée', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <UDashboardPanel id="places">
    <template #header>
      <UDashboardNavbar title="Lieux">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton v-if="isAdmin" icon="i-lucide-plus" label="Ajouter un lieu" @click="showCreate = true" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <UCard v-for="p in places" :key="p.id">
          <NuxtLink :to="`/places/${p.id}`" class="flex items-center gap-2 font-semibold hover:underline">
            <PlaceDot :color="p.color" />
            {{ p.name }}
          </NuxtLink>
          <p class="mt-1 text-sm text-muted">{{ p.address ?? 'Aucune adresse' }}</p>
          <div v-if="isAdmin" class="mt-3 flex items-center gap-1.5">
            <span class="mr-1 text-xs text-muted">Couleur :</span>
            <button
              v-for="c in PLACE_COLORS" :key="c.value" type="button" :title="c.label"
              class="flex size-5 items-center justify-center rounded-full border border-default ring-2 ring-offset-1 ring-offset-default"
              :class="p.color === c.value ? 'ring-primary' : 'ring-transparent'" :style="{ backgroundColor: c.hex || 'transparent' }"
              @click="setColor(p, c.value)"
            >
              <UIcon v-if="!c.value" name="i-lucide-ban" class="size-3 text-muted" />
            </button>
          </div>
        </UCard>
      </div>
      <UCard v-if="!places.length">
        <p class="text-sm text-muted">Aucun lieu. {{ isAdmin ? '« Ajouter un lieu » pour commencer.' : 'Un administrateur doit d’abord créer un lieu.' }}</p>
      </UCard>
    </template>

    <UModal v-model:open="showCreate" title="Ajouter un lieu">
      <template #body>
        <div class="space-y-3">
          <UFormField label="Nom"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField label="Adresse (facultatif)"><UInput v-model="form.address" class="w-full" /></UFormField>
        </div>
      </template>
      <template #footer>
        <UButton variant="ghost" label="Annuler" @click="showCreate = false" />
        <UButton :loading="creating" label="Créer" @click="create" />
      </template>
    </UModal>
  </UDashboardPanel>
</template>
