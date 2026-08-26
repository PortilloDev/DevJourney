<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SiteSettingResource\Pages;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('e.g. hero_tagline, current_english_level, social_github.'),
            Forms\Components\Select::make('type')
                ->options([
                    'text' => 'Text',
                    'textarea' => 'Textarea',
                    'boolean' => 'Boolean',
                    'json' => 'JSON',
                ])
                ->default('text')
                ->required()
                ->live(),
            Forms\Components\TextInput::make('group')->default('general')->required(),
            Forms\Components\Textarea::make('value')
                ->required()
                ->rows(fn (Forms\Get $get) => $get('type') === 'textarea' ? 6 : 3)
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (is_bool($state) ? ($state ? 'true' : 'false') : (string) $state))
                ->dehydrateStateUsing(fn ($state, Forms\Get $get) => $get('type') === 'boolean' ? filter_var($state, FILTER_VALIDATE_BOOLEAN) : $state)
                ->helperText('Stored as JSON under the hood. Use true/false for boolean settings.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('group')
            ->groups(['group'])
            ->columns([
                Tables\Columns\TextColumn::make('key')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('group')->badge()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('value')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state)
                    ->limit(50),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteSettings::route('/'),
            'create' => Pages\CreateSiteSetting::route('/create'),
            'edit' => Pages\EditSiteSetting::route('/{record}/edit'),
        ];
    }
}
