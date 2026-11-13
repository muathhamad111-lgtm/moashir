<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import { SITE_SETTINGS_LIMITS } from '@/constants/cms'
import { moveIndex } from '@/utils/reorder'

const rows = defineModel('rows', { type: Array, required: true })

defineProps({
  errorOf: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()

function move(index, offset) {
  const next = moveIndex(rows.value, index, index + offset)
  rows.value.splice(0, rows.value.length, ...next)
}
</script>

<template>
  <fieldset class="site-settings__group">
    <legend class="site-settings__legend">
      {{ t('settings.homepage.siteSettings.quickLinks') }}
    </legend>
    <ul v-if="rows.length" class="site-settings__rows">
      <li
        v-for="(row, index) in rows"
        :key="`quick-${index}`"
        class="site-settings__row site-settings__row--stack"
      >
        <div class="site-settings__grid">
          <BaseInput
            v-model="row.label.ar"
            :name="`quickLabelAr-${index}`"
            :label="t('settings.homepage.siteSettings.linkLabelAr')"
            :error="errorOf(`quickLinks.${index}.label.ar`)"
          />
          <BaseInput
            v-model="row.label.en"
            :name="`quickLabelEn-${index}`"
            :label="t('settings.homepage.siteSettings.linkLabelEn')"
            input-dir="ltr"
          />
        </div>
        <div class="site-settings__row site-settings__row--inline">
          <BaseInput
            v-model="row.url"
            :name="`quickUrl-${index}`"
            :label="t('settings.homepage.siteSettings.url')"
            :hint="t('settings.homepage.siteSettings.urlHint')"
            :error="errorOf(`quickLinks.${index}.url`)"
            input-dir="ltr"
          />
          <div class="site-settings__row-actions">
            <BaseButton
              variant="ghost"
              size="sm"
              icon="arrow-up"
              icon-only
              :disabled="disabled || index === 0"
              :aria-label="t('settings.homepage.siteSettings.moveUp')"
              @click="move(index, -1)"
            />
            <BaseButton
              variant="ghost"
              size="sm"
              icon="arrow-down"
              icon-only
              :disabled="disabled || index === rows.length - 1"
              :aria-label="t('settings.homepage.siteSettings.moveDown')"
              @click="move(index, 1)"
            />
            <BaseButton
              variant="ghost"
              size="sm"
              icon="trash"
              icon-only
              :disabled="disabled"
              :aria-label="t('settings.homepage.siteSettings.removeLink')"
              @click="rows.splice(index, 1)"
            />
          </div>
        </div>
      </li>
    </ul>
    <p v-else class="site-settings__empty">{{ t('settings.homepage.siteSettings.noLinks') }}</p>
    <BaseButton
      variant="secondary"
      size="sm"
      icon="plus"
      :disabled="disabled || rows.length >= SITE_SETTINGS_LIMITS.quickLinksMax"
      @click="rows.push({ label: { ar: '', en: '' }, url: '' })"
    >
      {{ t('settings.homepage.siteSettings.addLink') }}
    </BaseButton>
  </fieldset>
</template>
