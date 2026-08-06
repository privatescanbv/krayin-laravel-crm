<?php

namespace App\Services\Ai;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry from config/ai_summaries.php -> subjects, resolved into an object.
 */
class AiSubjectDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $modelClass,
        public readonly string $builderClass,
        public readonly string $useCase,
        public readonly string $payloadKey,
        public readonly string $promptVersion,
        public readonly string $label,
        public readonly string $viewPermission,
        public readonly string $editPermission,
        public readonly ?string $route,
        public readonly bool $enabled,
        public readonly bool $generateOnView,
        public readonly bool $ownerScoped,
        public readonly int $staleAfterHours,
        public readonly int $activityLimit,
        public readonly int $emailLimit,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $defaults
     */
    public static function fromConfig(string $key, array $config, array $defaults = []): self
    {
        $value = fn (string $name, mixed $fallback = null) => $config[$name] ?? $defaults[$name] ?? $fallback;

        return new self(
            key: $key,
            modelClass: (string) $config['model'],
            builderClass: (string) $config['builder'],
            useCase: (string) $config['use_case'],
            payloadKey: (string) $config['payload_key'],
            promptVersion: (string) $value('prompt_version', 'v1'),
            label: (string) $value('label', $key),
            viewPermission: (string) $config['view_permission'],
            editPermission: (string) $config['edit_permission'],
            route: $value('route'),
            enabled: (bool) $value('enabled', true),
            generateOnView: (bool) $value('generate_on_view', true),
            ownerScoped: (bool) $value('owner_scoped', true),
            staleAfterHours: (int) $value('stale_after_hours', 24),
            activityLimit: max(1, (int) $value('activity_limit', 12)),
            emailLimit: max(1, (int) $value('email_limit', 6)),
        );
    }

    public function matches(Model $model): bool
    {
        return $model instanceof $this->modelClass;
    }

    /**
     * The value stored in ai_summaries.subject_type — the model's morph alias, which
     * is normally the subject key but stays correct if the two ever diverge.
     */
    public function morphClass(): string
    {
        /** @var Model $prototype */
        $prototype = new $this->modelClass;

        return $prototype->getMorphClass();
    }

    public function findOrFail(int $id): Model
    {
        /** @var Model $prototype */
        $prototype = new $this->modelClass;

        return $prototype->newQuery()->findOrFail($id);
    }

    public function find(int $id): ?Model
    {
        /** @var Model $prototype */
        $prototype = new $this->modelClass;

        return $prototype->newQuery()->find($id);
    }
}
