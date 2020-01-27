<?php

namespace Themsaid\CashierTool;

use Stripe\Plan;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Dispute;
use Stripe\PaymentIntent;
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
     * @param int $billableId
     *
     * @return array
     */
    public function user($billableId)
    {
        /** @var \Laravel\Cashier\Billable|\App\Models\User $billable */
        $billable = (new $this->stripeModel())->find($billableId);

        $subscription = $billable->subscription($this->subscriptionName);

        if (!$subscription) {
            return [
                'subscription' => null,
            ];
        }

        $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_id);

        return [
            'user' => $billable->toArray(),
            'cards' => request('brief') ? [] : $this->formatPaymentMethods($billable->paymentMethods(), $billable->defaultPaymentMethod()->id),
            'invoices' => request('brief') ? [] : $this->formatInvoices($billable->invoicesIncludingPending()),
            'charges' => request('brief') ? [] : $this->formatPaymentIntents(PaymentIntent::all(['customer' => $billable->asStripeCustomer()->id])),
            'subscription' => $this->formatSubscription($subscription, $stripeSubscription),
            'plans' => request('brief') ? [] : $this->formatPlans(Plan::all(['limit' => 100])),
        ];
    }

    /**
     * Cancel the given subscription.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $billableId
     */
    public function cancelSubscription(Request $request, $billableId)
    {
        /** @var \Laravel\Cashier\Billable|\App\Models\User $billable */
        $billable = (new $this->stripeModel())->find($billableId);

        if ($request->input('now')) {
            $billable->subscription($this->subscriptionName)->cancelNow();
        } else {
            $billable->subscription($this->subscriptionName)->cancel();
        }
    }

    /**
     * Update the given subscription.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $billableId
     */
    public function updateSubscription(Request $request, $billableId)
    {
        /** @var \Laravel\Cashier\Billable|\App\Models\User $billable */
        $billable = (new $this->stripeModel())->find($billableId);

        $billable->subscription($this->subscriptionName)->swap($request->input('plan'));
    }

    /**
     * Resume the given subscription.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $billableId
     */
    public function resumeSubscription(Request $request, $billableId)
    {
        /** @var \Laravel\Cashier\Billable|\App\Models\User $billable */
        $billable = (new $this->stripeModel())->find($billableId);

        $billable->subscription($this->subscriptionName)->resume();
    }

    /**
     * Refund the given charge.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $billableId
     * @param string                   $stripeChargeId
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
     * @param \Laravel\Cashier\Subscription $subscription
     * @param \Stripe\Subscription          $stripeSubscription
     *
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
            'charges_automatically' => $stripeSubscription->collection_method == 'charge_automatically',
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
     * @param array|\Illuminate\Support\Collection $paymentMethods
     * @param string|null              $defaultPaymentMethodId
     *
     * @return array
     */
    private function formatPaymentMethods($paymentMethods, $defaultPaymentMethodId = null)
    {
        return collect($paymentMethods)->map(function ($paymentMethod) use ($defaultPaymentMethodId) {
            /* @var \Stripe\PaymentMethod $paymentMethod */
            return [
                'id' => $paymentMethod->id,
                'is_default' => $paymentMethod->id == $defaultPaymentMethodId,
                'name' => $paymentMethod->card->name,
                'last4' => $paymentMethod->card->last4,
                'country' => $paymentMethod->card->country,
                'brand' => $paymentMethod->card->brand,
                'exp_month' => $paymentMethod->card->exp_month,
                'exp_year' => $paymentMethod->card->exp_year,
            ];
        })->toArray();
    }

    /**
     * Format the invoices collection.
     *
     * @param array|\Illuminate\Support\Collection $invoices
     *
     * @return array
     */
    private function formatInvoices($invoices)
    {
        return collect($invoices)->map(function ($invoice) {
            /* @var \Stripe\Invoice $invoice */
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
     * @param \Stripe\Collection $paymentIntents
     *
     * @return array
     */
    private function formatPaymentIntents($paymentIntents)
    {
        $charges = collect([]);
        collect($paymentIntents->data)->each(function ($paymentIntent) use ($charges) {
            /** @var \Stripe\PaymentIntent $paymentIntent */
            collect($paymentIntent->charges->data)->each(function ($charge) use ($charges) {
                /** @var \Stripe\Charge $charge */
                $charges->push([
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
                ]);
            });
        });

        return $charges->toArray();
    }

    /**
     * Format the plans collection.
     *
     * @param \Stripe\Collection $plans
     *
     * @return array
     */
    private function formatPlans($plans)
    {
        return collect($plans->data)->map(function ($plan) {
            /* @var Plan $plan */
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
