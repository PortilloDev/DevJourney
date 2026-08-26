<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use App\Services\SiteSettingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Group::make()->columnSpan(2)->schema([
                    Forms\Components\Section::make('Content')->schema([
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
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-friendly identifier. Auto-filled from the title.'),
                        Forms\Components\Textarea::make('excerpt')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Short summary shown in listings and meta description fallback.'),
                        Forms\Components\MarkdownEditor::make('body_md')
                            ->label('Body (Markdown)')
                            ->required()
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('posts'),
                    ]),
                    Forms\Components\Section::make('SEO')->collapsed()->schema([
                        Forms\Components\TextInput::make('seo_title')->maxLength(255),
                        Forms\Components\Textarea::make('seo_description')->rows(2)->maxLength(255),
                    ]),
                ]),
                Forms\Components\Group::make()->columnSpan(1)->schema([
                    Forms\Components\Section::make('Publishing')->schema([
                        Forms\Components\Select::make('status')
                            ->options(PostStatus::options())
                            ->default(PostStatus::Draft->value)
                            ->required()
                            ->live(),
                        Forms\Components\Toggle::make('featured')
                            ->label('Featured')
                            ->helperText('Show this post in the highlighted row on the home page and the journal.')
                            ->default(false)
                            ->inline(false),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->helperText('Leave empty for now; set a future date to schedule.')
                            ->default(now()),
                        Forms\Components\Select::make('english_level')
                            ->options(EnglishLevel::options())
                            ->default(fn () => app(SiteSettingService::class)->get('current_english_level', EnglishLevel::B1->value))
                            ->required()
                            ->helperText('Auto-suggested from your current level; editable per post.'),
                    ]),
                    Forms\Components\Section::make('Taxonomy')->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                            ]),
                        Forms\Components\Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                            ]),
                    ]),
                    Forms\Components\Section::make('Media')->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->image()
                            ->disk('public')
                            ->directory('posts')
                            ->imageEditor(),
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
                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('english_level')
                    ->label('EN')
                    ->badge()
                    ->formatStateUsing(fn (EnglishLevel $state) => $state->value),
                Tables\Columns\IconColumn::make('featured')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->width(24)
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PostStatus $state) => match ($state) {
                        PostStatus::Published => 'success',
                        PostStatus::Draft => 'gray',
                        PostStatus::Archived => 'warning',
                    }),
                Tables\Columns\TextColumn::make('reading_minutes')
                    ->label('Min')
                    ->suffix(' min')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PostStatus::options()),
                Tables\Filters\TernaryFilter::make('featured')->label('Featured'),
                Tables\Filters\SelectFilter::make('english_level')->options(EnglishLevel::options()),
                Tables\Filters\SelectFilter::make('category')->relationship('category', 'name'),
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
