<?php
namespace GiveNovac;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\DonationSummary;
use Give\Framework\Http\Response\Types\RedirectResponse;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\Contracts\WebhookNotificationsListener;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\PaymentComplete;
use Give\Framework\PaymentGateways\Commands\PaymentRefunded;
use Give\Framework\Support\Facades\Scripts\ScriptAsset;
use Give\Framework\PaymentGateways\Log\PaymentGatewayLog;
use Give\Framework\Support\ValueObjects\Money;
use Give\Session\SessionDonation\DonationAccessor;
use Give\Log\Log;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

/**
 * Novac Gateway (Hosted Checkout)
 *
 * Uses GiveWP’s modern gateway framework:
 * - Offsite redirect via method routes (generateSecureGatewayRouteUrl)
 * - Webhook listener via WebhookNotificationsListener
 */
class Give_Novac_Gateway extends PaymentGateway implements WebhookNotificationsListener
{
    public $routeMethods = [
        'webhookNotificationsListener',
        'handleReturn'
    ];
    public $secureRouteMethods = [
//		'handleReturn',
        'handleCancelledPaymentReturn',
        'return'
    ];
    /** ----------------------------------------------------------------
     * Identity
     * --------------------------------------------------------------- */
    public static function id(): string
    {
        return 'novac';
    }

    public function getId(): string
    {
        return self::id();
    }

    /**
     * @inheritDoc
     */
    public function createPayment(Donation $donation, $gatewayData) {
        try {
            return $this->purchase($donation, $gatewayData);
//			throw new PaymentGatewayException( __(' Currency not supported by selected payment option.', 'novac-give' ) );
        } catch ( PaymentGatewayException $e ) {
            $donation->status = DonationStatus::FAILED();
            $donation->save();

            DonationNote::create( [
                'donationId' => $donation->id,
                'content'    => sprintf(
                /* translators: %s: Donation reason */
                    esc_html__( 'Donation failed. Reason: %s', 'novac-give' ),
                    esc_html( $e->getMessage() )
                ),
            ] );

            throw new PaymentGatewayException( esc_html( $e->getMessage() ) );
        }

    }

    public function getName(): string
    {
        return __( 'Novac', 'novac-give' );
    }

    public function getPaymentMethodLabel(): string
    {
        return __( 'Novac', 'novac-give' );
    }

    public function getTransactionUrl(Donation $donation): ?string
    {
        $checkoutUrl = give_get_payment_meta($donation->id, '_give_novac_checkout_url', true);
        return $checkoutUrl ?: null;
    }

    public function enqueueScript(int $formId)
    {
        // Load Novac’s JS SDK
        wp_enqueue_script(
            'novac-give-js',
            GIVE_NOVAC_URL . 'assets/js/gateway.js', // Confirm latest CDN path in docs
            [],
            GIVE_NOVAC_VER,
            true
        );

        // Pass localized config to JS
        wp_localize_script('novac-give-js', 'giveNovacSettings', $this->formSettings($formId));
    }

    public function formSettings(int $formId): array
    {
        return [
            'message' => __( 'You will be redirected to Novac to complete the donation!', 'novac-give' ),
        ];
//		return [
//			'publicKey'   => give_get_option('give_novac_public_key'),
//			'currency'    => give_get_currency(),
//			'mode'        => give_get_option('give_novac_mode'),
//			'formId'      => $formId,
//			'ajaxUrl'     => $this->generateGatewayRouteUrl('tokenize'),
//		];
    }

    /**
     * @inheritDoc
     */
    public function getLegacyFormFieldMarkup( int $formId, array $args ): string
    {
        return "<div class=\"novac-give-help-text\">
                    <p>" . esc_html__( 'You will be redirected to Novac to complete the donation!', 'novac-give' ) . "</p>
                </div>";
    }


    /** ----------------------------------------------------------------
     * Form v3 support (optional; no custom JS needed for redirect)
     * --------------------------------------------------------------- */
    public function supportsFormVersions(): array
    {
        // We support v3 by default; no enqueue needed for an offsite redirect.
        return [3];
    }

