<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function log(
        User $user,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $affectedData = null,
        ?string $description = null
    ): void {
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setUsername($user->getUserIdentifier());
        $log->setRole($this->extractPrimaryRole($user));
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);

        if ($affectedData !== null) {
            $log->setAffectedData(json_encode($affectedData, JSON_PRETTY_PRINT));
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function logCreate(User $user, string $entityType, int $entityId, ?array $data = null, ?string $description = null): void
    {
        $this->log($user, 'CREATE', $entityType, $entityId, $data, $description);
    }

    public function logUpdate(User $user, string $entityType, int $entityId, ?array $data = null, ?string $description = null): void
    {
        $this->log($user, 'UPDATE', $entityType, $entityId, $data, $description);
    }

    public function logDelete(User $user, string $entityType, int $entityId, ?array $data = null, ?string $description = null): void
    {
        $this->log($user, 'DELETE', $entityType, $entityId, $data, $description);
    }

    public function logLogin(User $user, string $source = 'web'): void
    {
        $sourceLabel = match ($source) {
            'mobile', 'app' => 'mobile app',
            'web' => 'website',
            default => $source,
        };

        $this->log($user, 'LOGIN', null, null, null, sprintf('Logged in via %s', $sourceLabel));
    }

    public function logOrderPlaced(User $user, Order $order, string $source = 'mobile'): void
    {
        $orderId = $order->getId();
        if (null === $orderId) {
            return;
        }

        $sourceLabel = match ($source) {
            'mobile', 'app' => 'mobile app',
            'web' => 'website',
            default => $source,
        };

        $label = $order->getName() ?? 'Order';
        $total = (float) $order->getTotal();

        $this->logCreate(
            $user,
            'Order',
            $orderId,
            ['name' => $label, 'total' => $total, 'status' => $order->getStatus()],
            sprintf('Placed order via %s: %s (₱%s)', $sourceLabel, $label, number_format($total, 2, '.', ','))
        );
    }

    public function logLogout(User $user): void
    {
        $this->log($user, 'LOGOUT', null, null, null, 'User logged out');
    }

    public function logPasswordChange(User $user): void
    {
        $this->log($user, 'PASSWORD_CHANGE', 'User', $user->getId(), null, 'Password changed');
    }

    public function logRegister(User $user, string $source = 'mobile'): void
    {
        $sourceLabel = match ($source) {
            'mobile', 'app' => 'mobile app',
            'web' => 'website',
            default => $source,
        };

        $this->log(
            $user,
            'REGISTER',
            'User',
            $user->getId(),
            ['username' => $user->getUsername(), 'email' => $user->getEmail()],
            sprintf('New user registered via %s: %s', $sourceLabel, $user->getUsername())
        );
    }

    private function extractPrimaryRole(User $user): string
    {
        $roles = $user->getRoles();
        foreach ($roles as $role) {
            if ($role !== 'ROLE_USER') {
                return $role;
            }
        }

        return $roles[0] ?? 'ROLE_USER';
    }
}

