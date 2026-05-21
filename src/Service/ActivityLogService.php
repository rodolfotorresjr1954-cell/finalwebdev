<?php

namespace App\Service;

use App\Entity\ActivityLog;
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

    public function logLogin(User $user): void
    {
        $this->log($user, 'LOGIN', null, null, null, 'User logged in');
    }

    public function logLogout(User $user): void
    {
        $this->log($user, 'LOGOUT', null, null, null, 'User logged out');
    }

    public function logPasswordChange(User $user): void
    {
        $this->log($user, 'PASSWORD_CHANGE', 'User', $user->getId(), null, 'Password changed');
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