    /** ----------------------------------------------------------------
     * Entry point: called by Give when donor submits with this gateway
     * --------------------------------------------------------------- */
    public function purchase(Donation $donation, $gatewayData)
    {
        // 1) Create a pending donation row has been handled by Give pre-purchase.
        // 2) Initialize Novac transaction and redirect donor to authorization_url.

        $init = $this->createNovacTransaction($donation);

        if (is_wp_error($init)) {
            $secret = trim((string) give_get_option('give_novac_secret_key'));
            PaymentGatewayLog::error(
                'Novac – Initialize Errorx',
                [
                    'message' => (string) $init->get_error_code(),
                    'donation_id' => $donation->id
                ]
            );
            $errorMessage = $init->get_error_message();
            throw new PaymentGatewayException(esc_html($errorMessage));
        }

        $authUrl = $init['authorization_url'] ?? '';
        if (!$authUrl) {
            PaymentGatewayLog::error(
                'Novac – Missing authorization_url',
                [
                    'response' => $init,
                    'donation_id' => $donation->id
                ]
            );
            throw new PaymentGatewayException(esc_html__('Payment link not received from Novac.', 'novac-give' ));
        }

        return new RedirectOffsite($authUrl);
    }

    /** ----------------------------------------------------------------
     * Secure return route (optional)
     * You can link this as Novac callback/return in the init payload.
     * --------------------------------------------------------------- */

    /**
     * Handle return from Novac after payment
     *
     * @since 1.0.0
     */
    public function handleReturn(array $queryParams): RedirectResponse
    {
        // The donation ID comes from the route signature
        $donationId = absint($queryParams['donation-id'] ?? 0);
        $reference = sanitize_text_field($queryParams['reference'] ?? '');

        if (empty($donationId) || empty($reference)) {
            PaymentGatewayLog::error('Novac Missing Parameters', [
                'params' => $queryParams
            ]);
            return new RedirectResponse(give_get_failed_transaction_uri());
        }

        $donation = Donation::find($donationId);
        if (!$donation) {
            PaymentGatewayLog::error('Novac Transaction Not Found', [
                'donation_id' => $donationId,
                'reference' => $reference,
            ]);
            return new RedirectResponse(give_get_failed_transaction_uri());
        }

        // If already completed, just redirect to success
        if (give_is_donation_completed($donationId)) {
            return new RedirectResponse(give_get_success_page_uri());
        }

        PaymentGatewayLog::error('Verifying Novac Return', [
            'donation_id' => $donationId,
            'reference' => $reference,
        ]);


        // Verify the transaction with Novac API
        $verify = $this->verifyNovac($reference);

        if (is_wp_error($verify)) {
            PaymentGatewayLog::error('Novac Return Verification Failed', [
                'donation_id' => $donationId,
                'reference' => $reference,
                'error' => $verify->get_error_message()
            ]);

            $donation->status = DonationStatus::FAILED();
            $donation->save();

            DonationNote::create([
                'donationId' => $donation->id,
                'content' => sprintf(
                /* translators: %s: Error message from Novac */
                    __('Payment verification failed: %s', 'novac-give'),
                    $verify->get_error_message()
                ),
            ]);

            return new RedirectResponse(give_get_failed_transaction_uri());
        }

        // Check the verified status from Novac
        $verifiedStatus = strtolower($verify['data']['status'] ?? '');

        PaymentGatewayLog::info('Novac Return Verification', $verify);

        if ($verifiedStatus === 'successful') {
            $donation->status = DonationStatus::COMPLETE();
            $donation->gatewayTransactionId = $reference;
            $donation->save();

            DonationNote::create([
                'donationId' => $donation->id,
                'content' => __('Payment completed and verified via Novac.', 'novac-give'),
            ]);

            return new RedirectResponse(give_get_success_page_uri());
        } else {
            // Payment not successful
            $donation->status = DonationStatus::FAILED();
            $donation->save();

            DonationNote::create([
                'donationId' => $donation->id,
                'content' => sprintf(
                /* translators: %s: Payment status from Novac (e.g., pending, failed) */
                    __('Payment status from Novac: %s', 'novac-give'),
                    $verifiedStatus
                ),
            ]);

            return new RedirectResponse(give_get_failed_transaction_uri());
        }
    }

    /**
     * This method is called when the user cancels the payment on Novac.
     *
     * @since 1.0.0
     */
    protected function handleCancelledPaymentReturn(array $queryParams): RedirectResponse
    {
        $donationId = (int)$queryParams['donation-id'];

        /** @var Donation $donation */
        $donation = Donation::find($donationId);
        $donation->status = DonationStatus::CANCELLED();
        $donation->save();

        return new RedirectResponse(esc_url_raw($queryParams['givewp-return-url']));
    }

