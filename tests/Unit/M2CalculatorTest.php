<?php

use App\Services\M2Calculator;

it('calcula los m² a partir de dimensiones en centímetros', function () {
    $calculator = new M2Calculator;

    expect($calculator->m2DesdeDimensiones('60', '60'))->toBe('0.36');
    expect($calculator->m2DesdeDimensiones('100', '100'))->toBe('1.00');
    expect($calculator->m2DesdeDimensiones('120', '60'))->toBe('0.72');
});

it('aplica el porcentaje de desperdicio por defecto del 10 %', function () {
    $calculator = new M2Calculator;

    expect($calculator->aplicarDesperdicio('20'))->toBe('22.00');
    expect($calculator->aplicarDesperdicio('22.00'))->toBe('24.20');
});

it('aplica un porcentaje de desperdicio personalizado', function () {
    $calculator = new M2Calculator;

    expect($calculator->aplicarDesperdicio('20', '15'))->toBe('23.00');
});

it('calcula las cajas necesarias redondeando hacia arriba', function () {
    $calculator = new M2Calculator;

    expect($calculator->cajasNecesarias('20', '1.15'))->toBe(18);
    expect($calculator->cajasNecesarias('2.3', '1.15'))->toBe(2);
    expect($calculator->cajasNecesarias('0.5', '1.15'))->toBe(1);
});

it('rechaza dimensiones menores o iguales a cero', function (string $largo, string $ancho) {
    $calculator = new M2Calculator;

    expect(fn () => $calculator->m2DesdeDimensiones($largo, $ancho))->toThrow(InvalidArgumentException::class);
})->with([
    'superficie cero' => ['0', '60'],
    'largo cero' => ['0', '60'],
    'ancho cero' => ['60', '0'],
]);

it('rechaza superficies o m² por caja menores o iguales a cero', function (string $m2, string $m2PorCaja) {
    $calculator = new M2Calculator;

    expect(fn () => $calculator->cajasNecesarias($m2, $m2PorCaja))->toThrow(InvalidArgumentException::class);
})->with([
    'superficie cero' => ['0', '1.15'],
    'm² por caja cero' => ['20', '0'],
]);
