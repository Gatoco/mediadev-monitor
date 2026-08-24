<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
            <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full bg-success-500"></span>
                <span><strong>wp-full</strong> — sitio WordPress sano</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full bg-warning-500"></span>
                <span><strong>wp-degraded</strong> — WP con endpoints restringidos</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full bg-info-500"></span>
                <span><strong>non-wp</strong> — sitio sin WordPress (solo uptime)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full bg-danger-500"></span>
                <span><strong>down</strong> — no responde (3 fallos seguidos)</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full bg-gray-500"></span>
                <span><strong>unknown</strong> — aún sin clasificar</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
