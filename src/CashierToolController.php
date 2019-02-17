<?php

namespace Themsaid\CashierTool;

use Stripe\Plan;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Dispute;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Routing\Controller;
use Stripe\Subscription as StripeSubscription;

class CashierToolController extends Controller
{
    /**
     * The model used by Stripe.
     *
     * @var string
     */
    public $stripeModel;

    /**
     * The subscription name.
     *
     * @var string
     */
    public $subscriptionName;

    /**
     * Create a new controller instance.
     *
     * @param \Illuminate\Config\Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->middleware(function ($request, $next) use ($config) {
            Stripe::setApiKey($config->get('services.stripe.secret'));

            $this->stripeModel = $config->get('services.stripe.model');

            $this->subscriptionName = $config->get('nova-cashier-manager.subscription_name');

            return $next($request);
        });
    }

    /**
     * Return the user response.
     *
     * @param  int $billableId
     * @param  bool $brief
     * @return \Illuminate\Http\Response
     */
    public function user($billableId, $subscriptionId = null)
    {
        // Get user
        $billable = (new $this->stripeModel)->find($billableId);

        // Get subscription(s)
        if (is_null($subscriptionId)) {
            $subscriptions = $billable->subscriptions()->get();
        } else {
            $subscriptions = $billable->subscriptions()->where('id', $subscriptionId)->get();
        }

        // echo print_r($subscriptions, true); exit;

        if (!$subscriptions) {
            return [
                'subscriptions' => [],
            ];
        }

        // Get ALL subscriptions
        $formattedSubscriptions = [];
        foreach($subscriptions as $cur_subscription) {
            $stripeSubscription = StripeSubscription::retrieve($cur_subscription->stripe_id);
            $formattedSubscriptions[] = $this->formatSubscription($cur_subscription, $stripeSubscription);
        }

        // Get invoices
        $invoices = request('brief') ? [] : $this->formatInvoices($billable->invoicesIncludingPending(), array_column($subscriptions->toArray(), 'stripe_id'));

        // Return data
        return [
            'user' => $billable->toArray(),
            'cards' => request('brief') ? [] : $this->formatCards($billable->cards(), optional($billable->defaultCard())->id),
            'invoices' => $invoices,
            'charges' => request('brief') ? [] : $this->formatCharges($billable->asStripeCustomer()->charges(), array_column($invoices, 'id')),
            'subscriptions' => $formattedSubscriptions,
            'plans' => request('brief') ? [] : $this->formatPlans(Plan::all(['limit' => 100])),
        ];
    }

    /**
     * Cancel the given subscription.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $billableId
     * @return \Illuminate\Http\Response
     */
    public function cancelSubscription(Request $request, $billableId, $subscriptionId)
    {
        $billable = (new $this->stripeModel)->find($billableId);

        $subscription = $billable->subscriptions()->find($subscriptionId);

        if ($request->input('now')) {
            return $billable->subscription($subscription->name)->cancelNow();
        } else {
            return $billable->subscription($subscription->name)->cancel();
        }
    }

    /**
     * Update the given subscription.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $billableId
     * @return \Illuminate\Http\Response
     */
    public function updateSubscription(Request $request, $billableId, $subscriptionId)
    {
        $billable = (new $this->stripeModel)->find($billableId);

        $subscription = $billable->subscriptions()->find($subscriptionId);

        $billable->subscription($subscription->name)->swap($request->input('plan'))->update([
            'name' => $request->input('plan')
        ]);
    }

    /**
     * Resume the given subscription.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $billableId
     * @param  int $subscriptionId
     * @return \Illuminate\Http\Response
     */
    public function resumeSubscription(Request $request, $billableId, $subscriptionId)
    {
        $billable = (new $this->stripeModel)->find($billableId);

        $subscription = $billable->subscriptions()->find($subscriptionId);

        $billable->subscription($subscription->name)->resume();
    }

    /**
     * Refund the given charge.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $billableId
     * @param  string $stripeChargeId
     * @return \Illuminate\Http\Response
     */
    public function refundCharge(Request $request, $billableId, $stripeChargeId)
    {
        $refundParameters = ['charge' => $stripeChargeId];

        if ($request->input('amount')) {
            $refundParameters['amount'] = $request->input('amount');
        }

        if ($request->input('notes')) {
            $refundParameters['metadata'] = ['notes' => $request->input('notes')];
        }

        Refund::create($refundParameters);
    }

