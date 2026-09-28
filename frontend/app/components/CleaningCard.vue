<script setup lang="ts">
import type { CleaningTask, StockItem, StockLevel } from '~/types/place'

// One cleaning, made for a phone: status buttons, checklist, photos (before/after/damage, straight from the camera),
// stock levels of the place and notes. Every change is saved at once and the updated task is emitted.
const props = defineProps<{ task: CleaningTask }>()
const emit = defineEmits<{ updated: [task: CleaningTask] }>()
const api = useApi()
const toast = useToast()
const open = ref(false)
const busy = ref(false)
const notes = ref(props.task.notes ?? '')
watch(() => props.task.notes, v => notes.value = v ?? '')

const doneCount = computed(() => props.task.checklist.filter(c => c.done).length)

const { data: items } = useAsyncData('stock-items', () => api<StockItem[]>('/api/stock-items'), { default: () => [], immediate: false })
const { data: levels, execute: loadLevels } = useAsyncData(`stock-levels-${props.task.placeId}`, () => api<StockLevel[]>('/api/stock-levels', { query: { place: `/api/places/${props.task.placeId}` } }), { default: () => [], immediate: false })
const itemName = (iri: string) => items.value.find(i => `/api/stock-items/${i.id}` === iri)?.name ?? '?'
watch(open, async (v) => {
  if (v && !levels.value.length) {
    await Promise.all([loadLevels(), items.value.length ? null : api<StockItem[]>('/api/stock-items').then(r => items.value = r)])
  }
})

async function save(path: string, body: Record<string, unknown> | FormData, method: 'PATCH' | 'POST' = 'PATCH') {
  busy.value = true
  try {
    emit('updated', await api<CleaningTask>(`/api/cleanings/${props.task.id}${path}`, { method, body }))
  }
  catch (error) {
    toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })
  }
  finally {
    busy.value = false
  }
}

const setStatus = (status: string) => save('', { status })
const check = (index: number, done: boolean) => save('', { checklist: [{ index, done }] })
const saveNotes = () => notes.value !== (props.task.notes ?? '') && save('', { notes: notes.value })

async function setStock(level: StockLevel, value: string) {
  await save('/stock', { stockLevelId: level.id, level: value }, 'POST')
  level.level = value as StockLevel['level']
}

async function upload(event: Event, moment: string) {
  const input = event.target as HTMLInputElement
  for (const file of Array.from(input.files ?? [])) {
    const form = new FormData()
    form.append('file', file)
    form.append('moment', moment)
    await save('/photos', form, 'POST')
  }
  input.value = ''
}
</script>

<template>
  <UCard :ui="{ body: 'p-3 sm:p-4' }">
    <button type="button" class="flex w-full items-start justify-between gap-3 text-left" @click="open = !open">
      <div>
        <p class="font-semibold">{{ task.placeName }}</p>
        <p class="text-sm text-muted">
          {{ task.label }} · {{ dayFr(task.scheduledAt) }} {{ hourFr(task.scheduledAt) }}<span v-if="task.dueAt"> → {{ hourFr(task.dueAt) }}</span>
        </p>
        <p class="text-xs text-muted">{{ task.assignee?.name ?? 'Non attribué' }}<span v-if="task.checklist.length"> · {{ doneCount }}/{{ task.checklist.length }} points</span><span v-if="task.photos.length"> · {{ task.photos.length }} photo(s)</span></p>
      </div>
      <div class="flex shrink-0 flex-col items-end gap-1">
        <UBadge :color="CLEANING_STATUS_COLOR[task.status]" variant="subtle">{{ CLEANING_STATUS_LABEL[task.status] }}</UBadge>
        <UBadge v-if="task.late" color="error" variant="solid">En retard</UBadge>
      </div>
    </button>

    <div v-if="open" class="mt-4 space-y-5">
      <div class="grid grid-cols-2 gap-2">
        <UButton v-if="task.status === 'todo'" block size="lg" icon="i-lucide-play" label="Commencer" :loading="busy" @click="setStatus('in_progress')" />
        <UButton v-if="task.status !== 'done'" block size="lg" color="success" icon="i-lucide-check" label="Terminé" :loading="busy" @click="setStatus('done')" />
        <UButton v-else block size="lg" variant="soft" icon="i-lucide-rotate-ccw" label="Rouvrir" :loading="busy" @click="setStatus('in_progress')" />
      </div>

      <section v-if="task.checklist.length">
        <h3 class="mb-2 text-sm font-semibold">Checklist</h3>
        <div class="space-y-1">
          <label v-for="(c, i) in task.checklist" :key="i" class="flex min-h-11 items-center gap-3 rounded-md px-2 hover:bg-elevated">
            <UCheckbox :model-value="c.done" :disabled="busy" @update:model-value="v => check(i, v === true)" />
            <span :class="c.done ? 'text-muted line-through' : ''">{{ c.label }}</span>
          </label>
        </div>
      </section>

      <section>
        <h3 class="mb-2 text-sm font-semibold">Photos</h3>
        <div class="grid grid-cols-3 gap-2">
          <label v-for="m in ['before', 'after', 'damage']" :key="m" class="cursor-pointer">
            <input type="file" accept="image/*" capture="environment" multiple class="hidden" @change="upload($event, m)">
            <span class="flex min-h-11 items-center justify-center gap-1 rounded-md border border-default text-sm" :class="m === 'damage' ? 'text-error' : ''">
              <UIcon :name="m === 'damage' ? 'i-lucide-triangle-alert' : 'i-lucide-camera'" /> {{ PHOTO_MOMENT_LABEL[m] }}
            </span>
          </label>
        </div>
        <ul v-if="task.photos.length" class="mt-2 space-y-1 text-xs text-muted">
          <li v-for="p in task.photos" :key="p.fileId">{{ PHOTO_MOMENT_LABEL[p.moment] }} · {{ whenFr(p.at) }} · {{ p.name }}</li>
        </ul>
      </section>

      <section>
        <h3 class="mb-2 text-sm font-semibold">Stock</h3>
        <div v-for="l in levels" :key="l.id" class="flex min-h-11 items-center justify-between gap-2">
          <span class="text-sm">{{ itemName(l.item) }}</span>
          <div class="flex gap-1">
            <UButton
              v-for="lvl in ['ok', 'low', 'empty']" :key="lvl" size="sm" :color="l.level === lvl ? STOCK_LEVEL_COLOR[lvl] : 'neutral'"
              :variant="l.level === lvl ? 'solid' : 'outline'" :label="STOCK_LEVEL_LABEL[lvl]" :disabled="busy" @click="setStock(l, lvl)"
            />
          </div>
        </div>
        <p v-if="!levels.length" class="text-xs text-muted">Aucun article suivi pour ce lieu.</p>
      </section>

      <section>
        <h3 class="mb-2 text-sm font-semibold">Notes</h3>
        <UTextarea v-model="notes" :rows="3" class="w-full" placeholder="Dégât, oubli, remarque…" @blur="saveNotes" />
      </section>
    </div>
  </UCard>
</template>
