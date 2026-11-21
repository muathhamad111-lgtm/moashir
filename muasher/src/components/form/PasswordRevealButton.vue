<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  revealed: { type: Boolean, required: true },
  controls: { type: String, required: true },
  subject: {
    type: String,
    default: 'password',
    validator: (value) => ['password', 'secret'].includes(value),
  },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle'])
const { t } = useI18n()

const label = computed(() =>
  t(`components.passwordReveal.${props.subject}.${props.revealed ? 'hide' : 'show'}`),
)
</script>

<template>
  <button
    type="button"
    class="password-reveal"
    :aria-label="label"
    :aria-pressed="revealed ? 'true' : 'false'"
    :aria-controls="controls"
    :disabled="disabled"
    @mousedown.prevent
    @click="emit('toggle')"
  >
    <AppIcon :name="revealed ? 'eye-slash' : 'eye'" />
  </button>
</template>
