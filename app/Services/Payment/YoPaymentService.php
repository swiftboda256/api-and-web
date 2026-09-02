<?php

namespace App\Services\Payment;

use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Wraps the Yo! Payments XML API (see docs/API_3_48.pdf).
 *
 * Collections (acdepositfunds) and disbursements (acwithdrawfunds) are submitted
 * with NonBlocking=TRUE, so Yo! typically responds with TransactionStatus=PENDING
 * and the final SUCCEEDED/FAILED outcome must be resolved later via
 * checkTxnStatus() (using the TransactionReference returned here), since no
 * Instant Payment Notification (IPN) listener is implemented yet.
 */
class YoPaymentService implements PaymentGateway
{
    private const string SUPPORTED_CURRENCY = 'UGX';

    public function name(): string
    {
        return 'Yo Uganda LTD';
    }

    public function collectFromMobileMoney(string $phone, float $amount, string $currencyCode, string $reference, string $narrative): MobileMoneyResult
    {
        $this->assertSupportedCurrency($currencyCode);

        $xml = $this->buildRequestXml('acdepositfunds', [
            'NonBlocking' => 'TRUE',
            'Amount' => $amount,
            'Account' => $phone,
            'AccountProviderCode' => config('services.yo.account_provider_code'),
            'Narrative' => $narrative,
            'ExternalReference' => $reference,
        ]);

        return $this->submit($xml, $amount);
    }

    public function disburseToMobileMoney(string $phone, float $amount, string $currencyCode, string $reference, string $narrative): MobileMoneyResult
    {
        $this->assertSupportedCurrency($currencyCode);

        $xml = $this->buildRequestXml('acwithdrawfunds', [
            'NonBlocking' => 'TRUE',
            'Amount' => $amount,
            'Account' => $phone,
            'AccountProviderCode' => config('services.yo.account_provider_code'),
            'Narrative' => $narrative,
            'ExternalReference' => $reference,
        ]);

        return $this->submit($xml, $amount);
    }

    public function checkTxnStatus(string $transactionReference): MobileMoneyResult
    {
        $xml = $this->buildRequestXml('actransactioncheckstatus', [
            'TransactionReference' => $transactionReference,
        ]);

        return $this->submit($xml, null);
    }

