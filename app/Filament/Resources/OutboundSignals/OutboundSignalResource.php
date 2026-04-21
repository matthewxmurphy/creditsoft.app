<?php

namespace App\Filament\Resources\OutboundSignals;

use App\Filament\Resources\OutboundSignals\Pages\ManageOutboundSignals;
use App\Models\Client;
use App\Models\OutboundSignal;
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

class OutboundSignalResource extends Resource
{
    protected static ?string $model = OutboundSignal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Outbound Signals';

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
                    ->preload(),
                TextInput::make('event_type')
                    ->required(),
                TextInput::make('visibility')
                    ->required()
                    ->default('shareable_case_brief'),
                Textarea::make('payload')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('sanitized_payload')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('queued_at')
                    ->required(),
                DateTimePicker::make('sent_at'),
                Textarea::make('error_message')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.id')
                    ->label('Client')
                    ->placeholder('-'),
                TextEntry::make('event_type'),
                TextEntry::make('visibility'),
                TextEntry::make('payload')
                    ->columnSpanFull(),
                TextEntry::make('sanitized_payload')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('queued_at')
                    ->dateTime(),
                TextEntry::make('sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('error_message')
                    ->placeholder('-')
                    ->columnSpanFull(),
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
                    ->formatStateUsing(fn ($state, OutboundSignal $record) => $record->client?->display_name ?? '-')
                    ->searchable(),
                TextColumn::make('event_type')
                    ->searchable(),
                TextColumn::make('visibility')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('queued_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sent_at')
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
            'index' => ManageOutboundSignals::route('/'),
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
