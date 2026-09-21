<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    public function getTitle(): string
    {
        return 'Sitios monitoreados';
    }

    public function getSubheading(): ?string
    {
        return 'Ordenados por prioridad: caídos primero, luego WP vulnerables';
    }
}