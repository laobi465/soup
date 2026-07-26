import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import 'element-plus/dist/index.css'
import zhCn from 'element-plus/es/locale/lang/zh-cn'

import App from './App.vue'
import router from './router'
import { permissionDirective } from './directives/permission'

import '@/styles/variables.scss'
import '@/styles/common.scss'
import '@/styles/element-ui.scss'
import './style.css'

import DataTable from '@/components/DataTable/index.vue'
import FormModal from '@/components/FormModal/index.vue'
import ConfirmDialog from '@/components/ConfirmDialog/index.vue'
import EmptyState from '@/components/EmptyState/index.vue'
import StatusTag from '@/components/StatusTag/index.vue'
import CopyText from '@/components/CopyText/index.vue'
import CountDown from '@/components/CountDown/index.vue'

const app = createApp(App)

for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

app.component('DataTable', DataTable)
app.component('FormModal', FormModal)
app.component('ConfirmDialog', ConfirmDialog)
app.component('EmptyState', EmptyState)
app.component('StatusTag', StatusTag)
app.component('CopyText', CopyText)
app.component('CountDown', CountDown)

app.directive('permission', permissionDirective)

app.use(createPinia())
app.use(router)
app.use(ElementPlus, { locale: zhCn })

app.mount('#app')
