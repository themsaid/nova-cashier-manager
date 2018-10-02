Nova.booting((Vue, router) => {
    Vue.component('cashier-tool', require('./components/ResourceTool'));
    
    router.addRoutes([
        {
            name: 'cashier-tool-manage-subscription',
            path: '/cashier-tool/manage-subscription/:resourceId',
            component: require('./components/SubscriptionDetails'),
            props: true
        },
    ])
})