    /**
     * Verifies an Instant Payment Notification per docs section 6.3.4: the
     * `signature` parameter is a base64-encoded RSA/SHA1 signature over
     * date_time+amount+narrative+network_ref+external_ref+msisdn (concatenated
     * in that order, no separators) signed with Yo!'s private key.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function verifyIpnSignature(array $parameters, string $signature): bool
    {
        $message = implode('', [
            (string) ($parameters['date_time'] ?? ''),
            (string) ($parameters['amount'] ?? ''),
            (string) ($parameters['narrative'] ?? ''),
            (string) ($parameters['network_ref'] ?? ''),
            (string) ($parameters['external_ref'] ?? ''),
            (string) ($parameters['msisdn'] ?? ''),
        ]);

        return $this->verifySignature($message, $signature);
    }

    /**
     * Verifies a Transaction Failure Notification per docs section 6.4.3: the
     * `verification` parameter is a base64-encoded RSA/SHA1 signature over
     * failed_transaction_reference+transaction_init_date (concatenated in that
     * order, no separators).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function verifyFailureNotificationSignature(array $parameters, string $verification): bool
    {
        $message = implode('', [
            (string) ($parameters['failed_transaction_reference'] ?? ''),
            (string) ($parameters['transaction_init_date'] ?? ''),
        ]);

        return $this->verifySignature($message, $verification);
    }

    private function verifySignature(string $message, string $signature): bool
    {
        $publicKeyPath = config('services.yo.ipn_public_key_path');

        if (! $publicKeyPath) {
            Log::error('yo.ipn.missing_public_key_path');

            return false;
        }

        $resolvedPath = base_path((string) $publicKeyPath);
        $publicKeyPem = @file_get_contents($resolvedPath);

        if ($publicKeyPem === false) {
            Log::error('yo.ipn.unreadable_public_key_file', ['path' => $resolvedPath]);

            return false;
        }

        $publicKey = openssl_pkey_get_public(str_replace('\n', "\n", (string) $publicKeyPem));

        if ($publicKey === false) {
            Log::error('yo.ipn.invalid_public_key_config');

            return false;
        }

        $decodedSignature = base64_decode($signature, true);

        if ($decodedSignature === false) {
            return false;
        }

        return openssl_verify($message, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA1) === 1;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function buildRequestXml(string $method, array $fields): string
    {
        $xml = new SimpleXMLElement('<AutoCreate><Request/></AutoCreate>');
        $request = $xml->Request;

        $request->addChild('APIUsername', $this->escapeXml(config('services.yo.api_username')));
        $request->addChild('APIPassword', $this->escapeXml(config('services.yo.api_password')));
        $request->addChild('Method', $method);

        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $request->addChild($name, $this->escapeXml((string) $value));
        }

        return (string) $xml->asXML();
    }

    private function escapeXml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function submit(string $xml, ?float $amount): MobileMoneyResult
    {
        $response = Http::withHeaders([
            'Content-Type' => 'text/xml',
            'Content-transfer-encoding' => 'text',
        ])->withBody($xml, 'text/xml')->post(config('services.yo.base_url'));

        Log::info('yo.response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            Log::error('yo.request_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return new MobileMoneyResult(
                status: MobileMoneyTransactionStatus::Failed,
                transactionReference: null,
                gatewayReference: null,
                amount: $amount,
                failureReason: 'Unable to reach the Yo! Payments gateway.',
            );
        }

        return $this->mapResponseToResult($this->parseResponseXml($response->body()), $amount);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponseXml(string $body): array
    {
        if (! str_starts_with(ltrim($body), '<')) {
            Log::error('yo.non_xml_response', ['body' => $body]);

            return [];
        }

        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($xml === false || ! isset($xml->Response)) {
            return [];
        }

        return json_decode((string) json_encode($xml->Response), true) ?: [];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function mapResponseToResult(array $fields, ?float $amount): MobileMoneyResult
    {
        $status = strtoupper((string) ($fields['Status'] ?? ''));

        if ($status !== 'OK') {
            return new MobileMoneyResult(
                status: MobileMoneyTransactionStatus::Failed,
                transactionReference: $fields['TransactionReference'] ?? null,
                gatewayReference: null,
                amount: $amount,
                failureReason: $fields['StatusMessage'] ?? $fields['ErrorMessage'] ?? 'The transaction was rejected by Yo! Payments.',
            );
        }

        $transactionStatus = match (strtoupper((string) ($fields['TransactionStatus'] ?? ''))) {
            'SUCCEEDED' => MobileMoneyTransactionStatus::Succeeded,
            'FAILED' => MobileMoneyTransactionStatus::Failed,
            'INDETERMINATE' => MobileMoneyTransactionStatus::Indeterminate,
            default => MobileMoneyTransactionStatus::Pending,
        };

        return new MobileMoneyResult(
            status: $transactionStatus,
            transactionReference: $fields['TransactionReference'] ?? null,
            gatewayReference: $fields['MNOTransactionReferenceId'] ?? null,
            amount: isset($fields['Amount']) ? (float) $fields['Amount'] : $amount,
            failureReason: $transactionStatus === MobileMoneyTransactionStatus::Failed
                ? ($fields['StatusMessage'] ?? $fields['ErrorMessage'] ?? null)
                : null,
        );
    }

    private function assertSupportedCurrency(string $currencyCode): void
    {
        if (strtoupper($currencyCode) !== self::SUPPORTED_CURRENCY) {
            throw new InvalidArgumentException('Yo! Payments only supports ['.self::SUPPORTED_CURRENCY."], got [{$currencyCode}].");
        }
    }
}
