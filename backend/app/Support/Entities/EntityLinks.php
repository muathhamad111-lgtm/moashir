<?php

declare(strict_types=1);

namespace App\Support\Entities;

final class EntityLinks
{
    private const string STAFF_ROOT = '/admin/entities';

    public static function ownProfile(): string
    {
        return '/entity/profile';
    }

    public static function signIn(): string
    {
        return '/auth/login';
    }

    public static function record(int $entityId): string
    {
        return self::STAFF_ROOT.'/'.$entityId;
    }

    public static function emailChanges(): string
    {
        return self::STAFF_ROOT.'/email-changes';
    }

    public static function deletedEntities(): string
    {
        return self::STAFF_ROOT.'/deleted';
    }
}
