<script setup>
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from '@/composables/useTheme'
import { THEMES } from '@/constants/themes'
import { revealThemeChange } from '@/utils/landing/themeReveal'

const { t } = useI18n()
const { theme, setTheme } = useTheme()
const buttonRef = ref(null)
const isDark = computed(() => theme.value === THEMES.dark)
const label = computed(() => t(isDark.value ? 'landing.header.toLight' : 'landing.header.toDark'))

function toggle() {
  const next = isDark.value ? THEMES.light : THEMES.dark
  revealThemeChange(buttonRef.value, async () => {
    setTheme(next)
    document.documentElement.dataset.theme = next
    await nextTick()
  })
}
</script>

<template>
  <button
    ref="buttonRef"
    type="button"
    class="lp-top__icon-btn lp-theme-switch"
    role="switch"
    :aria-checked="isDark ? 'true' : 'false'"
    :aria-label="label"
    @click="toggle"
  >
    <svg class="lp-theme-switch__sun" viewBox="0 0 24 24" aria-hidden="true">
      <use href="#lp-i-sun" />
    </svg>
    <svg class="lp-theme-switch__moon" viewBox="0 0 24 24" aria-hidden="true">
      <use href="#lp-i-moon" />
    </svg>
  </button>
</template>
