import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'

export function withSetup(composable, { locale = 'en' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  let api
  const wrapper = mount(
    defineComponent({
      setup() {
        api = composable()
        return () => null
      },
    }),
    { global: { plugins: globals.plugins } },
  )
  return { api, wrapper, globals }
}
