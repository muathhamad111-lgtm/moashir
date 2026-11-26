<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ACCREDITED_LIMITS } from '@/constants/accredited'
import { localizedText } from '@/utils/localizedText'
import { formatNumber } from '@/utils/formatNumber'
import { ownNotesIndex, prefilledNote } from '@/utils/accredited/completionNotes'

const props = defineProps({
  options: { type: Array, required: true },
  notes: { type: Object, required: true },
  error: { type: String, default: '' },
  // The viewer's internal review notes: a chosen item starts with the matching one (operator 2026-11-22).
  reviewNotes: { type: Array, default: null },
})

const emit = defineEmits(['remove', 'note'])
const { t, n, locale } = useI18n()
// A note field shows only once asked for, or when the item already has a note.
const noteRequested = ref(new Set())
const showsNote = (key) => noteRequested.value.has(key) || Boolean(props.notes[key])

const index = computed(() => ownNotesIndex(props.reviewNotes))
const prefills = reactive({})
const fromNotes = (key) => Boolean(prefills[key]) && props.notes[key] === prefills[key]

watch(
  () => props.options,
  (options) => {
    const selectedKeys = options.map((option) => option.key)
    options
      .filter((option) => props.notes[option.key] === undefined)
      .forEach((option) => {
        const text = prefilledNote(option, { index: index.value, selectedKeys })
        prefills[option.key] = text
        if (text) emit('note', option.key, text)
      })
  },
  { immediate: true },
)

function requestNote(key) {
  noteRequested.value = new Set([...noteRequested.value, key])
}

function labelOf(option) {
  if (!option.sub)
    return t('review.completion.mainItem', { main: localizedText(option.main.name, locale.value) })
  return t('review.completion.itemCell', {
    sub: localizedText(option.sub.name, locale.value),
    kind: t(`accredited.completion.kinds.${option.kind}`),
  })
}
</script>

<!-- The chosen items as removable chips, each with an optional note for the entity. -->
<template>
  <section class="completion-summary" aria-labelledby="completion-summary-title">
    <h3 id="completion-summary-title" class="completion-summary__title">
      {{ t('review.completion.selectedTitle', { count: formatNumber(n, options.length) }) }}
    </h3>
    <p v-if="!options.length" class="completion-summary__empty">
      {{ t('review.completion.selectedEmpty') }}
    </p>
    <ul v-else class="completion-summary__list">
      <li v-for="option in options" :key="option.key" class="completion-summary__item">
        <div class="completion-summary__chip">
          <span class="completion-summary__label">{{ labelOf(option) }}</span>
          <span v-if="fromNotes(option.key)" class="completion-summary__from-notes">{{
            t('review.completion.fromNotes')
          }}</span>
          <button
            type="button"
            class="completion-summary__remove"
            :aria-label="t('review.completion.remove', { name: labelOf(option) })"
            @click="emit('remove', option.key)"
          >
            <AppIcon name="x" />
          </button>
        </div>
        <BaseButton
          v-if="!showsNote(option.key)"
          class="completion-summary__add-note"
          variant="ghost"
          size="sm"
          icon="plus"
          :aria-label="t('review.completion.addNoteFor', { name: labelOf(option) })"
          @click="requestNote(option.key)"
          >{{ t('review.completion.addNote') }}</BaseButton
        >
        <BaseInput
          v-else
          :model-value="notes[option.key] ?? ''"
          :name="`note-${option.key}`"
          :label="t('review.completion.itemNote')"
          :hint="
            t('review.completion.itemNoteHint', { max: ACCREDITED_LIMITS.COMPLETION_ITEM_NOTE_MAX })
          "
          @update:model-value="emit('note', option.key, $event)"
        />
      </li>
    </ul>
    <p v-if="error" id="completion-items-error" class="field__error" role="alert">{{ error }}</p>
  </section>
</template>
