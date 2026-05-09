@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<style>
@media print { .no-print { display: none !important; } }
.ref-link { text-decoration: none; font-weight: 500; }
.ref-link:hover { text-decoration: underline; }
</style>

<div class="tabs">
    <ul class="nav nav-tabs card-header-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'IL' ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'IL']) }}">Item Ledger</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'SR' ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'SR']) }}">Stock In Hand</a>
        </li>
    </ul>

    <div class="tab-content pt-3">

        {{-- ITEM LEDGER --}}
        <div id="IL" class="tab-pane fade {{ $tab === 'IL' ? 'show active' : '' }}">
            <form method="GET" class="border p-3 bg-light rounded mb-3 no-print">
                <input type="hidden" name="tab" value="IL">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="small fw-bold">Product <span class="text-danger">*</span></label>
                        <select name="item_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Product --</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}"
                                    {{ request('item_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold">From</label>
                        <input type="date" name="from_date" value="{{ $from }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold">To</label>
                        <input type="date" name="to_date" value="{{ $to }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm w-100"
                                onclick="exportPDF('il-table', 'Item Ledger', '{{ $from }} to {{ $to }}')">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
            </form>

            <div id="il-table">
                <table class="table table-sm table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th><th>Type</th><th>Reference</th>
                            <th class="text-end">Qty In</th>
                            <th class="text-end">Qty Out</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    @if (request('item_id'))
                        <tr class="table-info">
                            <td>{{ $from }}</td>
                            <td colspan="2" class="fw-bold">Opening Balance</td>
                            <td class="text-end">—</td><td class="text-end">—</td>
                            <td class="text-end fw-bold">{{ number_format($openingQty, 2) }}</td>
                        </tr>
                        @php $runningBalance = $openingQty; @endphp
                        @forelse ($itemLedger as $row)
                            @php
                                $qtyIn  = (float) $row['qty_in'];
                                $qtyOut = (float) $row['qty_out'];
                                $runningBalance += ($qtyIn - $qtyOut);
                                $desc = $row['description'];
                                $badgeClass = match ($row['type']) {
                                    'Purchase'        => 'bg-success',
                                    'Sale'            => 'bg-danger',
                                    'Purchase Return' => 'bg-warning text-dark',
                                    'Sale Return'     => 'bg-info text-dark',
                                    default           => 'bg-secondary',
                                };
                            @endphp
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td><span class="badge {{ $badgeClass }}">{{ $row['type'] }}</span></td>
                                <td>
                                    {{-- FIX: use str_replace (not ltrim) to extract IDs --}}
                                    @if (str_starts_with($desc, 'PI-'))
                                        @php $pid = (int) str_replace('PI-', '', $desc); @endphp
                                        <a href="{{ route('purchase_invoices.print', $pid) }}"
                                           target="_blank" class="ref-link text-success">{{ $desc }}</a>
                                        <a href="{{ route('purchase_invoices.edit', $pid) }}"
                                           class="ms-1 no-print text-secondary"><i class="fas fa-edit fa-xs"></i></a>
                                    @elseif (str_starts_with($desc, 'SI-'))
                                        @php $sid = (int) str_replace('SI-', '', $desc); @endphp
                                        <a href="{{ route('sale_invoices.print', $sid) }}"
                                           target="_blank" class="ref-link text-primary">{{ $desc }}</a>
                                        <a href="{{ route('sale_invoices.edit', $sid) }}"
                                           class="ms-1 no-print text-secondary"><i class="fas fa-edit fa-xs"></i></a>
                                    @elseif (str_starts_with($desc, 'PR-'))
                                        <span class="text-warning">{{ $desc }}</span>
                                    @elseif (str_starts_with($desc, 'SR-'))
                                        <span class="text-info">{{ $desc }}</span>
                                    @else
                                        {{ $desc }}
                                    @endif
                                </td>
                                <td class="text-end text-success">{{ $qtyIn  > 0 ? number_format($qtyIn,  2) : '—' }}</td>
                                <td class="text-end text-danger">{{ $qtyOut > 0 ? number_format($qtyOut, 2) : '—' }}</td>
                                <td class="text-end fw-bold">{{ number_format($runningBalance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-3 text-muted">No transactions in this period.</td></tr>
                        @endforelse
                        @if ($itemLedger->count() > 0)
                            <tr class="table-secondary fw-bold">
                                <td colspan="5" class="text-end">Closing Balance</td>
                                <td class="text-end">{{ number_format($runningBalance, 2) }}</td>
                            </tr>
                        @endif
                    @else
                        <tr><td colspan="6" class="text-center text-muted py-3">Please select a product to generate the ledger.</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- STOCK IN HAND --}}
        <div id="SR" class="tab-pane fade {{ $tab=='SR'?'show active':'' }}">

            <form method="GET" class="mb-3">
                <input type="hidden" name="tab" value="SR">
                <div class="row">
                    <div class="col-md-3">
                        <label>Product</label>
                        <select name="item_id" class="form-control select2-js">
                            <option value="">-- All Products --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                    {{ request('item_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Costing Method</label>
                        <select name="costing_method" class="form-control">
                            <option value="avg"    {{ request('costing_method','avg')=='avg'    ? 'selected':'' }}>Average</option>
                            <option value="latest" {{ request('costing_method','avg')=='latest' ? 'selected':'' }}>Latest</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $grandQty   = $stockInHand->sum('quantity');
                $grandTotal = $stockInHand->sum('total');
            @endphp

            @if(auth()->user()->hasRole('superadmin'))
            <div class="mb-3 text-end">
                <h4>Total Stock Value: <strong>PKR {{ number_format($grandTotal, 2) }}</strong></h4>
            </div>
            @endif

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        @if(auth()->user()->hasRole('superadmin'))
                            <th>Purchase Rate</th>
                            <th>Bilty/Unit</th>
                            <th>Total Rate</th>
                            <th>Stock Value</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockInHand as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row['product'] }}</td>
                            <td>{{ number_format($row['quantity'], 2) }}</td>
                            @if(auth()->user()->hasRole('superadmin'))
                                <td>{{ number_format($row['purchase_price'], 2) }}</td>
                                <td>
                                    @if($row['bilty_price'] > 0)
                                        <span class="text-success">
                                            + {{ number_format($row['bilty_price'], 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><strong>{{ number_format($row['price'], 2) }}</strong></td>
                                <td>{{ number_format($row['total'], 2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasRole('superadmin') ? 7 : 3 }}"
                                class="text-center">No stock found</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <th colspan="2" class="text-end">Grand Total</th>
                        <th>{{ number_format($grandQty, 2) }}</th>
                        @if(auth()->user()->hasRole('superadmin'))
                            <th>—</th>
                            <th>—</th>
                            <th>—</th>
                            <th>{{ number_format($grandTotal, 2) }}</th>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
</div>

<script>
function exportPDF(tableId, title, period) {
    const el = document.getElementById(tableId);
    if (!el) return;
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.no-print').forEach(e => e.remove());
    clone.querySelectorAll('.badge').forEach(b => {
        b.replaceWith(document.createTextNode(b.textContent.trim()));
    });
    clone.querySelectorAll('a').forEach(a => {
        a.replaceWith(document.createTextNode(a.textContent.trim()));
    });
    const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title>'
        + '<style>body{font-family:Arial,sans-serif;font-size:11px;margin:20px}'
        + 'h2{font-size:14px;margin-bottom:4px}p{font-size:10px;color:#555;margin:0 0 10px}'
        + 'table{width:100%;border-collapse:collapse}'
        + 'th{background:#1a1a2e;color:#fff;padding:5px 7px;text-align:left}'
        + 'td{padding:4px 7px;border-bottom:0.5px solid #ddd}'
        + 'tr:nth-child(even) td{background:#f9f9f9}'
        + 'tfoot td{background:#e9ecef;font-weight:bold}'
        + '.text-end{text-align:right}.fw-bold{font-weight:bold}</style></head><body>'
        + '<h2>' + title + '</h2><p>' + period + '</p>'
        + clone.innerHTML
        + '<script>window.onload=function(){window.print();}<\/script>'
        + '</body></html>';
    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(html);
    win.document.close();
}
</script>
@endsection