    /** ----------------------------------------------------------------
     * Webhook listener
     * --------------------------------------------------------------- */
    public function getWebhookUrl(): string
    {
        // GiveWP will expose: /wp-json/give-api/v2/gateways/novac/webhook
        // (Provided by $this->webhook)
        return static::webhook()->url();
    }

    public function handleWebhookNotification(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params() ?: [];
        $reference = sanitize_text_field($payload['reference'] ?? '');
        $status    = strtolower(sanitize_text_field($payload['status'] ?? ''));

        if (!$reference) {
            return new WP_REST_Response(['error' => 'Missing reference'], 400);
        }

        // Optional HMAC verification if Novac provides a signing secret.
        $secret = give_get_option('give_novac_webhook_secret');
        if ($secret) {
            $sigHeader = $request->get_header('x-novac-signature');
            if (!$this->verifyHmac($payload, $sigHeader, $secret)) {
                return new WP_REST_Response(['error' => 'Invalid signature'], 400);
            }
        }

        $donationId = give()->donations->getIdByPaymentKey($reference);
        if (!$donationId) {
            Log::warning('Novac Webhook: Donation not found for reference', ['reference' => $reference]);
            return new WP_REST_Response(['ok' => true], 200);
        }

        if ($status === 'success') {
            give_update_payment_status($donationId, 'publish');
        } elseif ($status === 'failed' || $status === 'cancelled') {
            give_update_payment_status($donationId, 'failed');
        } else {
            // Unknown status — do nothing, keep pending.
            Log::info('Novac Webhook: Unknown status', ['status' => $status, 'reference' => $reference]);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    /** ----------------------------------------------------------------
     * Helpers: Novac API calls
     * --------------------------------------------------------------- */

    private function createNovacTransaction(Donation $donation)
    {
        $public = trim((string) give_get_option('give_novac_public_key'));
        if (!$public) {
            return new WP_Error('novac_missing_secret', 'Novac Public Key is not set.');
        }

        $amount =  (floatval($donation->amount->getAmount()) / 100); // e.g., NGN kobo
        $currency = $donation->amount->getCurrency()->getCode();        // e.g., NGN, USD
//		$reference = $donation->gatewayTransactionId ?: $donation->paymentKey;
        $reference = 'give-' . $donation->id . '-' . uniqid('GWP');

        // Store reference in donation meta
        give_update_payment_meta($donation->id, '_give_novac_reference', $reference);

        // Use a secure Give route as return/callback
        $returnUrl = $this->generateSecureGatewayRouteUrl('handleReturn', $donation->id, [
            'reference' => $reference,
        ]);

        $returnUrl = $this->generateGatewayRouteUrl('handleReturn', [
            'reference' => $reference,
            'donation-id' => $donation->id,
        ]);

        $body = [
            'transactionReference' => $reference,
            'amount'      => $amount,
            'currency'    => $currency ?? 'NGN',
            'redirectUrl' => $returnUrl,
            'checkoutCustomerData' => [
                'email' => $donation->email,
                'firstName' => $donation->firstName ?? '',
                'lastName' => $donation->lastName ?? '',
                'phoneNumber' => $donation->phone ?? '',
            ],
            'checkoutCustomizationData' => [
//                'logoUrl' => get_site_icon_url() ?? home_url( '/favicon.ico' ),
                'paymentDescription' => $donation->formTitle,
                'checkoutModalTitle' => $donation->formTitle,
            ]
        ];

        PaymentGatewayLog::info(
            'Novac – Payload for Checkout',
            [
                'donation_id' => $donation->id,
                'payload' => $body,
                'redirectUrl' => $returnUrl,
//                "meta_data" => $donation
            ]
        );

        $endpoint = 'https://api.novacpayment.com/api/v1/initiate';
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $public,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ];

        $res = wp_remote_post($endpoint, $args);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);

        if ($code >= 200 && $code < 300 && isset($json['data']['paymentRedirectUrl'])) {
            // store gateway transaction id if available
            if (!empty($json['data']['paymentRedirectUrl'])) {
                give_update_payment_meta($donation->id, '_give_payment_transaction_id', sanitize_text_field($json['data']['reference']));
            }
            return [
                'authorization_url' => $json['data']['paymentRedirectUrl'],
            ];
        }

        return new WP_Error('novac_init_failed', 'Failed to initialize Novac transaction: ' . wp_json_encode($json));
    }

