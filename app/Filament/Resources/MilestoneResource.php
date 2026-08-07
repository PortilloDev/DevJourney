<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\MilestoneType;
use App\Filament\Resources\MilestoneResource\Pages;
use App\Models\Milestone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MilestoneResource extends Resource
{
    protected static ?string $model = Milestone::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->rows(3),
            Forms\Components\DatePicker::make('achieved_at')->required()->default(now()),
            Forms\Components\Select::make('type')
                ->options(MilestoneType::options())
                ->required(),
            Forms\Components\TextInput::make('icon')
                ->helperText('Optional Heroicon name, e.g. heroicon-o-academic-cap.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('achieved_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('achieved_at')->date('M j, Y')->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (MilestoneType $state) => $state->label()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(MilestoneType::options()),
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
            'index' => Pages\ListMilestones::route('/'),
            'create' => Pages\CreateMilestone::route('/create'),
            'edit' => Pages\EditMilestone::route('/{record}/edit'),
        ];
    }
}