    /**
     * Format a a subscription object.
     *
     * @param  \Laravel\Cashier\Subscription $subscription
     * @param  \Stripe\Subscription $stripeSubscription
     * @return array
     */
    public function formatSubscription($subscription, $stripeSubscription)
    {
        return array_merge($subscription->toArray(), [
            'plan_amount' => $stripeSubscription->plan->amount,
            'plan_interval' => $stripeSubscription->plan->interval,
            'plan_currency' => $stripeSubscription->plan->currency,
            'plan' => $subscription->stripe_plan,
            'stripe_plan' => $stripeSubscription->plan->id,
            'ended' => $subscription->ended(),
            'cancelled' => $subscription->cancelled(),
            'active' => $subscription->active(),
            'on_trial' => $subscription->onTrial(),
            'on_grace_period' => $subscription->onGracePeriod(),
            'charges_automatically' => $stripeSubscription->billing == 'charge_automatically',
            'created_at' => $stripeSubscription->billing_cycle_anchor ? Carbon::createFromTimestamp($stripeSubscription->billing_cycle_anchor)->toDateTimeString() : null,
            'ended_at' => $stripeSubscription->ended_at ? Carbon::createFromTimestamp($stripeSubscription->ended_at)->toDateTimeString() : null,
            'current_period_start' => $stripeSubscription->current_period_start ? Carbon::createFromTimestamp($stripeSubscription->current_period_start)->toDateString() : null,
            'current_period_end' => $stripeSubscription->current_period_end ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)->toDateString() : null,
            'days_until_due' => $stripeSubscription->days_until_due,
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
            'canceled_at' => $stripeSubscription->canceled_at,
        ]);
    }

    /**
     * Format the cards collection.
     *
     * @param  array $cards
     * @param  null|int $defaultCardId
     * @return array
     */
    private function formatCards($cards, $defaultCardId = null)
    {
        return collect($cards)->map(function ($card) use ($defaultCardId) {
            return [
                'id' => $card->id,
                'is_default' => $card->id == $defaultCardId,
                'name' => $card->name,
                'last4' => $card->last4,
                'country' => $card->country,
                'brand' => $card->brand,
                'exp_month' => $card->exp_month,
                'exp_year' => $card->exp_year,
            ];
        })->toArray();
    }

    /**
     * Format the invoices collection.
     *
     * @param  array $invoices
     * @return array
     */
    private function formatInvoices($invoices, $subscription_ids = [])
    {
        return collect($invoices)->map(function ($invoice, $key) {
            return [
                'id' => $invoice->id,
                'subscription_id' => $invoice->subscription,
                'total' => $invoice->total,
                'attempted' => $invoice->attempted,
                'charge_id' => $invoice->charge,
                'currency' => $invoice->currency,
                'period_start' => $invoice->period_start ? Carbon::createFromTimestamp($invoice->period_start)->toDateTimeString() : null,
                'period_end' => $invoice->period_end ? Carbon::createFromTimestamp($invoice->period_end)->toDateTimeString() : null,
                'metadata' => $invoice->metadata ? $invoice->metadata : null,
            ];
        })->filter(function ($invoice, $key) use ($subscription_ids) {
            return $invoice != null && in_array($invoice['subscription_id'], $subscription_ids);
        })->values()->toArray();
    }

    /**
     * Format the charges collection.
     *
     * @param  array $charges
     * @return array
     */
    private function formatCharges($charges, $invoice_ids)
    {
        return collect($charges->data)->map(function ($charge) {
            return [
                'id' => $charge->id,
                'invoice' => $charge->invoice,
                'amount' => $charge->amount,
                'amount_refunded' => $charge->amount_refunded,
                'captured' => $charge->captured,
                'paid' => $charge->paid,
                'status' => $charge->status,
                'currency' => $charge->currency,
                'dispute' => $charge->dispute ? Dispute::retrieve($charge->dispute) : null,
                'failure_code' => $charge->failure_code,
                'failure_message' => $charge->failure_message,
                'created' => $charge->created ? Carbon::createFromTimestamp($charge->created)->toDateTimeString() : null,
            ];
        })->filter(function ($charge, $key) use ($invoice_ids) {
            return $charge != null && in_array($charge['invoice'], $invoice_ids);
        })->values()->toArray();
    }

    /**
     * Format the plans collection.
     *
     * @param  array $charges
     * @return array
     */
    private function formatPlans($plans)
    {
        return collect($plans->data)->map(function ($plan) {
            return [
                'id' => $plan->id,
                'nickname' => $plan->nickname,
                'price' => $plan->amount,
                'interval' => $plan->interval,
                'currency' => $plan->currency,
                'interval_count' => $plan->interval_count,
            ];
        })->toArray();
    }
}
