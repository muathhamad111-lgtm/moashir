<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  colorKey: { type: String, required: true },
  value: { type: String, required: true },
  autoValue: { type: String, default: '' },
  check: { type: Object, default: null },
  isCustom: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['override', 'restore-auto'])
const { t, n } = useI18n()

const name = computed(() => t(`branding.derived.colors.${props.colorKey}.name`))
const passes = computed(() => props.check?.passes ?? true)
const ratio = computed(() =>
  props.check
    ? t('branding.contrast.ratio', { ratio: formatNumber(n, props.check.ratio, 'money') })
    : '',
)
</script>

<template>
  <tr class="brand-derived__row" :class="{ 'brand-derived__row--failing': !passes }">
    <th scope="row">
      <div class="brand-derived__name">
        <span class="brand-derived__swatch" :data-color="colorKey" aria-hidden="true">Aa</span>
        <span class="brand-derived__label">
          <span>{{ name }}</span>
          <span class="brand-derived__use">{{ t(`branding.derived.colors.${colorKey}.use`) }}</span>
          <bdi class="brand-derived__hex u-ltr">{{ value }}</bdi>
          <span v-if="isCustom" class="brand-derived__auto">
            {{ t('branding.derived.autoValue') }} <bdi class="u-ltr">{{ autoValue }}</bdi>
          </span>
        </span>
      </div>
    </th>
    <td>
      <span
        class="brand-derived__ratio"
        :class="`brand-derived__ratio--${passes ? 'pass' : 'fail'}`"
      >
        <AppIcon :name="passes ? 'check' : 'x'" />
        <bdi class="u-ltr">{{ ratio }}</bdi>
        <span class="brand-derived__sr">
          {{ t(passes ? 'branding.derived.passes' : 'branding.derived.fails') }}
        </span>
      </span>
    </td>
    <td>
      <div class="brand-derived__state">
        <Badge :variant="isCustom ? 'info' : 'muted'">
          {{ t(isCustom ? 'branding.derived.custom' : 'branding.derived.auto') }}
        </Badge>
        <div class="brand-derived__actions">
          <template v-if="isCustom">
            <input
              class="brand-derived__picker"
              type="color"
              :value="value"
              :disabled="disabled"
              :aria-label="t('branding.derived.pick', { name })"
              @input="emit('override', $event.target.value)"
            />
            <BaseButton
              variant="ghost"
              size="sm"
              :disabled="disabled"
              @click="emit('restore-auto')"
            >
              {{ t('branding.derived.restoreAuto') }}
              <span class="brand-derived__sr">{{ name }}</span>
            </BaseButton>
          </template>
          <BaseButton
            v-else
            variant="ghost"
            size="sm"
            :disabled="disabled"
            @click="emit('override', value)"
          >
            {{ t('branding.derived.override') }}
            <span class="brand-derived__sr">{{ name }}</span>
          </BaseButton>
        </div>
      </div>
    </td>
  </tr>
</template>
