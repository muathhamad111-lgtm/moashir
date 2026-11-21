<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import Badge from '@/components/ui/Badge.vue'
import { COMPLETION_ITEM_KINDS } from '@/constants/accredited'
import { localizedText } from '@/utils/localizedText'
import { formatNumber } from '@/utils/formatNumber'
import { matchesQuery } from '@/utils/text/normalizeArabic'

const KINDS = [COMPLETION_ITEM_KINDS.CV, COMPLETION_ITEM_KINDS.EVIDENCE]

const props = defineProps({
  groups: { type: Array, required: true },
  selected: { type: Array, required: true },
  openMainId: { type: Number, default: null },
  // Item keys suggested from the scores (design §B.3.3); their rows carry a «مقترح» tag.
  suggested: { type: Array, default: () => [] },
})

const emit = defineEmits(['toggle', 'toggleMany'])
const { t, n, locale } = useI18n()
const search = ref('')
// The criterion the assessor is on opens first, with every criterion that holds a suggestion.
const expanded = ref(
  new Set([
    props.openMainId ?? props.groups[0]?.main.id,
    ...props.groups
      .filter((group) => group.options.some((option) => props.suggested.includes(option.key)))
      .map((group) => group.main.id),
  ]),
)

const name = (value) => localizedText(value, locale.value)
const kindLabel = (kind) => t(`accredited.completion.kinds.${kind}`)
const optionOf = (row, kind) => row.options.find((option) => option.kind === kind) ?? null
const isSelected = (option) => props.selected.includes(option.key)
const isSuggested = (row) => row.options.some((option) => props.suggested.includes(option.key))

// Rows per main criterion, narrowed by the search; a search shows every matching group open. The
// main criterion's own attachments come first, as one evidence-only row.
const rows = computed(() => {
  const term = search.value.trim()
  return props.groups
    .map((group) => ({
      main: group.main,
      options: group.options,
      mainOption: matchesTerm(group.main, term)
        ? (group.options.find((option) => !option.sub) ?? null)
        : null,
      subs: group.main.subs
        .map((sub) => ({
          sub,
          options: group.options.filter((option) => option.sub?.id === sub.id),
        }))
        .filter((row) => row.options.length)
        .filter((row) => !term || matchesQuery(name(row.sub.name), term)),
    }))
    .filter((group) => group.subs.length || group.mainOption)
})

function matchesTerm(main, term) {
  return !term || matchesQuery(name(main.name), term)
}

const isOpen = (group) => Boolean(search.value) || expanded.value.has(group.main.id)

function toggleGroup(group) {
  const next = new Set(expanded.value)
  if (next.has(group.main.id)) next.delete(group.main.id)
  else next.add(group.main.id)
  expanded.value = next
}

function countOf(group) {
  const chosen = group.options.filter(isSelected).length
  return t('review.completion.selectedCount', {
    selected: formatNumber(n, chosen),
    total: formatNumber(n, group.options.length),
  })
}

function columnKeys(group, kind) {
  return group.options.filter((option) => option.kind === kind).map((option) => option.key)
}

function columnChecked(group, kind) {
  const keys = columnKeys(group, kind)
  return keys.length > 0 && keys.every((key) => props.selected.includes(key))
}
</script>

<template>
  <div class="completion-picker">
    <BaseInput v-model="search" name="completion-search" :label="t('review.completion.search')" />
    <div class="completion-picker__scroll">
      <table class="completion-picker__table">
        <thead>
          <tr>
            <th scope="col">{{ t('review.completion.columnSub') }}</th>
            <th v-for="kind in KINDS" :key="kind" scope="col">{{ kindLabel(kind) }}</th>
          </tr>
        </thead>
        <tbody v-for="group in rows" :key="group.main.id">
          <tr class="completion-picker__group">
            <th scope="rowgroup">
              <button
                type="button"
                class="completion-picker__toggle"
                :aria-expanded="isOpen(group)"
                @click="toggleGroup(group)"
              >
                <AppIcon name="chevron-down" class="completion-picker__chevron" />
                <span>{{ name(group.main.name) }}</span>
                <span class="completion-picker__count">{{ countOf(group) }}</span>
              </button>
            </th>
            <td v-for="kind in KINDS" :key="kind">
              <input
                v-if="columnKeys(group, kind).length"
                type="checkbox"
                class="completion-picker__check"
                :checked="columnChecked(group, kind)"
                :aria-label="
                  t('review.completion.selectColumn', {
                    kind: kindLabel(kind),
                    main: name(group.main.name),
                  })
                "
                @change="emit('toggleMany', columnKeys(group, kind), $event.target.checked)"
              />
            </td>
          </tr>
          <template v-if="isOpen(group)">
            <tr v-if="group.mainOption" class="completion-picker__row">
              <th scope="row">{{ t('review.completion.mainRow') }}</th>
              <td class="completion-dialog__option">
                <span class="completion-picker__none" aria-hidden="true">—</span>
              </td>
              <td class="completion-dialog__option">
                <input
                  type="checkbox"
                  class="completion-picker__check"
                  :name="`item-${group.mainOption.key}`"
                  :checked="isSelected(group.mainOption)"
                  :aria-label="t('review.completion.mainItem', { main: name(group.main.name) })"
                  @change="emit('toggle', group.mainOption.key, $event.target.checked)"
                />
              </td>
            </tr>
            <tr v-for="row in group.subs" :key="row.sub.id" class="completion-picker__row">
              <th scope="row">
                {{ name(row.sub.name) }}
                <Badge v-if="isSuggested(row)" variant="warning">{{
                  t('review.completion.suggestedTag')
                }}</Badge>
              </th>
              <td v-for="kind in KINDS" :key="kind" class="completion-dialog__option">
                <input
                  v-if="optionOf(row, kind)"
                  type="checkbox"
                  class="completion-picker__check"
                  :name="`item-${optionOf(row, kind).key}`"
                  :checked="isSelected(optionOf(row, kind))"
                  :aria-label="
                    t('review.completion.itemCell', {
                      sub: name(row.sub.name),
                      kind: kindLabel(kind),
                    })
                  "
                  @change="emit('toggle', optionOf(row, kind).key, $event.target.checked)"
                />
                <span v-else class="completion-picker__none" aria-hidden="true">—</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <p v-if="!rows.length" class="completion-picker__empty">
        {{ t('review.completion.noMatches') }}
      </p>
    </div>
  </div>
</template>
