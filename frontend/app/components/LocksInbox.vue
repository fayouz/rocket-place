<script setup lang="ts">
import type { AccessGrant, Lock } from '~/types/place'

// Locks of a place on the left; on the right the chosen lock: state and battery, its access grants ("Créer sur la
// serrure", always confirmed) and its latest events.
const props = defineProps<{ placeId: string }>()
const api = useApi()
const toast = useToast()
const { data: locks } = await useAsyncData(`locks-${props.placeId}`, () => api<{ demo: boolean, locks: Lock[] }>(`/api/places/${props.placeId}/locks`).catch(() => null))
const { data: grants, refresh: refreshGrants } = await useAsyncData(`access-grants-${props.placeId}`, () => api<AccessGrant[]>(`/api/places/${props.placeId}/access-grants`), { default: () => [] })

const selected = ref<number | null>(null)
watchEffect(() => {
  const list = locks.value?.locks ?? []
  if (!list.some(l => l.id === selected.value)) selected.value = list[0]?.id ?? null
})
const current = computed(() => locks.value?.locks.find(l => l.id === selected.value) ?? null)
const grantsOf = (lockId: number) => (grants.value ?? []).filter(g => g.lockId === lockId && g.status !== 'revoked')

const busy = ref<string | null>(null)
async function send(g: AccessGrant) {
  if (!confirm(`Créer le code ${g.code} sur la serrure pour « ${g.label} » (${dayFr(g.validFrom)} → ${dayFr(g.validUntil)}) ?`)) return
  busy.value = g.id
  try {
    await api(`/api/access-grants/${g.id}/send`, { method: 'POST' })
  }
  catch (error) {
    toast.add({ title: 'Code non créé', description: apiErrorMessage(error), color: 'error' })
  }
  busy.value = null
  await refreshGrants()
}

async function revoke(g: AccessGrant) {
  if (!confirm(`Révoquer l’accès « ${g.label} » ?`)) return
  busy.value = g.id
  try {
    await api(`/api/access-grants/${g.id}/revoke`, { method: 'POST' })
    await refreshGrants()
  }
  catch (error) {
    toast.add({ title: 'Non révoqué', description: apiErrorMessage(error), color: 'error' })
  }
  busy.value = null
}

