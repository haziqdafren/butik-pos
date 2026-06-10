@props(['name', 'value' => ''])
@php
    $sizes    = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'All Size'];
    $isCustom = $value !== '' && !in_array($value, $sizes);
@endphp
<div data-size-wrapper>
    <select class="input" data-size-select onchange="sizeSelectChange(this)">
        @foreach($sizes as $s)
            <option value="{{ $s }}" @selected(!$isCustom && $value === $s)>{{ $s }}</option>
        @endforeach
        <option value="other" @selected($isCustom)>--- Lainnya (manual) ---</option>
    </select>
    <input class="input" style="margin-top:6px" type="text" data-size-custom
           @if(!$isCustom) hidden @endif
           value="{{ $isCustom ? $value : '' }}"
           placeholder="Isi ukuran manual (contoh: 32, 38, XL Jumbo)">
    <input type="hidden" name="{{ $name }}" value="{{ $value !== '' ? $value : 'S' }}">
</div>
