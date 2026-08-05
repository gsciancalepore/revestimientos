<?php

namespace App\Enums;

enum ProductSaleUnit: string
{
    case M2 = 'm2';
    case Unidad = 'unidad';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::M2 => 'Por m²',
            self::Unidad => 'Por unidad',
        };
    }
}
