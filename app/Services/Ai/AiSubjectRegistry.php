<?php

namespace App\Services\Ai;

use App\Services\Ai\Context\AiContextBuilder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Single lookup point for everything that can carry an AI summary.
 *
 * Everything downstream (service, job, controller, blade) talks to a subject key
 * instead of a concrete model, so adding an entity is a config change plus one
 * context builder.
 */
class AiSubjectRegistry
{
    /** @var array<string, AiSubjectDefinition>|null */
    private ?array $definitions = null;

    public function enabled(): bool
    {
        return (bool) config('ai_summaries.enabled', true);
    }

    /**
     * @return array<string, AiSubjectDefinition>
     */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $defaults = (array) config('ai_summaries.defaults', []);

        $definitions = [];

        foreach ((array) config('ai_summaries.subjects', []) as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            $definitions[$key] = AiSubjectDefinition::fromConfig((string) $key, $config, $defaults);
        }

        return $this->definitions = $definitions;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function find(string $key): ?AiSubjectDefinition
    {
        return $this->all()[$key] ?? null;
    }

    public function get(string $key): AiSubjectDefinition
    {
        return $this->find($key) ?? throw new InvalidArgumentException("Unknown AI summary subject: {$key}");
    }

    public function findForModel(Model $model): ?AiSubjectDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->matches($model)) {
                return $definition;
            }
        }

        return null;
    }

    public function forModel(Model $model): AiSubjectDefinition
    {
        return $this->findForModel($model)
            ?? throw new InvalidArgumentException('No AI summary subject registered for '.$model::class);
    }

    /**
     * Whether summaries are switched on globally and for this subject.
     */
    public function isEnabled(AiSubjectDefinition|string $subject): bool
    {
        $definition = $subject instanceof AiSubjectDefinition ? $subject : $this->find($subject);

        return $this->enabled() && $definition?->enabled === true;
    }

    public function builder(AiSubjectDefinition|string $subject): AiContextBuilder
    {
        $definition = $subject instanceof AiSubjectDefinition ? $subject : $this->get($subject);

        /** @var AiContextBuilder $builder */
        $builder = app($definition->builderClass);

        return $builder->for($definition);
    }
}
