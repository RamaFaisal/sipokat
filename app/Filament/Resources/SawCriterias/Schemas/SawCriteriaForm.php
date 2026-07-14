<?php

namespace App\Filament\Resources\SawCriterias\Schemas;

use App\Models\SawCriteria;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SawCriteriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Kriteria')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Kode')
                                    ->required()
                                    ->maxLength(10)
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),
                                TextInput::make('name')
                                    ->label('Nama Kriteria')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('type')
                                    ->label('Jenis')
                                    ->options([
                                        'cost' => 'Cost (nilai rendah lebih prioritas)',
                                        'benefit' => 'Benefit (nilai tinggi lebih prioritas)',
                                    ])
                                    ->required()
                                    ->helperText('Jenis kriteria pada RAW input.'),
                                TextInput::make('weight')
                                    ->label('Bobot')
                                    ->required()
                                    ->numeric()
                                    ->step(0.001)
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->helperText('Total bobot kriteria aktif harus = 1.000')
                                    ->rules([
                                        static function (?SawCriteria $record): Closure {
                                            return static function (string $attribute, $value, Closure $fail) use ($record): void {
                                                $newWeight = (float) $value;

                                                if ($record && ! $record->is_active) {
                                                    return;
                                                }

                                                $othersTotal = (float) SawCriteria::query()
                                                    ->where('is_active', true)
                                                    ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                    ->sum('weight');

                                                $projected = round($othersTotal + $newWeight, 3);

                                                if ($projected > 1.000) {
                                                    $fail(sprintf(
                                                        'Total bobot kriteria aktif akan jadi %.3f (maks 1.000). Bobot kriteria aktif lain: %.3f. Turunkan bobot.',
                                                        $projected,
                                                        $othersTotal,
                                                    ));
                                                }
                                            };
                                        },
                                    ]),
                                TextInput::make('sort_order')
                                    ->label('Urutan Tampil')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Posisi tampil di list (kecil = atas).'),
                            ]),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Penjelasan singkat tentang kriteria ini.'),
                    ]),

                Section::make('Aturan Skala 1-5')
                    ->description('Konversi nilai mentah ke skor 1-5. Kosongkan "Min" untuk batas bawah terbuka (≤ Max), kosongkan "Max" untuk batas atas terbuka (≥ Min).')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('scale_rules')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('min')
                                    ->label('Min')
                                    ->numeric()
                                    ->placeholder('null = open')
                                    ->rule(static function (Get $get): Closure {
                                        return static function (string $attribute, $value, Closure $fail) use ($get): void {
                                            $max = $get('max');
                                            if ($value !== null && $value !== '' && $max !== null && $max !== '' && (float) $value > (float) $max) {
                                                $fail("Min ({$value}) tidak boleh lebih besar dari Max ({$max}).");
                                            }
                                        };
                                    }),
                                TextInput::make('max')
                                    ->label('Max')
                                    ->numeric()
                                    ->placeholder('null = open')
                                    ->rule(static function (Get $get): Closure {
                                        return static function (string $attribute, $value, Closure $fail) use ($get): void {
                                            $min = $get('min');
                                            if ($value !== null && $value !== '' && $min !== null && $min !== '' && (float) $value < (float) $min) {
                                                $fail("Max ({$value}) tidak boleh lebih kecil dari Min ({$min}).");
                                            }
                                        };
                                    }),
                                TextInput::make('score')
                                    ->label('Skor (1-5)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(5)
                                    ->helperText('Hanya 1–5'),
                            ])
                            ->columns(3)
                            ->defaultItems(5)
                            ->addActionLabel('Tambah Range')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['score'])
                                ? "Skor {$state['score']}: " . ($state['min'] ?? '−∞') . ' s/d ' . ($state['max'] ?? '+∞')
                                : null
                            ),
                    ]),
            ]);
    }
}
