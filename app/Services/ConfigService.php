<?php

namespace App\Services;

use App\Models\AppConfig;

class ConfigService
{
    /**
     * Load config from DB, or initialize from defaults.
     */
    public function load(): array
    {
        $record = AppConfig::where('config_key', 'main')->first();

        if ($record && !empty($record->config_json)) {
            return $this->mergeWithDefaults($record->config_json);
        }

        // No DB config — initialize from defaults
        $defaults = config('app-defaults');
        $this->save($defaults);

        return $defaults;
    }

    /**
     * Save config to DB.
     */
    public function save(array $config): void
    {
        AppConfig::updateOrCreate(
            ['config_key' => 'main'],
            ['config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]
        );
    }

    /**
     * Reset config to defaults.
     */
    public function reset(): array
    {
        $defaults = config('app-defaults');
        $this->save($defaults);

        return $defaults;
    }

    /**
     * Get a specific config value by dot-notation key.
     */
    public function get(string $key, $default = null)
    {
        $config = $this->load();

        return data_get($config, $key, $default);
    }

    /**
     * Update a specific section of the config.
     */
    public function updateSection(string $section, $value): array
    {
        $config = $this->load();

        // Handle nested keys like 'iomCharges'
        data_set($config, $section, $value);

        $this->save($config);

        return $config;
    }

    /**
     * Update multiple config keys at once.
     */
    public function updateMany(array $updates): array
    {
        $config = $this->load();

        foreach ($updates as $key => $value) {
            data_set($config, $key, $value);
        }

        $this->save($config);

        return $config;
    }

    /**
     * Merge loaded config with defaults to ensure all keys exist.
     */
    protected function mergeWithDefaults($configJson): array
    {
        $defaults = config('app-defaults');
        $loaded = is_array($configJson) ? $configJson : json_decode($configJson, true);

        if (!is_array($loaded)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $loaded);
    }
}
