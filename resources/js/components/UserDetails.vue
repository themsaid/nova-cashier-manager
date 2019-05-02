<script type="text/ecmascript-6">
    export default {
        props: ['userId'],


        data(){
            return {
                loading: true,
                user: null,
                subscription: null,
                cards: [],
                invoices: [],
                charges: [],
                plans: [],
                newPlan: '',
            }
        },


        mounted() {
            this.loadUserData();
        },


        methods: {
            /**
             * Load the user data.
             */
            loadUserData(){
                Nova.request().get(`/nova-cashier-tool-api/user/${this.userId}`)
                        .then(response => {
                            this.user = response.data.user;
                            this.subscription = response.data.subscription;
                            this.cards = response.data.cards;
                            this.invoices = response.data.invoices;
                            this.charges = response.data.charges;
                            this.plans = response.data.plans;

                            this.newPlan = response.data.subscription ? response.data.subscription.stripe_plan : null;

                            this.loading = false;
                        });
            },


            /**
             * Refund Charge.
             */
            refundCharge(chargeId){
                this.loading = true;

                Nova.request().post(`/nova-cashier-tool-api/user/${this.userId}/refund/${chargeId}`)
                        .then(response => {
                            this.$toasted.show("Refunded successfully!", {type: "success"});

                            this.loadUserData();
                        })
                        .catch(errors => {
                            this.$toasted.show(errors.response.data.message, {type: "error"});
                        })
            },


            /**
             * Cancel subscription.
             */
            cancelSubscription(){
                this.loading = true;

                Nova.request().post(`/nova-cashier-tool-api/user/${this.userId}/cancel`)
                        .then(response => {
                            this.$toasted.show("Cancelled successfully!", {type: "success"});

                            this.loadUserData();
                        })
                        .catch(errors => {
                            this.$toasted.show(errors.response.data.message, {type: "error"});
                        })
            },


            /**
             * Resume subscription.
             */
            resumeSubscription(){
                this.loading = true;

                Nova.request().post(`/nova-cashier-tool-api/user/${this.userId}/resume`)
                        .then(response => {
                            this.$toasted.show("Resumed successfully!", {type: "success"});

                            this.loadUserData();
                        })
                        .catch(errors => {
                            this.$toasted.show(errors.response.data.message, {type: "error"});
                        })
            },


            /**
             * Update subscription.
             */
            updateSubscription(){
                this.loading = true;

                Nova.request().post(`/nova-cashier-tool-api/user/${this.userId}/update`, {plan: this.newPlan})
                        .then(response => {
                            this.$toasted.show("Updated successfully!", {type: "success"});

                            this.loadUserData();
                        })
                        .catch(errors => {
                            this.$toasted.show(errors.response.data.message, {type: "error"});
                        })
            }
        }
    }
</script>

