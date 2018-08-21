<?php

namespace Themsaid\CashierTool;

use Illuminate\Http\Request;
use Stripe\Plan;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Dispute;
use Illuminate\Support\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Routing\Controller;
use Stripe\Subscription as StripeSubscription;

class CashierToolController extends Controller
{
    /**
     * @var string
     */
    public $stripeModel;

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

            return $next($request);
        });
    }

    /**
     * Return the user response.
     *
     * @param  int $billableId
     * @return \Illuminate\Http\Response
     */
    public function user($billableId)
    {
        $billable = (new $this->stripeModel)->find($billableId);

        $subscription = $billable->subscription();

        $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_id);

        return [
            'user' => $billable->toArray(),
            'cards' => $this->formatCards($billable->cards(), $billable->defaultCard()->id),
            'invoices' => $this->formatInvoices($billable->invoicesIncludingPending()),
            'charges' => $this->formatCharges($billable->asStripeCustomer()->charges()),
            'subscription' => $this->formatSubscription($subscription, $stripeSubscription),
            'plans' => $this->formatPlans(Plan::all(['limit' => 100])),
        ];
    }

    /**
     * Cancel the given subscription.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $billableId
     * @return \Illuminate\Http\Response
     */
    public function cancelSubscription(Request $request, $billableId)
    {
        $billable = (new $this->stripeModel)->find($billableId);

        if ($request->input('now')) {
            $billable->subscription()->cancelNow();
        } else {
            $billable->subscription()->cancel();
        }
    }

    /**
     * Update the given subscription.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $billableId
     * @return \Illuminate\Http\Response
     */
    public function updateSubscription(Request $request, $billableId)
    {
        $billable = (new $this->stripeModel)->find($billableId);

        $billable->subscription()->swap($request->input('plan'));
    }

    /**
     * Resume the given subscription.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $billableId
     * @param  int $subscriptionId
     * @return \Illuminate\Http\Response
     */
    public function resumeSubscription(Request $request, $billableId)
    {
        $billable = (new $this->stripeModel)->find($billableId);

        $billable->subscription()->resume();
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
    function formatSubscription($subscription, $stripeSubscription)
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
    private function formatInvoices($invoices)
    {
        return collect($invoices)->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'total' => $invoice->total,
                'attempted' => $invoice->attempted,
                'charge_id' => $invoice->charge,
                'currency' => $invoice->currency,
                'period_start' => $invoice->period_start ? Carbon::createFromTimestamp($invoice->period_start)->toDateTimeString() : null,
                'period_end' => $invoice->period_end ? Carbon::createFromTimestamp($invoice->period_end)->toDateTimeString() : null,
            ];
        })->toArray();
    }

    /**
     * Format the charges collection.
     *
     * @param  array $charges
     * @return array
     */
    private function formatCharges($charges)
    {
        return collect($charges->data)->map(function ($charge) {
            return [
                'id' => $charge->id,
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
        })->toArray();
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
                'price' => $plan->amount,
                'interval' => $plan->interval,
                'currency' => $plan->currency,
                'interval_count' => $plan->interval_count,
            ];
        })->toArray();
    }
}
