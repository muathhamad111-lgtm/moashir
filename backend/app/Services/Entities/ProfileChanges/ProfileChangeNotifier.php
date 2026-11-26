<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Data\Entities\EntityNotice;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileChangeFile;
use App\Enums\Entities\ProfileChangeVerdict;
use App\Enums\Identity\PermissionKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Services\Entities\EntityNotifier;
use App\Support\Entities\EntityLinks;
use App\Support\ValueObjects\LocalizedText;

final readonly class ProfileChangeNotifier
{
    private const string APPROVER = 'approver';

    public function __construct(private EntityNotifier $notifier) {}

    public function toApprovers(EntityProfileChangeRequest $request): void
    {
        $entity = $request->entity;
        $replacements = ['entity' => $entity->name->toLocalizedText(), 'reference' => $entity->reference()];
        $notice = EntityNotice::for(NotificationCode::EntityProfileChangeRequested, self::APPROVER, $replacements, EntityLinks::profileChanges());

        $this->notifier->toPermissionHolders(PermissionKey::EntitiesProfileChangeApprove, $notice, null);
    }

    public function toEntity(EntityProfileChangeRequest $request): void
    {
        $entity = $request->entity;
        $rejected = $this->rejectedLabelKeys($request);
        $replacements = [
            'entity' => $entity->name->toLocalizedText(),
            'fields' => $rejected === [] ? '' : LocalizedText::listOf($rejected),
            'reason' => (string) $request->review_note,
        ];

        $this->notifier->toOwner($entity, EntityNotice::for(NotificationCode::EntityProfileChangeDecided, $request->status->value, $replacements, EntityLinks::ownProfile()));
    }

    /**
     * @return list<string>
     */
    private function rejectedLabelKeys(EntityProfileChangeRequest $request): array
    {
        $rejected = ProfileChangeVerdict::Rejected->itemsIn($request->decisions ?? []);

        return array_values(array_filter(array_map(static fn (string $item): ?string => (EntityProfileField::tryFrom($item) ?? ProfileChangeFile::tryFrom($item))?->labelKey(), $rejected)));
    }
}
