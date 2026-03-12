import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import { definePreset } from '@primeuix/themes'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import 'primeicons/primeicons.css'
import './assets/colors.css'

import App from './App.vue'
import router from './router'

const YellowPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#fffdf0',
            100: '#fef9c3',
            200: '#fef08a',
            300: '#fdedad',
            400: '#ffea95',
            500: '#fee47b',
            600: '#eab308',
            700: '#a16207',
            800: '#854d0e',
            900: '#713f12',
            950: '#422006'
        },
        colorScheme: {
            light: {
                primary: {
                    color: '#ffea95',
                    contrastColor: '#000000',
                    hoverColor: '#fee47b',
                    activeColor: '#fde047'
                },
                highlight: {
                    background: '#ffea95',
                    focusBackground: '#fee47b',
                    color: '#000000',
                    focusColor: '#000000'
                }
            }
        }
    }
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
    theme: {
        preset: YellowPreset,
        options: {
            darkModeSelector: '.dark-mode',
            cssLayer: false
        }
    },
    ripple: true,
    locale: {
        firstDayOfWeek: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesShort: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        monthNamesShort: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
        today: 'Heute',
        clear: 'Löschen',
        accept: 'Ja',
        reject: 'Nein',
        choose: 'Auswählen',
        upload: 'Hochladen',
        cancel: 'Abbrechen',
        pending: 'Ausstehend',
        fileSizeTypes: ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
    }
})
app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)

app.mount('#app')
