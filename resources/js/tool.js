Nova.booting((Vue, router) => {
    Vue.component('cashier-tool', require('./components/ResourceTool'));
    
    router.addRoutes([
        {
            name: 'cashier-tool-user',
            path: '/cashier-tool/user/:userId/subscriptions/:subscriptionId',
            component: require('./components/UserDetails'),
            props: true
        },
    ])
})
