<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Leyenda de estados — qué significa cada color/badge del monitor.
 */
class StateLegend extends Widget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.state-legend';
}
