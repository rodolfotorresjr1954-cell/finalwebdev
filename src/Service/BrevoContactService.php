<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BrevoContactService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $brevoApiKey,
        private readonly string $brevoFromEmail,
        private readonly string $brevoToEmail,
        private readonly string $brevoFromName = 'Grilled & Bites Burger',
    ) {
    }

    /**
     * Sends a contact message via Brevo Transactional Email API.
     */
    public function sendContactMessage(string $name, string $email, string $subject, string $message): void
    {
        $this->sendEmail(
            $this->brevoToEmail,
            sprintf('[Contact] %s', $subject),
            sprintf("Name: %s\nEmail: %s\n\nMessage:\n%s\n", $name, $email, $message),
            replyToEmail: $email,
            replyToName: $name,
        );
    }

    /**
     * Notify the site owner when a new customer registers.
     */
    public function sendNewRegistrationAlert(string $username, string $email): void
    {
        $this->sendEmail(
            $this->brevoToEmail,
            sprintf('[New registration] %s', $username),
            sprintf(
                "A new user registered on Grilled & Bites Burger.\n\nUsername: %s\nEmail: %s\n\nThey must verify their email before they can log in.",
                $username,
                $email
            ),
        );
    }

    /**
     * Sends a transactional email to any recipient (verification, alerts, etc.).
     */
    public function sendEmail(
        string $toEmail,
        string $subject,
        string $textContent,
        ?string $htmlContent = null,
        ?string $replyToEmail = null,
        ?string $replyToName = null,
    ): void {
        if ('' === trim($this->brevoApiKey) || '' === trim($this->brevoFromEmail)) {
            throw new \RuntimeException('Brevo is not configured (missing API key or from email).');
        }

        $payload = [
            'sender' => [
                'name' => $this->brevoFromName,
                'email' => $this->brevoFromEmail,
            ],
            'to' => [
                ['email' => $toEmail],
            ],
            'subject' => $subject,
            'textContent' => $textContent,
        ];

        if ($htmlContent !== null && $htmlContent !== '') {
            $payload['htmlContent'] = $htmlContent;
        }

        if ($replyToEmail !== null && $replyToEmail !== '') {
            $payload['replyTo'] = [
                'email' => $replyToEmail,
                'name' => $replyToName ?? $replyToEmail,
            ];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'api-key' => $this->brevoApiKey,
                    'accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException(sprintf('Brevo API returned HTTP %d.', $status));
            }
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Failed to send email via Brevo.', 0, $e);
        }
    }
}

