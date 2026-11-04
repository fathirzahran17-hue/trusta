{{-- Pilihan barang berkelompok. Param: $products, $context (purchase|sales|input|output), $priceField (opsional), $stockMap (opsional), $withUnit (opsional) --}}
@foreach(\App\Support\ItemKind::groups($products, $context ?? 'purchase') as $group)
@if($group['label'])<optgroup label="{{ $group['label'] }}">@endif
@foreach($group['items'] as $prod)<option value="{{ $prod->id }}"@isset($priceField) data-price="{{ $prod->{$priceField} }}"@endisset data-unit="{{ $prod->unit }}" data-type="{{ $prod->type }}"@isset($stockMap) data-stock="{{ $stockMap[$prod->id] ?? 0 }}"@endisset @if($group['warn']) data-warn="1"@endif @if(isset($selected) && $selected == $prod->id) selected @endif>{{ $prod->sku ? $prod->sku . ' - ' : '' }}{{ $prod->name }}@if($withUnit ?? false) ({{ $prod->unit }})@endif</option>
@endforeach
@if($group['label'])</optgroup>@endif
@endforeach
