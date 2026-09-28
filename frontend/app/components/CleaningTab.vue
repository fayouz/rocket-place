<script setup lang="ts">
import type { CleaningTask } from '~/types/place'

// "Ménage" tab of a place: its cleanings; an administrator plans new ones and edits the checklist template
// (copied into each new cleaning, existing ones keep theirs).
const props = defineProps<{ placeId: string }>()
const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()

const { data: tasks, refresh } = await useAsyncData(`cleanings-${props.placeId}`, () => api<CleaningTask[]>(`/api/places/${props.placeId}/cleanings`), { default: () => [] })
const { data: checklist } = await useAsyncData(`cleaning-checklist-${props.placeId}`, () => api<string[]>(`/api/places/${props.placeId}/cleaning-checklist`), { default: () => [] })
const replace = (t: CleaningTask) => tasks.value = tasks.value.map(x => x.id === t.id ? t : x)

const checklistText = ref(checklist.value.join('\n'))
async function saveChecklist() {
  try {
    checklist.value = await api<string[]>(`/api/places/${props.placeId}/cleaning-checklist`, { method: 'PUT', body: { items: checklistText.value.split('\n') } })
    checklistText.value = checklist.value.join('\n')
    toast.add({ title: 'Checklist enregistrée', color: 'success' })
  }
  catch (error) {
    toast.add({ title: 'Non enregistrée', description: apiErrorMessage(error), color: 'error' })
  }
}

const form = reactive({ label: 'Ménage', date: new Date().toLocaleDateString('sv-SE'), from: '11:00', until: '16:00', assigneeEmail: '' })
async function create() {
  try {
    await api(`/api/places/${props.placeId}/cleanings`, {
      method: 'POST',
      body: {
        label: form.label,
        scheduledAt: new Date(`${form.date}T${form.from}`).toISOString(),
        dueAt: form.until ? new Date(`${form.date}T${form.until}`).toISOString() : null,
        ...(form.assigneeEmail ? { assigneeEmail: form.assigneeEmail } : {}),
      },
    })
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Non créé', description: apiErrorMessage(error), color: 'error' })
  }
}

async function remove(task: CleaningTask) {
  try {
    await api(`/api/cleanings/${task.id}`, { method: 'DELETE' })
    await refresh()
  }
  catch (error) {
    toast.add({ title: 'Non supprimé', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-3 lg:col-span-2">
      <div v-for="t in tasks" :key="t.id" class="flex items-start gap-2">
        <CleaningCard :task="t" class="flex-1" @updated="replace" />
        <UButton v-if="isAdmin" icon="i-lucide-trash-2" color="error" variant="ghost" aria-label="Supprimer" @click="remove(t)" />
      </div>
      <UCard v-if="!tasks.length"><p class="text-sm text-muted">Aucun ménage planifié pour ce lieu.</p></UCard>
    </div>
    <div v-if="isAdmin" class="space-y-4">
      <UCard>
        <template #header><b class="text-sm">Planifier un ménage</b></template>
        <div class="space-y-2">
          <UInput v-model="form.label" placeholder="Libellé" class="w-full" />
          <UInput v-model="form.date" type="date" class="w-full" />
          <div class="flex gap-2">
            <UInput v-model="form.from" type="time" class="flex-1" />
            <UInput v-model="form.until" type="time" class="flex-1" />
          </div>
          <UInput v-model="form.assigneeEmail" type="email" placeholder="E-mail de la personne (optionnel)" class="w-full" />
          <UButton block icon="i-lucide-plus" label="Planifier" @click="create" />
        </div>
      </UCard>
      <UCard>
        <template #header><b class="text-sm">Checklist du lieu</b></template>
        <p class="mb-2 text-xs text-muted">Un point par ligne, recopiée dans chaque nouveau ménage.</p>
        <UTextarea v-model="checklistText" :rows="8" class="w-full" />
        <UButton class="mt-2" block variant="soft" label="Enregistrer" @click="saveChecklist" />
      </UCard>
    </div>
  </div>
</template>
