import {h} from 'vue'
import DefaultTheme from 'vitepress/theme'
import type {Theme} from 'vitepress'
import SignOut from './SignOut.vue'
import './custom.css'

export default {
    extends: DefaultTheme,
    Layout: () => h(DefaultTheme.Layout, null, {
        'nav-bar-content-after': () => h(SignOut),
    }),
} satisfies Theme
