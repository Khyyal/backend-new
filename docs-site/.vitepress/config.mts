import {defineConfig, type DefaultTheme} from 'vitepress'

type Entry = [slug: string, title: string]

type Group = { title: string; base: string; items: Entry[] }

/**
 * Every page must be registered here, otherwise it is not reachable from the sidebar.
 * Keep the order aligned with the folder structure under docs-site/.
 */
const devGroups: Group[] = [
    {
        title: 'Getting started', base: 'dev/getting-started/', items: [
            ['installation', 'Installation'],
            ['configuration', 'Configuration'],
            ['running', 'Running the platform'],
        ],
    },
    {
        title: 'Architecture', base: 'dev/architecture/', items: [
            ['overview', 'Overview'],
            ['modules', 'Modules'],
            ['api-surfaces', 'API surfaces and guards'],
            ['octane', 'Octane and long-running workers'],
        ],
    },
    {
        title: 'Conventions', base: 'dev/conventions/', items: [
            ['coding', 'Coding conventions'],
            ['permissions', 'Roles and permissions'],
            ['testing', 'Testing'],
            ['documentation', 'Keeping these docs current'],
        ],
    },
    {
        title: 'Domain flows', base: 'dev/domain/', items: [
            ['centers', 'Centers'],
            ['services', 'Services'],
            ['reservations', 'Reservations'],
            ['purchase', 'Purchases and payments'],
            ['promotions', 'Promotions and coupons'],
            ['billing', 'Billing and subscriptions'],
            ['settlements', 'Settlements and payouts'],
            ['homepage', 'Homepage content'],
            ['clients', 'Clients'],
            ['notifications', 'Notifications'],
            ['analytics', 'Analytics'],
            ['exports', 'Exports'],
            ['search', 'Global search'],
            ['support', 'Support: OTP, devices and media'],
        ],
    },
    {
        title: 'API reference', base: 'dev/api/', items: [
            ['overview', 'Overview'],
            ['authentication', 'Authentication'],
        ],
    },
]

const userGroups: Group[] = [
    {
        title: 'Admin panel', base: 'user/admin/', items: [
            ['getting-around', 'Getting around'],
            ['centers', 'Centers'],
            ['clients', 'Clients'],
            ['promotions', 'Promotions, campaigns and coupons'],
            ['homepage', 'Homepage sections and banners'],
            ['notifications', 'Notification campaigns'],
            ['billing', 'Plans and subscriptions'],
            ['payments-payouts', 'Purchases, fees and payouts'],
            ['analytics', 'Analytics and exports'],
            ['users-roles', 'Users and roles'],
        ],
    },
    {
        title: 'Center dashboard', base: 'user/center/', items: [
            ['overview', 'Overview'],
            ['services', 'Managing services'],
            ['horses', 'Horses'],
            ['schedules-blocks', 'Schedules, periods and blocks'],
            ['reservations', 'Reservations'],
            ['purchases-payouts', 'Purchases, balance and payouts'],
            ['promotions', 'Promotions'],
            ['team', 'Team and permissions'],
        ],
    },
    {
        title: 'Client app', base: 'user/client/', items: [
            ['browsing', 'Browsing centers and services'],
            ['booking', 'Booking and paying'],
            ['packages-memberships', 'Packages and memberships'],
            ['offers', 'Offers and coupons'],
            ['account', 'Account, favorites and ratings'],
        ],
    },
]

function sidebar(groups: Group[], intro: Entry): DefaultTheme.SidebarItem[] {
    const [introSlug, introTitle] = intro

    return [
        {text: introTitle, link: `/${introSlug}`},
        ...groups.map((group) => ({
            text: group.title,
            collapsed: false,
            items: group.items.map(([slug, title]) => ({
                text: title,
                link: `/${group.base}${slug}`,
            })),
        })),
    ]
}

export default defineConfig({
    title: 'Khyyal Platform Docs',
    description: 'Developer and user documentation for the Khyyal platform.',
    lang: 'en',
    base: '/guide/',
    cleanUrls: true,
    outDir: '../storage/app/docs',
    lastUpdated: false,
    head: [['meta', {name: 'robots', content: 'noindex, nofollow'}]],
    themeConfig: {
        nav: [
            {text: 'Developer guide', link: '/dev/', activeMatch: '^/dev/'},
            {text: 'User guide', link: '/user/', activeMatch: '^/user/'},
            {text: 'Endpoint reference', link: '/docs', target: '_self'},
        ],
        sidebar: {
            '/dev/': sidebar(devGroups, ['dev/', 'Introduction']),
            '/user/': sidebar(userGroups, ['user/', 'Introduction']),
        },
        outline: {level: [2, 3], label: 'On this page'},
        search: {provider: 'local'},
    },
})
