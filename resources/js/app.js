// resources/js/app.js
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import App from './App.vue'
import router from './router'

createApp(App).use(createPinia()).use(router).use(VueApexCharts).mount('#app')
