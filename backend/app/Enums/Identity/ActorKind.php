<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum ActorKind: string
{
    case Entity = 'entity';
    case Staff = 'staff';
    case System = 'system';

    public static function of(?int $actorUserId, ?int $entityOwnerId): self
    {
        return match (true) {
            $actorUserId === null => self::System,
            $actorUserId === $entityOwnerId => self::Entity,
            default => self::Staff,
        };
    }
}
