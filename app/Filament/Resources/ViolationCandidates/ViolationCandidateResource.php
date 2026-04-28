<?php

namespace App\Filament\Resources\ViolationCandidates;

use App\Filament\Resources\ViolationCandidates\Pages\ManageViolationCandidates;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\Tradeline;
use App\Models\ViolationCandidate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ViolationCandidateResource extends Resource
{
    protected static ?string $model = ViolationCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?string $navigationLabel = 'Violation Queue';

    protected static function isReadOnlyDemo(): bool
    {
        return auth()->user()?->isReadOnlyDemo() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Client')
                    ->options(Client::query()->get()->mapWithKeys(fn (Client $client) => [$client->getKey() => $client->display_name])->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('reporting_cycle_id')
                    ->label('Reporting cycle')
                    ->options(ReportingCycle::query()->orderByDesc('started_at')->pluck('cycle_label', 'id')->all())
                    ->searchable()
                    ->preload(),
                Select::make('tradeline_id')
                    ->label('Tradeline')
                    ->options(Tradeline::query()->pluck('creditor_name', 'id')->all())
                    ->searchable(),
                TextInput::make('rule_key')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Select::make('severity')
                    ->required()
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->default('medium'),
                Select::make('status')
                    ->required()
                    ->options([
                        'open' => 'Open',
                        'confirmed' => 'Confirmed',
                        'resolved' => 'Resolved',
                    ])
                    ->default('open'),
                Select::make('bureau')
                    ->options([
                        'experian' => 'Experian',
                        'transunion' => 'TransUnion',
                        'equifax' => 'Equifax',
                    ]),
                Textarea::make('evidence')
                    ->columnSpanFull(),
                Textarea::make('next_action')
                    ->columnSpanFull(),
                TextInput::make('confirmed_by')
                    ->numeric(),
                DateTimePicker::make('confirmed_at'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.id')
                    ->label('Client'),
                TextEntry::make('reportingCycle.id')
                    ->label('Reporting cycle')
                    ->placeholder('-'),
                TextEntry::make('tradeline.id')
                    ->label('Tradeline')
                    ->placeholder('-'),
                TextEntry::make('rule_key'),
                TextEntry::make('title'),
                TextEntry::make('severity'),
                TextEntry::make('status'),
                TextEntry::make('bureau')
                    ->placeholder('-'),
                TextEntry::make('evidence')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('next_action')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('confirmed_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('confirmed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, ViolationCandidate $record) => $record->client?->display_name ?? '-')
                    ->searchable(),
                TextColumn::make('reportingCycle.cycle_label')
                    ->label('Cycle')
                    ->searchable(),
                TextColumn::make('rule_key')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('severity')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('bureau')
                    ->formatStateUsing(fn (?string $state) => $state ? Str::headline($state) : 'All bureaus')
                    ->searchable(),
                TextColumn::make('confirmed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageViolationCandidates::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return ! static::isReadOnlyDemo();
    }

    public static function canEdit(Model $record): bool
    {
        return ! static::isReadOnlyDemo();
    }

    public static function canDelete(Model $record): bool
    {
        return ! static::isReadOnlyDemo();
    }

    public static function canDeleteAny(): bool
    {
        return ! static::isReadOnlyDemo();
    }
}
