<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import { MENU_LINK_TYPES, MENU_PAGE_TARGETS } from '@/constants/siteMenu'
import { isMenuGroup } from '@/utils/siteMenu'

const item = defineModel('item', { type: Object, required: true })

const props = defineProps({
  path: { type: String, required: true },
  errorOf: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['remove'])

const { t } = useI18n()
const headingId = useId()
const isGroup = computed(() => isMenuGroup(item.value))
const typeOptions = computed(() =>
  Object.values(MENU_LINK_TYPES).map((type) => ({
    value: type,
    label: t(`identity.menus.linkTypes.${type}`),
  })),
)
const targetOptions = computed(() =>
  MENU_PAGE_TARGETS.map((target) => ({
    value: target.url,
    label: t(`identity.menus.targets.${target.id}`),
  })),
)

function changeType(type) {
  item.value.linkType = type
  const isTarget = MENU_PAGE_TARGETS.some((target) => target.url === item.value.url)
  if (type === MENU_LINK_TYPES.page && !isTarget) item.value.url = MENU_PAGE_TARGETS[0].url
}

function fieldName(suffix) {
  return `${props.path.replaceAll('.', '-')}-${suffix}`
}
</script>

<template>
  <section class="menu-panel" :aria-labelledby="headingId">
    <header class="menu-panel__head">
      <h4 :id="headingId" class="menu-panel__title">{{ t('identity.menus.panelTitle') }}</h4>
      <ToggleSwitch
        v-model="item.isVisible"
        :name="fieldName('visible')"
        :label="t('identity.menus.visible')"
        :description="t('identity.menus.visibleHint')"
        :disabled="disabled"
      />
    </header>
    <div class="menu-panel__grid">
      <BaseInput
        v-model="item.label.ar"
        :name="fieldName('label-ar')"
        :label="t('settings.homepage.siteSettings.linkLabelAr')"
        :error="errorOf(`${path}.label.ar`)"
        :disabled="disabled"
      />
      <BaseInput
        v-model="item.label.en"
        :name="fieldName('label-en')"
        :label="t('settings.homepage.siteSettings.linkLabelEn')"
        input-dir="ltr"
        :disabled="disabled"
      />
    </div>
    <p v-if="isGroup" class="menu-panel__note">
      {{ t('identity.menus.groupNote', { count: item.children.length }) }}
    </p>
    <template v-else>
      <AppSelect
        :model-value="item.linkType"
        :name="fieldName('type')"
        :label="t('identity.menus.linkType')"
        :options="typeOptions"
        :disabled="disabled"
        @update:model-value="changeType"
      />
      <AppSelect
        v-if="item.linkType === MENU_LINK_TYPES.page"
        v-model="item.url"
        :name="fieldName('page')"
        :label="t('identity.menus.page')"
        :options="targetOptions"
        :error="errorOf(`${path}.url`)"
        :disabled="disabled"
      />
      <BaseInput
        v-else
        v-model="item.url"
        :name="fieldName('url')"
        :label="t('settings.homepage.siteSettings.url')"
        :hint="t('settings.homepage.siteSettings.urlHint')"
        :error="errorOf(`${path}.url`)"
        input-dir="ltr"
        :disabled="disabled"
      />
    </template>
    <footer class="menu-panel__foot">
      <BaseButton
        variant="ghost"
        size="sm"
        icon="trash"
        :disabled="disabled"
        @click="emit('remove')"
      >
        {{ t('identity.menus.remove') }}
      </BaseButton>
    </footer>
  </section>
</template>