// New access grant
const showNew = ref(false)
const form = reactive({ label: '', validFrom: '', validUntil: '', externalRef: '' })
async function plan() {
  if (!current.value) return
  try {
    await api(`/api/places/${props.placeId}/access-grants`, {
      method: 'POST',
      body: { lockId: current.value.id, label: form.label, validFrom: form.validFrom, validUntil: form.validUntil, externalRef: form.externalRef || null },
    })
    showNew.value = false
    form.label = ''; form.validFrom = ''; form.validUntil = ''; form.externalRef = ''
    await refreshGrants()
  }
  catch (error) {
    toast.add({ title: 'Non planifié', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <div class="flex flex-col gap-4 lg:h-[calc(100vh-12rem)] lg:flex-row">
    <div class="min-w-0 space-y-1 overflow-y-auto lg:w-80 lg:shrink-0 lg:border-r lg:border-default lg:pr-3">
      <button
        v-for="l in locks?.locks ?? []" :key="l.id" type="button" class="block w-full rounded-md p-2.5 text-left transition-colors"
        :class="selected === l.id ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-elevated'" @click="selected = l.id"
      >
        <div class="flex items-center justify-between gap-2">
          <b class="truncate text-sm">{{ l.name }}</b>
          <UBadge size="sm" :color="l.locked ? 'success' : 'warning'" variant="subtle" :label="l.state" />
        </div>
        <p class="text-xs text-muted">
          <UBadge v-if="l.provider !== 'nuki'" size="sm" variant="subtle" color="neutral" :label="l.provider" class="mr-1" />
          Batterie {{ l.battery === null ? 'inconnue' : `${l.battery} %` }} · {{ grantsOf(l.id).length }} accès actif{{ grantsOf(l.id).length > 1 ? 's' : '' }}
          <span v-if="l.batteryCritical || l.keypadBatteryCritical" class="text-error"> · ⚠</span>
        </p>
      </button>
      <p v-if="!locks" class="text-sm text-muted">La serrure ne répond pas pour le moment.</p>
      <p v-else-if="!locks.locks.length" class="text-sm text-muted">Aucune serrure liée à ce lieu (Administration → Serrures).</p>
    </div>

    <div v-if="current" class="grid min-w-0 flex-1 gap-4 lg:grid-cols-3 lg:grid-rows-[minmax(0,1fr)]">
      <UCard class="lg:col-span-2" :ui="{ root: 'flex flex-col lg:min-h-0', body: 'min-h-0 flex-1 space-y-3 overflow-y-auto' }">
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-semibold">{{ current.name }}</h3>
              <UBadge :color="current.locked ? 'success' : 'warning'" variant="subtle" :label="current.state" />
              <UBadge :color="current.batteryCritical ? 'error' : 'neutral'" variant="subtle" :icon="batteryIcon(current.battery)" :label="current.battery === null ? '?' : `${current.battery} %`" />
              <UBadge v-if="current.keypadBatteryCritical" color="error" variant="subtle" label="Pile clavier faible" />
            </div>
            <UButton size="sm" icon="i-lucide-plus" label="Planifier un accès" @click="showNew = true" />
          </div>
          <p class="mt-1 text-sm text-muted">Un accès est d’abord planifié (code réservé), puis créé sur la serrure seulement sur ton clic.</p>
        </template>
        <div v-for="g in grantsOf(current.id)" :key="g.id" class="rounded-md border border-default p-3" :class="{ 'border-l-4 border-l-error': g.status === 'error' }">
          <div class="flex justify-between gap-3"><b>{{ g.label }}</b><span v-if="g.externalRef" class="text-xs text-muted">Réf. {{ g.externalRef }}</span></div>
          <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
            <span><span class="font-mono text-lg font-semibold tracking-widest">{{ g.code }}</span><span class="text-sm text-muted"> · {{ whenFr(g.validFrom) }} → {{ whenFr(g.validUntil) }}</span></span>
            <div class="flex items-center gap-1.5">
              <UBadge v-if="g.status === 'created'" color="success" variant="subtle" label="Créé sur la serrure" />
              <UButton v-else size="sm" icon="i-lucide-key-round" label="Créer sur la serrure" :loading="busy === g.id" :disabled="locks?.demo" @click="send(g)" />
              <UButton size="sm" variant="soft" color="error" icon="i-lucide-x" :loading="busy === g.id" @click="revoke(g)" />
            </div>
          </div>
          <p v-if="g.error && g.status === 'error'" class="text-sm text-error">⚠ {{ g.error }}</p>
        </div>
        <p v-if="!grantsOf(current.id).length" class="text-sm text-muted">Aucun accès planifié sur cette serrure.</p>
      </UCard>
      <UCard class="lg:col-span-1" :ui="{ root: 'flex flex-col lg:min-h-0', body: 'min-h-0 flex-1 overflow-y-auto' }">
        <template #header><p class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted"><UIcon name="i-lucide-history" class="size-3.5" /> Historique</p></template>
        <p v-for="(g, i) in current.logs" :key="i" class="text-sm text-muted">{{ whenFr(g.date) }} · {{ LOCK_ACTIONS[g.action] || `Action ${g.action}` }}<template v-if="g.who"> · {{ g.who }}</template></p>
        <p v-if="!current.logs.length" class="text-sm text-muted">Aucun passage récent.</p>
      </UCard>
    </div>

    <UModal v-model:open="showNew" title="Planifier un accès">
      <template #body>
        <div class="space-y-3">
          <UFormField label="Libellé"><UInput v-model="form.label" class="w-full" placeholder="Ex. Sofia Rossi" /></UFormField>
          <UFormField label="Début"><UInput v-model="form.validFrom" type="datetime-local" class="w-full" /></UFormField>
          <UFormField label="Fin"><UInput v-model="form.validUntil" type="datetime-local" class="w-full" /></UFormField>
          <UFormField label="Référence externe (facultatif)"><UInput v-model="form.externalRef" class="w-full" placeholder="Ex. id de réservation" /></UFormField>
        </div>
      </template>
      <template #footer>
        <UButton variant="ghost" label="Annuler" @click="showNew = false" />
        <UButton label="Planifier" @click="plan" />
      </template>
    </UModal>
  </div>
</template>
