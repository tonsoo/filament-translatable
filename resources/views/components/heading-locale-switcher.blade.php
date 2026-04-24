@php
    $headingHtml = $heading instanceof \Illuminate\Contracts\Support\Htmlable ? $heading->toHtml() : e((string) $heading);
@endphp

<div style="display: flex; column-gap: 0.75rem;">
    <span>{!! $headingHtml !!}</span>

    <x-filament::input.wrapper style="margin-inline-start: 0.5rem;">
        <x-filament::input.select wire:model.live="translatableLocale">
            @foreach ($options as $value => $label)
                <option value="{{ $value }}" @selected($value === $activeLocale)>{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
