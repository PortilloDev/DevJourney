<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\Post;
use App\Services\Rag\RagService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $modelLabel = 'reference document';

    protected static ?string $pluralModelLabel = 'reference documents';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Select::make('post_id')
                ->label('Linked post')
                ->options(fn () => Post::query()->latestPublished()->pluck('title', 'id'))
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Optional — index this document alongside this post.'),
            Forms\Components\FileUpload::make('path')
                ->label('PDF file')
                ->disk('local')
                ->directory('documents')
                ->acceptedFileTypes(['application/pdf'])
                ->storeFileNamesIn('filename')
                ->preserveFilenames()
                ->required()
                ->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'indexed' => 'Indexed',
                    'failed' => 'Failed',
                ])
                ->default('pending')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('filename')->limit(30),
                Tables\Columns\TextColumn::make('post.title')->label('Post')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('size')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state / 1024, 1).' KB' : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'indexed' => 'success',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('indexed_at')->dateTime('M j, Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'indexed' => 'Indexed',
                    'failed' => 'Failed',
                ]),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->after(fn (Document $record) => app(RagService::class)->deleteDocument($record)),
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
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
