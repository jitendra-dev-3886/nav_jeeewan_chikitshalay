<?php

namespace App\Models;

class AuditLog extends ClinicModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public static function record(string $action, string $entity, mixed $id = null, array $metadata = []): void
    {
        static::create(['actor_id' => auth()->id(), 'action' => $action, 'entity' => $entity, 'entity_id' => $id, 'metadata' => $metadata]);
    }
}