    private function verifyNovac(string $reference)
    {
        Log::info(' Novac Verifying Transaction', ['reference' => $reference]);
        $secret = trim((string) give_get_option('give_novac_secret_key'));
        if (!$secret) return new WP_Error('novac_missing_secret', 'Secret missing');

        $endpoint = "https://api.novacpayment.com/api/v1/checkout/{$reference}/verify";
        $res = wp_remote_get($endpoint, [
            'headers' => ['Authorization' => 'Bearer ' . $secret],
            'timeout' => 30,
        ]);

        if (is_wp_error($res)) return $res;
        $json = json_decode(wp_remote_retrieve_body($res), true);

        return $json ?: new WP_Error('novac_verify_failed', 'Invalid verify response');
    }

    private function verifyHmac(array $payload, ?string $sigHeader, string $secret): bool
    {
        if (!$sigHeader) return false;
        $computed = hash_hmac('sha256', wp_json_encode($payload), $secret);
        // You may need to match Novac’s exact signature format; adapt accordingly.
        return hash_equals($computed, $sigHeader);
    }

    private function toGatewayMinor(Money $money): int
    {
        // Give Money is precise; but $money->getAmount() here is a Money VO, not a float.
        // Convert safely to minor units via string:
        $asString = (string) $money; // ex "1500.00"
        // Remove decimal point to get minor units (assumes 2dp currencies).
        return (int) round(floatval($asString) * 100);
    }

    /**
     * Get the Ip of the current request.
     *
     * @return string
     */
    public function novac_get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
                foreach ( $ip_list as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return $ip;
                    }
                }
            }
        }

        return 'UNKNOWN';
    }

    /**
     * This method implementation is a sample that demonstrates how we can handle webhook notifications
     *
     * @since 4.5.0
     */
    public function webhookNotificationsListener()
    {
        if ( NOVAC_ALLOWED_WEBHOOK_IP_ADDRESS !== $this->novac_get_client_ip() ) {
            PaymentGatewayLog::warning(
                'Novac Webhook: Restricted IP Address',
                [
                    'ip' => $this->novac_get_client_ip(),
                ]
            );
            wp_send_json(
                array(
                    'status'  => 'error',
                    'message' => 'Unauthorized Access (Restriction)',
                ),
                WP_Http::UNAUTHORIZED
            );
        }

        try {

            $rawBody = file_get_contents('php://input');
//			$webhookNotification = give_clean($_REQUEST);
            $webhookNotification = json_decode($rawBody, true);

            /**
             * Allow developers to handle the webhook notification.
             *
             * @since 1.0.0
             *
             * @param array $webhookNotification
             */
            do_action('givewp_' . $this::id() . '_webhook_notification_handler', $webhookNotification);

            // We will handle recurring donations in a separate submodule.
            if (isset($webhookNotification['gatewayRecurringPayment']) && $webhookNotification['gatewayRecurringPayment']) {
                return;
            }

            if ( ! isset($webhookNotification['gatewayPaymentStatus']) && ! isset($webhookNotification['gatewayPaymentId'])) {
                return;
            }

            switch (strtolower($webhookNotification['gatewayPaymentStatus'])) {
                case 'complete':
                    $this->webhook->events->paymentCompleted($webhookNotification['gatewayPaymentId']);
                    break;
                case 'failed':
                    $this->webhook->events->paymentFailed($webhookNotification['gatewayPaymentId']);
                    break;
                case 'cancelled':
                    $this->webhook->events->paymentCancelled($webhookNotification['gatewayPaymentId']);
                    break;
                case 'refunded':
                    $this->webhook->events->paymentRefunded($webhookNotification['gatewayPaymentId']);
                    break;
                default:
                    break;
            }

            wp_send_json([ 'status' => 'success']);

        } catch (Exception $e) {
            esc_html_e('Novac Webhook Notification failed.', 'novac-give');
            PaymentGatewayLog::error(
                'Webhook Notification failed. Error: ' . $e->getMessage()
            );
        }

        exit();
    }

}