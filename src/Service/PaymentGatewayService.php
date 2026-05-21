<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Payment token validation. Replace token verification with Stripe/PayMongo when keys are configured.
 */
final class PaymentGatewayService
{
    public const METHOD_CASH = 'cash';
    public const METHOD_GCASH = 'gcash';
    public const METHOD_ATM = 'atm';
    public const METHOD_CARD = 'card';

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return [
            self::METHOD_CASH,
            self::METHOD_GCASH,
            self::METHOD_ATM,
            self::METHOD_CARD,
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertPaymentReady(string $method, ?string $paymentToken, float $amount): void
    {
        $method = strtolower(trim($method));
        if (!\in_array($method, $this->getAllowedMethods(), true)) {
            throw new \InvalidArgumentException('Invalid payment method.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Order total must be greater than zero.');
        }

        if (self::METHOD_CARD === $method) {
            $this->validateCardToken($paymentToken, $amount);

            return;
        }

        if (null !== $paymentToken && $paymentToken !== '') {
            throw new \InvalidArgumentException('Payment token is only required for card payments.');
        }
    }

    /**
     * Simulates server-side charge after client tokenization.
     *
     * @return array{transactionId: string, status: string}
     */
    public function charge(string $method, ?string $paymentToken, float $amount): array
    {
        $this->assertPaymentReady($method, $paymentToken, $amount);

        if (self::METHOD_CARD === $method) {
            return [
                'transactionId' => 'txn_'.substr(hash('sha256', (string) $paymentToken), 0, 16),
                'status' => 'captured',
            ];
        }

        return [
            'transactionId' => 'txn_'.bin2hex(random_bytes(8)),
            'status' => 'pending_manual',
        ];
    }

    private function validateCardToken(?string $paymentToken, float $amount): void
    {
        if (null === $paymentToken || '' === trim($paymentToken)) {
            throw new \InvalidArgumentException('Payment token is required for card payments.');
        }

        $token = trim($paymentToken);

        // Dev/test tokens from mobile client (tok_test_*) — swap for Stripe PaymentIntent confirmation in production.
        if (!preg_match('/^tok_test_[a-f0-9]{16,64}$/i', $token)) {
            throw new \InvalidArgumentException('Invalid payment token format.');
        }

        if ($amount > 50000) {
            throw new \InvalidArgumentException('Amount exceeds card payment limit.');
        }
    }
}
