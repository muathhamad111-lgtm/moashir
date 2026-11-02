import '@/styles/main.scss'

import { createApp } from 'vue'
import App from '@/App.vue'
import { router } from '@/router'
import { installPlugins } from '@/plugins'
import { enableMocking } from '@/mocks'
import { reveal } from '@/directives/reveal'

async function bootstrap() {
  await enableMocking()
  const app = createApp(App)
  installPlugins(app, router)
  app.directive('reveal', reveal)
  app.mount('#app')
}

bootstrap()
