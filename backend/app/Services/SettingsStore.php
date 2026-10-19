<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable LessonLoop rules. Values saved in the settings table
 * override config/lessonloop.php; the rest of the code keeps reading config.
 */
class SettingsStore
{
    private const CACHE_KEY = 'lessonloop.settings';

    /**
     * Editable keys with their allowed range.
     *
     * @var array<string, array{min: int, max: int}>
     */
    public const RULES = [
        'pass_score' => ['min' => 1, 'max' => 100],
        'cooldown_lessons' => ['min' => 1, 'max' => 20],
        'debtor_weight' => ['min' => 1, 'max' => 5],
        'never_asked_weight' => ['min' => 1, 'max' => 5],
        'default_question_count' => ['min' => 1, 'max' => 100],
        'grade_edit_hours' => ['min' => 1, 'max' => 168],
    ];

    /** @var array<string, int> */
    private array $defaults;

    public function __construct(private Cache $cache, private Config $config)
    {
        $this->defaults = collect(array_keys(self::RULES))
            ->mapWithKeys(fn (string $key): array => [$key => (int) $config->get("lessonloop.{$key}")])
            ->all();
    }

    /**
     * Copy the stored overrides into config for this request.
     */
    public function apply(): void
    {
        foreach ($this->values() as $key => $value) {
            $this->config->set("lessonloop.{$key}", $value);
        }
    }

    /**
     * Effective values: defaults with stored overrides on top.
     *
     * @return array<string, int>
     */
    public function values(): array
    {
        return array_merge($this->defaults, $this->overrides());
    }

    /**
     * @return array<string, int>
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * @param  array<string, int>  $values
     */
    public function update(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (array_intersect_key($values, self::RULES) as $key => $value) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        });

        $this->cache->forget(self::CACHE_KEY);
        $this->apply();
    }

    /**
     * Drop the overrides and go back to config/lessonloop.php.
     */
    public function reset(): void
    {
        Setting::query()->whereIn('key', array_keys(self::RULES))->delete();
        $this->cache->forget(self::CACHE_KEY);

        foreach ($this->defaults as $key => $value) {
            $this->config->set("lessonloop.{$key}", $value);
        }
    }

    /**
     * @return array<string, int>
     */
    private function overrides(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->whereIn('key', array_keys(self::RULES))
            ->pluck('value', 'key')
            ->map(fn (string $value): int => (int) $value)
            ->all());
    }
}