<template>
    <div>
        <heading class="mb-6">Cashier Manager</heading>

        <card class="bg-90 flex flex-col items-center justify-center" style="min-height: 300px" v-if="loading">
            <svg class="spin fill-80 mb-6" width="69" height="72" viewBox="0 0 23 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M20.12 20.455A12.184 12.184 0 0 1 11.5 24a12.18 12.18 0 0 1-9.333-4.319c4.772 3.933 11.88 3.687 16.36-.738a7.571 7.571 0 0 0 0-10.8c-3.018-2.982-7.912-2.982-10.931 0a3.245 3.245 0 0 0 0 4.628 3.342 3.342 0 0 0 4.685 0 1.114 1.114 0 0 1 1.561 0 1.082 1.082 0 0 1 0 1.543 5.57 5.57 0 0 1-7.808 0 5.408 5.408 0 0 1 0-7.714c3.881-3.834 10.174-3.834 14.055 0a9.734 9.734 0 0 1 .03 13.855zM4.472 5.057a7.571 7.571 0 0 0 0 10.8c3.018 2.982 7.912 2.982 10.931 0a3.245 3.245 0 0 0 0-4.628 3.342 3.342 0 0 0-4.685 0 1.114 1.114 0 0 1-1.561 0 1.082 1.082 0 0 1 0-1.543 5.57 5.57 0 0 1 7.808 0 5.408 5.408 0 0 1 0 7.714c-3.881 3.834-10.174 3.834-14.055 0a9.734 9.734 0 0 1-.015-13.87C5.096 1.35 8.138 0 11.5 0c3.75 0 7.105 1.68 9.333 4.319C16.06.386 8.953.632 4.473 5.057z" fill-rule="evenodd"/>
            </svg>
            <h1 class="text-white text-4xl text-90 font-light mb-6">Loading...</h1>
            <p class="text-white-50% text-lg">
                Fetching subscription information from Stripe. Might take a few moments.
            </p>
        </card>

        <div class="card mb-6 py-3 px-6" v-if="!loading && !subscription">
            <p class="text-90">User has no subscription.</p>
        </div>

        <div class="card mb-6 py-3 px-6" v-if="!loading && subscription">
            <div class="flex border-b border-40">
                <div class="w-1/4 py-4"><h4 class="font-normal text-80">Customer</h4></div>
                <div class="w-3/4 py-4"><p class="text-90">{{user.name}} ({{user.email}})</p></div>
            </div>

            <div class="flex border-b border-40" v-if="subscription">
                <div class="w-1/4 py-4"><h4 class="font-normal text-80">Created</h4></div>
                <div class="w-3/4 py-4"><p class="text-90">{{subscription.created_at}}</p></div>
            </div>

            <div class="flex border-b border-40" v-if="subscription">
                <div class="w-1/4 py-4"><h4 class="font-normal text-80">Plan</h4></div>
                <div class="w-3/4 py-4">
                    <select v-model="newPlan" class="form-control form-select">
                        <option value="" disabled="disabled" selected="selected">Choose New Plan</option>
                        <option :value="plan.id" v-for="plan in plans">
                            {{plan.id}} ({{plan.price / 100}} {{plan.currency}} / {{plan.interval}})
                        </option>
                    </select>

                    <button class="btn btn-sm btn-outline" v-if="newPlan && newPlan != subscription.stripe_plan && subscription.active && !subscription.cancel_at_period_end"
                            v-on:click="updateSubscription()">
                        Update Plan
                    </button>
                </div>
            </div>

            <div class="flex border-b border-40" v-if="subscription">
                <div class="w-1/4 py-4"><h4 class="font-normal text-80">Amount</h4></div>
                <div class="w-3/4 py-4"><p class="text-90">{{subscription.plan_amount / 100}} ({{subscription.plan_currency}}) / {{subscription.plan_interval}}</p></div>
            </div>

            <div class="flex border-b border-40" v-if="subscription">
                <div class="w-1/4 py-4"><h4 class="font-normal text-80">Billing Period</h4></div>
                <div class="w-3/4 py-4"><p class="text-90">{{subscription.current_period_start}} => {{subscription.current_period_end}}</p></div>
            </div>

            <div class="flex border-b border-40 remove-bottom-border" v-if="subscription">
                <div class="w-1/4 py-4"><h4 class="font-normal text-80">Status</h4></div>
                <div class="w-3/4 py-4">
                    <p class="text-90">
                        <span v-if="subscription.on_grace_period">On Grace Period</span>
                        <span v-if="subscription.cancelled || subscription.cancel_at_period_end" class="text-danger">Cancelled</span>
                        <span v-if="subscription.active && !subscription.cancelled && !subscription.cancel_at_period_end">Active</span>

                        <button class="btn btn-sm btn-outline" v-if="subscription.active && !subscription.cancelled && !subscription.cancel_at_period_end"
                                v-on:click="cancelSubscription()">
                            Cancel
                        </button>

                        <button class="btn btn-sm btn-outline" v-if="subscription.active && subscription.cancel_at_period_end"
                                v-on:click="resumeSubscription()">
                            Resume
                        </button>
                    </p>
                </div>
            </div>
        </div>

        <div class="card mb-6 relative" v-if="!loading && invoices && invoices.length">
            <div class="py-3 flex items-center border-b border-50">
                <div class="px-3">
                    Invoices
                </div>
            </div> <!---->
            <div class="overflow-hidden overflow-x-auto relative">
                <table cellpadding="0" cellspacing="0" data-testid="resource-table" class="table w-full">
                    <thead>
                    <tr>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">Amount</span></th>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">From</span></th>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">To</span></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="invoice in invoices">
                        <td><span class="whitespace-no-wrap text-left">{{invoice.total / 100}} {{invoice.currency}}</span></td>
                        <td><span class="whitespace-no-wrap text-left">{{invoice.period_start}}</span></td>
                        <td><span class="whitespace-no-wrap text-left">{{invoice.period_end}}</span></td>
                        <td class="text-right">
                            <button class="btn btn-sm btn-outline" v-on:click="refundCharge(invoice.charge_id)" v-if="invoice.total > 0">
                                Refund
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-6 relative" v-if="!loading && charges && charges.length">
            <div class="py-3 flex items-center border-b border-50">
                <div class="px-3">
                    Charges
                </div>
            </div> <!---->
            <div class="overflow-hidden overflow-x-auto relative">
                <table cellpadding="0" cellspacing="0" data-testid="resource-table" class="table w-full">
                    <thead>
                    <tr>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">Amount</span></th>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">Status</span></th>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">Created</span></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="charge in charges">
                        <td><span class="whitespace-no-wrap text-left">{{charge.amount / 100}} {{charge.currency}}</span></td>
                        <td>
                            <span class="whitespace-no-wrap text-left" v-if="charge.amount_refunded">{{charge.amount_refunded / 100}} {{charge.currency}} Refunded</span>
                            <span class="whitespace-no-wrap text-left text-success" v-if="charge.captured">Successfull</span>
                            <span class="whitespace-no-wrap text-left text-danger" v-if="charge.failure_message">{{charge.failure_message}}</span>
                        </td>
                        <td><span class="whitespace-no-wrap text-left">{{charge.created}}</span></td>
                        <td class="text-right">
                            <button class="btn btn-sm btn-outline" v-on:click="refundCharge(charge.id)">
                                Refund
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-6 relative" v-if="!loading && cards && cards.length">
            <div class="py-3 flex items-center border-b border-50">
                <div class="px-3">
                    Cards
                </div>
            </div> <!---->
            <div class="overflow-hidden overflow-x-auto relative">
                <table cellpadding="0" cellspacing="0" data-testid="resource-table" class="table w-full">
                    <thead>
                    <tr>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">Brand</span></th>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">Number</span></th>
                        <th class="text-left"><span class="cursor-pointer inline-flex items-center">Expiration</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="card in cards">
                        <td><span class="whitespace-no-wrap text-left">{{card.brand}}</span></td>
                        <td><span class="whitespace-no-wrap text-left">**********{{card.last4}}</span></td>
                        <td><span class="whitespace-no-wrap text-left">{{card.exp_month}}/{{card.exp_year}}</span></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style>
    /* Scopes Styles */
</style>
