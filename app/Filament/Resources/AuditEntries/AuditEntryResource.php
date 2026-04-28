<?php

namespace App\Filament\Resources\AuditEntries;

use App\Filament\Resources\AuditEntries\Pages\ManageAuditEntries;
use App\Models\AuditEntry;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

class AuditEntryResource extends Resource
{
    protected static ?string $model = AuditEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static function isReadOnlyDemo(): bool
    {
        return auth()->user()?->isReadOnlyDemo() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('auditable_type'),
                TextInput::make('auditable_id')
                    ->numeric(),
                TextInput::make('event')
                    ->required(),
                TextInput::make('summary')
                    ->required(),
                Textarea::make('context')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('auditable_type')
                    ->placeholder('-'),
                TextEntry::make('auditable_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('event'),
                TextEntry::make('summary'),
                TextEntry::make('context')
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
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('event')
                    ->searchable(),
                TextColumn::make('summary')
                    ->searchable(),
                TextColumn::make('auditable_type')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('auditable_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ManageAuditEntries::route('/'),
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
