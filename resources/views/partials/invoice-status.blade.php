{{-- Lencana status faktur. Param: $invoice --}}
@php
    $st = $invoice->status;
    $late = in_array($st, ['unpaid', 'partial']) && $invoice->due_date && $invoice->due_date->lt(today());
    $days = $late ? (int) $invoice->due_date->diffInDays(today()) : 0;
@endphp
@if($st === 'paid') <span class="st st-paid">Lunas</span>
@elseif($st === 'void') <span class="st st-void">Dibatalkan</span>
@else
    <span class="st {{ $st === 'partial' ? 'st-partial' : 'st-unpaid' }}">{{ $st === 'partial' ? 'Dibayar Sebagian' : 'Belum Lunas' }}</span>
    @if($late) <span class="st st-late" title="Lewat jatuh tempo">Terlambat {{ $days }} hari</span> @endif
@endif
