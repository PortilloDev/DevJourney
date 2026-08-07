<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeTopic;
use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Filament\Resources\ChallengeResource\Pages;
use App\Models\Challenge;
use App\Services\SiteSettingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ChallengeResource extends Resource
{
    protected static ?string $model = Challenge::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Group::make()->columnSpan(2)->schema([
                    Forms\Components\Section::make('Challenge')->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),
                    Forms\Components\Section::make('Question → Solution → Explanation')->schema([
                        Forms\Components\MarkdownEditor::make('question_md')
                            ->label('Problem statement (Markdown)')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\MarkdownEditor::make('answer_md')
                            ->label('Solution (Markdown)')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Hidden behind a toggle on the public page.'),
                        Forms\Components\MarkdownEditor::make('explanation_md')
                            ->label('Explanation (Markdown, optional)')
                            ->columnSpanFull(),
                    ]),
                ]),
                Forms\Components\Group::make()->columnSpan(1)->schema([
                    Forms\Components\Section::make('Classification')->schema([
                        Forms\Components\Select::make('topic')
                            ->options(ChallengeTopic::options())
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('difficulty')
                            ->options(ChallengeDifficulty::options())
                            ->required(),
                        Forms\Components\Select::make('english_level')
                            ->options(EnglishLevel::options())
                            ->default(fn () => app(SiteSettingService::class)->get('current_english_level', EnglishLevel::B1->value))
                            ->required(),
                        Forms\Components\Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                            ]),
                    ]),
                    Forms\Components\Section::make('Publishing')->schema([
                        Forms\Components\Select::make('status')
                            ->options(PostStatus::options())
                            ->default(PostStatus::Draft->value)
                            ->required(),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->default(now()),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('topic')
                    ->badge()
                    ->formatStateUsing(fn (ChallengeTopic $state) => $state->label())
                    ->sortable(),
                Tables\Columns\TextColumn::make('difficulty')
                    ->badge()
                    ->formatStateUsing(fn (ChallengeDifficulty $state) => $state->label())
                    ->color(fn (ChallengeDifficulty $state) => match ($state) {
                        ChallengeDifficulty::Beginner => 'success',
                        ChallengeDifficulty::Intermediate => 'warning',
                        ChallengeDifficulty::Advanced => 'danger',
                        ChallengeDifficulty::Expert => 'danger',
                    }),
                Tables\Columns\TextColumn::make('english_level')
                    ->label('EN')
                    ->badge()
                    ->formatStateUsing(fn (EnglishLevel $state) => $state->value),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PostStatus $state) => $state === PostStatus::Published ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('topic')->options(ChallengeTopic::options()),
                Tables\Filters\SelectFilter::make('difficulty')->options(ChallengeDifficulty::options()),
                Tables\Filters\SelectFilter::make('status')->options(PostStatus::options()),
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
            'index' => Pages\ListChallenges::route('/'),
            'create' => Pages\CreateChallenge::route('/create'),
            'edit' => Pages\EditChallenge::route('/{record}/edit'),
        ];
    }
}
