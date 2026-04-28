<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\Client;
use Illuminate\Support\Collection;

class ClientScoreTimeline
{
    /**
     * @return array{
     *     source:?array{id:int,imported_at:?string,page_title:?string,provider_key:?string,provider_label:?string},
     *     as_of_date:?string,
     *     labels:list<string>,
     *     points:list<array{date:string,recorded_on:?string,credit:?int,auto:?int,insurance:?int}>,
     *     series:list<array{key:string,label:string,color:string,values:list<?int>}>
     * }
     */
    public function build(Client $client): array
    {
        $captures = $client->relationLoaded('browserCaptures')
            ? $client->browserCaptures
            : $client->browserCaptures()->latest('imported_at')->get();

        $scoreCapture = $this->selectScoreCapture($captures);

        if (! $scoreCapture && $client->relationLoaded('browserCaptures')) {
            $scoreCapture = $this->selectScoreCapture($client->browserCaptures()->latest('imported_at')->get());
        }

        if (! $scoreCapture) {
            return [
                'source' => null,
                'as_of_date' => null,
                'labels' => [],
                'points' => [],
                'series' => [],
            ];
        }

        /** @var list<array{date:string,recorded_on:?string,credit:?int,auto:?int,insurance:?int}> $history */
        $history = data_get($scoreCapture->metadata, 'provider_capture.score_history', data_get($scoreCapture->metadata, 'smartcredit.score_history', []));
        $chart = data_get($scoreCapture->metadata, 'provider_capture.score_history_chart', data_get($scoreCapture->metadata, 'smartcredit.score_history_chart'));
        $providerKey = data_get($scoreCapture->metadata, 'provider_key');
        $providerLabel = match ($providerKey) {
            'credit_karma' => 'Credit Karma',
            'smartcredit' => 'SmartCredit',
            default => $providerKey ? str($providerKey)->replace('_', ' ')->title()->toString() : null,
        };

        if (($history === [] || ! is_array($history)) && is_array(data_get($scoreCapture->metadata, 'provider_capture.scores', data_get($scoreCapture->metadata, 'smartcredit.scores')))) {
            $history = [[
                'date' => data_get($scoreCapture->metadata, 'provider_capture.as_of_date', data_get($scoreCapture->metadata, 'smartcredit.as_of_date')) ?? optional($scoreCapture->imported_at)?->format('M d, Y') ?? 'Latest',
                'recorded_on' => optional($scoreCapture->imported_at)?->toDateString(),
                'credit' => data_get($scoreCapture->metadata, 'provider_capture.scores.credit', data_get($scoreCapture->metadata, 'smartcredit.scores.credit')),
                'auto' => data_get($scoreCapture->metadata, 'provider_capture.scores.auto', data_get($scoreCapture->metadata, 'smartcredit.scores.auto')),
                'insurance' => data_get($scoreCapture->metadata, 'provider_capture.scores.insurance', data_get($scoreCapture->metadata, 'smartcredit.scores.insurance')),
            ]];
        }

        $points = Collection::make($history)
            ->filter(fn ($row) => is_array($row) && filled($row['date'] ?? null))
            ->map(fn (array $row) => [
                'date' => (string) $row['date'],
                'recorded_on' => is_string($row['recorded_on'] ?? null) ? $row['recorded_on'] : null,
                'credit' => is_numeric($row['credit'] ?? null) ? (int) $row['credit'] : null,
                'auto' => is_numeric($row['auto'] ?? null) ? (int) $row['auto'] : null,
                'insurance' => is_numeric($row['insurance'] ?? null) ? (int) $row['insurance'] : null,
            ])
            ->sortBy(fn (array $row) => $row['recorded_on'] ?? $row['date'])
            ->values()
            ->all();

        return [
            'source' => [
                'id' => $scoreCapture->getKey(),
                'imported_at' => optional($scoreCapture->imported_at)?->toIso8601String(),
                'page_title' => $scoreCapture->page_title,
                'provider_key' => $providerKey,
                'provider_label' => $providerLabel,
            ],
            'as_of_date' => data_get($scoreCapture->metadata, 'provider_capture.as_of_date', data_get($scoreCapture->metadata, 'smartcredit.as_of_date')),
            'labels' => is_array($chart['labels'] ?? null) ? $chart['labels'] : array_map(fn (array $row) => $row['date'], $points),
            'points' => $points,
            'series' => is_array($chart['series'] ?? null) ? $chart['series'] : $this->buildFallbackSeries($points),
        ];
    }

    /**
     * @param  iterable<BrowserCapture>  $captures
     */
    protected function selectScoreCapture(iterable $captures): ?BrowserCapture
    {
        return Collection::make($captures)
            ->filter(function (BrowserCapture $capture): bool {
                $providerKey = data_get($capture->metadata, 'provider_key');
                $providerCapture = data_get($capture->metadata, 'provider_capture');

                if (data_get($capture->metadata, 'smartcredit.profile') === 'score_tracker') {
                    return true;
                }

                return $providerKey === 'credit_karma' && is_numeric(data_get($providerCapture, 'scores.credit'));
            })
            ->sortBy(fn (BrowserCapture $capture) => [
                $this->providerPriority($capture),
                -count(data_get($capture->metadata, 'provider_capture.score_history', data_get($capture->metadata, 'smartcredit.score_history', []))),
                -(optional($capture->imported_at)?->timestamp ?? 0),
            ])
            ->first();
    }

    protected function providerPriority(BrowserCapture $capture): int
    {
        if (data_get($capture->metadata, 'smartcredit.profile') === 'score_tracker') {
            return 0;
        }

        return match (data_get($capture->metadata, 'provider_key')) {
            'smartcredit' => 0,
            'credit_karma' => 1,
            default => 10,
        };
    }

    /**
     * @param  list<array{date:string,recorded_on:?string,credit:?int,auto:?int,insurance:?int}>  $points
     * @return list<array{key:string,label:string,color:string,values:list<?int>}>
     */
    protected function buildFallbackSeries(array $points): array
    {
        $definitions = [
            'credit' => ['label' => 'Credit Score', 'color' => '#5f8b95'],
            'auto' => ['label' => 'Auto Score', 'color' => '#ba4d51'],
            'insurance' => ['label' => 'Insurance', 'color' => '#af8a53'],
        ];

        $series = [];

        foreach ($definitions as $key => $definition) {
            $values = array_map(fn (array $row) => $row[$key] ?? null, $points);

            if (count(array_filter($values, static fn ($value) => $value !== null)) === 0) {
                continue;
            }

            $series[] = [
                'key' => $key,
                'label' => $definition['label'],
                'color' => $definition['color'],
                'values' => $values,
            ];
        }

        return $series;
    }
}
