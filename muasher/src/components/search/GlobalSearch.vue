<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSearchHandoffStore } from '@/stores/searchHandoff'
import { useGlobalSearch } from '@/composables/useGlobalSearch'
import { useRecentSearches } from '@/composables/useRecentSearches'
import { useListboxNavigation } from '@/composables/useListboxNavigation'
import {
  SEARCH_KEY_SHORTCUTS,
  SEARCH_MAX_LENGTH,
  SEARCH_MIN_LENGTH,
  SEARCH_SHORTCUT_CODE,
  SEARCH_SHORTCUT_SLASH,
  SEARCH_TYPES,
  searchDestination,
} from '@/constants/globalSearch'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import GlobalSearchOption from './GlobalSearchOption.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const RECENT = 'recent'
const EDITABLE_TAGS = ['INPUT', 'TEXTAREA', 'SELECT']

const { t, locale } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const handoff = useSearchHandoffStore()

const baseId = useId()
const inputId = `${baseId}-input`
const listboxId = `${baseId}-listbox`
const rootRef = ref(null)
const inputRef = ref(null)
const listboxRef = ref(null)
const expanded = ref(false)
const focused = ref(false)

const userId = computed(() => auth.user?.id ?? null)
const { query, term, groups, status, retry, clear } = useGlobalSearch()
const recent = useRecentSearches(userId)

const showRecent = computed(() => !term.value && recent.items.value.length > 0)
const visibleGroups = computed(() => {
  if (showRecent.value) {
    const items = recent.items.value.map((text) => ({
      type: RECENT,
      key: `${RECENT}:${text}`,
      title: text,
    }))
    return [{ type: RECENT, total: items.length, items }]
  }
  return status.value === 'success' ? groups.value : []
})
const flatOptions = computed(() => visibleGroups.value.flatMap((group) => group.items))
const nav = useListboxNavigation(flatOptions)

const hasPopupContent = computed(() => showRecent.value || status.value !== 'idle')
const isOpen = computed(() => expanded.value && hasPopupContent.value)
const isFloated = computed(() => focused.value || Boolean(query.value))
const activeDescendant = computed(() =>
  isOpen.value && nav.activeIndex.value >= 0 ? optionId(nav.activeIndex.value) : undefined,
)

const STATUS_MESSAGES = {
  loading: () => t('globalSearch.loading'),
  'too-short': () => t('globalSearch.tooShort', { min: SEARCH_MIN_LENGTH }),
  empty: () => t('globalSearch.empty', { query: term.value }),
  error: () => t('globalSearch.error'),
  success: () => t('globalSearch.count', { count: flatOptions.value.length }),
}

const liveMessage = computed(() => {
  if (!isOpen.value || showRecent.value) return ''
  return STATUS_MESSAGES[status.value]?.() ?? ''
})

function optionId(index) {
  return `${baseId}-option-${index}`
}

function groupLabelId(type) {
  return `${baseId}-group-${type}`
}

function indexOf(item) {
  return flatOptions.value.indexOf(item)
}

function groupTitle(group) {
  return group.type === RECENT
    ? t('globalSearch.recentTitle')
    : t(`globalSearch.groups.${group.type}`)
}

function localizedTitle(item) {
  const { title } = item
  if (typeof title === 'string') return title
  return title?.[locale.value] || title?.ar || title?.en || ''
}

function open() {
  expanded.value = true
}

function close() {
  expanded.value = false
  nav.reset()
}

function focusInput() {
  inputRef.value?.focus()
}

function navigateTo(item) {
  const destination = searchDestination(item, localizedTitle(item))
  if (!destination) return
  handoff.hand(destination.to.name, destination.prefill)
  router.push(destination.to)
}

function select(item) {
  if (item.type === RECENT) {
    query.value = item.title
    focusInput()
    return
  }
  if (item.type !== SEARCH_TYPES.users) recent.remember(term.value)
  close()
  clear()
  navigateTo(item)
}

function onClearQuery() {
  clear()
  focusInput()
}

function onRetry() {
  focusInput()
  retry()
}

function onClearRecent() {
  recent.clear()
  focusInput()
}

function stepOrOpen(event, step) {
  event.preventDefault()
  if (!isOpen.value) {
    open()
    return
  }
  nav.move(step)
}

const KEY_HANDLERS = {
  ArrowDown: (event) => stepOrOpen(event, 1),
  ArrowUp: (event) => stepOrOpen(event, -1),
  Enter: (event) => {
    if (!isOpen.value || !nav.activeOption.value) return
    event.preventDefault()
    select(nav.activeOption.value)
  },
  Escape: (event) => {
    if (isOpen.value) {
      event.preventDefault()
      close()
      return
    }
    if (query.value) {
      event.preventDefault()
      clear()
    }
  },
}

function onKeydown(event) {
  KEY_HANDLERS[event.key]?.(event)
}

