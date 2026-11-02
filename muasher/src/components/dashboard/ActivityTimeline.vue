<script setup>
import { useI18n } from 'vue-i18n'
import { withLatinNumerals } from '@/utils/numerals'

const props = defineProps({
  items: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
})

const { locale } = useI18n()

const TYPE_ICONS = {
  registration: 'fa-solid fa-building-circle-check',
  payment: 'fa-solid fa-money-bill-wave',
  assessment: 'fa-solid fa-clipboard-list',
  accreditation: 'fa-solid fa-stamp',
  certificate: 'fa-solid fa-award',
  assessor: 'fa-solid fa-user-plus',
  note: 'fa-solid fa-comment-dots',
  edit: 'fa-solid fa-pen-to-square',
}

function iconOf(item) {
  return TYPE_ICONS[item.type] ?? 'fa-solid fa-circle-dot'
}

function textOf(item) {
  return props.localized(item.text)
}

function timeOf(item) {
  if (!item.at) return ''
  const lang = withLatinNumerals(locale.value === 'ar' ? 'ar-SA' : 'en-GB')
  return new Intl.DateTimeFormat(lang, { dateStyle: 'medium', timeStyle: 'short' }).format(
    new Date(item.at),
  )
}
</script>

<template>
  <ol class="timeline">
    <li v-for="item in items" :key="item.id" class="timeline__item">
      <span class="timeline__marker" aria-hidden="true">
        <i :class="iconOf(item)"></i>
      </span>
      <div class="timeline__body">
        <p class="timeline__text">{{ textOf(item) }}</p>
        <time v-if="item.at" class="timeline__time" :datetime="item.at">{{ timeOf(item) }}</time>
      </div>
    </li>
  </ol>
</template>
