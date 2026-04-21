<?php

namespace App\Creditsoft\Config;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use JsonException;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlConfigLoader
{
    public const CACHE_KEY = 'creditsoft.yaml-config';

    public function __construct(protected Filesystem $files)
    {
    }

    /**
     * @return array<string, array<mixed>|string|int|float|bool|null>
     */
    public function load(bool $fresh = false): array
    {
        $fingerprint = $this->fingerprint();

        if (! $fresh) {
            /** @var array<string, array<mixed>|string|int|float|bool|null>|null $cached */
            $cached = Cache::get(self::CACHE_KEY);

            if (
                is_array($cached)
                && $cached !== []
                && Arr::get($cached, '_meta.fingerprint') === $fingerprint
            ) {
                unset($cached['_meta']);

                return $cached;
            }
        }

        $config = [];

        foreach (config('creditsoft.required_files', []) as $filename) {
            $parsed = $this->parse($filename);
            $this->validate($filename, $parsed);
            $config[$filename] = $parsed;
        }

        Cache::forever(self::CACHE_KEY, [
            ...$config,
            '_meta' => [
                'fingerprint' => $fingerprint,
            ],
        ]);

        return $config;
    }

    /**
     * @return array<string, array<mixed>|string|int|float|bool|null>
     */
    public function reload(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->load(fresh: true);
    }

    public function directory(): string
    {
        return config('creditsoft.config_path');
    }

    /**
     * @return list<array{file:string, updated_at:string}>
     */
    public function summaries(): array
    {
        $summaries = [];

        foreach (config('creditsoft.required_files', []) as $filename) {
            $path = $this->path($filename);

            $summaries[] = [
                'file' => $filename,
                'updated_at' => $this->files->exists($path)
                    ? date(DATE_ATOM, $this->files->lastModified($path))
                    : 'missing',
            ];
        }

        return $summaries;
    }

    /**
     * @return array<mixed>|string|int|float|bool|null
     */
    protected function parse(string $filename): array|string|int|float|bool|null
    {
        $path = $this->path($filename);

        if (! $this->files->exists($path)) {
            throw new RuntimeException("Creditsoft configuration file [{$filename}] is missing.");
        }

        try {
            if (str_ends_with($filename, '.json')) {
                return json_decode($this->files->get($path), true, 512, JSON_THROW_ON_ERROR);
            }

            return Yaml::parseFile($path);
        } catch (ParseException|JsonException $exception) {
            throw new RuntimeException("Creditsoft configuration file [{$filename}] could not be parsed.", previous: $exception);
        }
    }

    /**
     * @param  array<mixed>|string|int|float|bool|null  $parsed
     */
    protected function validate(string $filename, array|string|int|float|bool|null $parsed): void
    {
        if (! is_array($parsed) || $parsed === []) {
            throw new RuntimeException("Creditsoft configuration file [{$filename}] must return a non-empty array or object.");
        }

        $required = match ($filename) {
            'config.yaml' => ['application', 'privacy', 'deployment'],
            'agents.yaml' => ['agents'],
            'crew.yaml' => ['workflows'],
            'tools.yaml' => ['tools'],
            'persona.yaml' => ['persona'],
            'soul.yaml' => ['principles'],
            'tasks.yaml' => ['tasks'],
            'ai_models.yaml' => ['default_provider', 'models'],
            'letter_templates.yaml' => ['templates'],
            'roadmap.json' => ['milestones'],
            'violation_rules.yaml' => ['rules'],
            default => [],
        };

        foreach ($required as $key) {
            if (! Arr::has($parsed, $key)) {
                throw new RuntimeException("Creditsoft configuration file [{$filename}] is missing required key [{$key}].");
            }
        }
    }

    protected function path(string $filename): string
    {
        return rtrim($this->directory(), '/').'/'.$filename;
    }

    protected function fingerprint(): string
    {
        $parts = [];

        foreach (config('creditsoft.required_files', []) as $filename) {
            $path = $this->path($filename);
            $parts[] = $filename.':'.
                ($this->files->exists($path) ? $this->files->lastModified($path) : 'missing');
        }

        return sha1(implode('|', $parts));
    }
}