function onFocus() {
  focused.value = true
  open()
}

function onFocusOut(event) {
  if (rootRef.value?.contains(event.relatedTarget)) return
  focused.value = false
  close()
}

function isEditable(target) {
  return Boolean(target?.isContentEditable) || EDITABLE_TAGS.includes(target?.tagName)
}

function onGlobalKeydown(event) {
  const combo = (event.ctrlKey || event.metaKey) && event.code === SEARCH_SHORTCUT_CODE
  const slash = event.key === SEARCH_SHORTCUT_SLASH && !isEditable(event.target)
  if (!combo && !slash) return
  event.preventDefault()
  focusInput()
  open()
}

watch(activeDescendant, async (id) => {
  if (!id) return
  await nextTick()
  listboxRef.value?.querySelector('[aria-selected="true"]')?.scrollIntoView?.({ block: 'nearest' })
})

onMounted(() => window.addEventListener('keydown', onGlobalKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onGlobalKeydown))
</script>

<template>
  <div
    ref="rootRef"
    class="global-search"
    :class="{ 'is-open': isOpen, 'is-floated': isFloated }"
    @focusout="onFocusOut"
  >
    <div class="global-search__field">
      <AppIcon name="search" class="global-search__icon" />
      <label :for="inputId" class="global-search__label">{{ t('globalSearch.label') }}</label>
      <input
        :id="inputId"
        ref="inputRef"
        v-model="query"
        class="global-search__input"
        type="text"
        role="combobox"
        autocomplete="off"
        spellcheck="false"
        enterkeyhint="search"
        :maxlength="SEARCH_MAX_LENGTH"
        aria-autocomplete="list"
        :aria-expanded="isOpen ? 'true' : 'false'"
        :aria-controls="listboxId"
        :aria-activedescendant="activeDescendant"
        :aria-keyshortcuts="SEARCH_KEY_SHORTCUTS"
        @focus="onFocus"
        @click="open"
        @input="open"
        @keydown="onKeydown"
      />
      <button
        v-if="query"
        type="button"
        class="global-search__clear"
        :aria-label="t('globalSearch.clearQuery')"
        @click="onClearQuery"
      >
        <AppIcon name="x" />
      </button>
      <kbd v-else class="global-search__shortcut" aria-hidden="true">{{
        t('globalSearch.shortcut')
      }}</kbd>
    </div>

    <div v-show="isOpen" class="global-search__popup">
      <div
        :id="listboxId"
        ref="listboxRef"
        role="listbox"
        class="global-search__listbox"
        :aria-label="t('globalSearch.resultsLabel')"
      >
        <ul
          v-for="group in visibleGroups"
          :key="group.type"
          role="group"
          class="global-search__group"
          :aria-labelledby="groupLabelId(group.type)"
        >
          <li :id="groupLabelId(group.type)" role="presentation" class="global-search__group-label">
            <span>{{ groupTitle(group) }}</span>
            <span v-if="group.type !== 'recent'" class="global-search__group-count">
              {{ t('globalSearch.groupCount', { shown: group.items.length, total: group.total }) }}
            </span>
          </li>
          <GlobalSearchOption
            v-for="item in group.items"
            :id="optionId(indexOf(item))"
            :key="item.key"
            :item="item"
            :title="localizedTitle(item)"
            :active="indexOf(item) === nav.activeIndex.value"
            @mousedown.prevent
            @click="select(item)"
          />
        </ul>
      </div>

      <template v-if="!showRecent">
        <div v-if="status === 'loading'" class="global-search__state">
          <Skeleton variant="text" :count="3" />
        </div>
        <p v-else-if="status === 'too-short'" class="global-search__state">
          {{ t('globalSearch.tooShort', { min: SEARCH_MIN_LENGTH }) }}
        </p>
        <p v-else-if="status === 'empty'" class="global-search__state">
          <AppIcon name="search" />
          {{ t('globalSearch.empty', { query: term }) }}
        </p>
        <div
          v-else-if="status === 'error'"
          class="global-search__state global-search__state--error"
        >
          <p class="global-search__error">
            <AppIcon name="warning-triangle" />
            {{ t('globalSearch.error') }}
          </p>
          <BaseButton variant="secondary" size="sm" icon="refresh" @click="onRetry">
            {{ t('globalSearch.retry') }}
          </BaseButton>
        </div>
      </template>

      <div class="global-search__footer">
        <BaseButton v-if="showRecent" variant="ghost" size="sm" icon="trash" @click="onClearRecent">
          {{ t('globalSearch.clearRecent') }}
        </BaseButton>
        <p v-else class="global-search__note">
          <AppIcon name="shield-info" />
          {{ t('globalSearch.scopeNote') }}
        </p>
      </div>
    </div>

    <p class="global-search__live" role="status" aria-live="polite">{{ liveMessage }}</p>
  </div>
</template>
