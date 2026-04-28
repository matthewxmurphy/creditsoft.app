<?php

namespace App\Console\Commands;

use App\Creditsoft\Config\YamlConfigLoader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('creditsoft:config:reload')]
#[Description('Reload and validate the Creditsoft YAML configuration set.')]
class CreditsoftReloadConfig extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $config = app(YamlConfigLoader::class)->reload();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Creditsoft configuration reloaded.');
        $this->table(
            ['File', 'Top-level keys'],
            collect($config)->map(fn ($value, $file) => [
                $file,
                implode(', ', array_keys(is_array($value) ? $value : [])),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
