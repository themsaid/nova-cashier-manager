Nova.booting((Vue, router) => {
    router.addRoutes([
        {
            name: 'cashier-tool',
            path: '/cashier-tool',
            component: require('./components/Home'),
        },

        {
            name: 'cashier-tool-user',
            path: '/cashier-tool/user/:userId',
            component: require('./components/UserDetails'),
            props: true
        },
    ])
})
