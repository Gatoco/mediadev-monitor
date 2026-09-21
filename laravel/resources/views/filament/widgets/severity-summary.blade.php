<x-filament-widgets::widget>
    <div class="mdm-severity-summary">
        <div class="mdm-severity-card mdm-severity-card--red">
            <span class="mdm-severity-count mdm-severity-count--red">{{ $red }}</span>
            <span class="mdm-severity-label">WP crítico (red)</span>
        </div>
        <div class="mdm-severity-card mdm-severity-card--yellow">
            <span class="mdm-severity-count mdm-severity-count--yellow">{{ $yellow }}</span>
            <span class="mdm-severity-label">WP desactualizado (yellow)</span>
        </div>
        <div class="mdm-severity-card mdm-severity-card--green">
            <span class="mdm-severity-count mdm-severity-count--green">{{ $green }}</span>
            <span class="mdm-severity-label">WP al día (green)</span>
        </div>
    </div>
</x-filament-widgets::widget>