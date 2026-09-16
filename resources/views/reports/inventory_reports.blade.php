@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<style>
@media print { .no-print { display: none !important; } }
</style>

<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab=='IL'?'active':'' }}" data-bs-toggle="tab" href="#IL">
                Item Ledger
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab=='SR'?'active':'' }}" data-bs-toggle="tab" href="#SR">
                Stock In Hand
            </a>
        </li>
    </ul>

    <div class="tab-content mt-3">

        {{-- ================= ITEM LEDGER ================= --}}
        <div id="IL" class="tab-pane fade {{ $tab=='IL'?'show active':'' }}">
            <form method="GET" class="mb-3 no-print">
                <input type="hidden" name="tab" value="IL">
                <div class="row">
                    <div class="col-md-3">
                        <label>Product</label>
                        <select name="item_id" class="form-control select2-js" required>
                            <option value="">-- Select Product --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                    {{ request('item_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" name="from_date" value="{{ $from }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" name="to_date" value="{{ $to }}" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $totalIn  = $itemLedger->sum('qty_in');
                $totalOut = $itemLedger->sum('qty_out');
                $closing  = $openingQty + $totalIn - $totalOut;
            @endphp

            @if(request('item_id'))
            <div class="mb-3 text-end">
                <h5>Closing Balance: <span class="text-danger fw-bold">{{ number_format($closing, 2) }}</span></h5>
            </div>
            @endif

            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="text-end">Qty In</th>
                        <th class="text-end">Qty Out</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                @if(request('item_id'))
                    <tr class="table-warning fw-bold">
                        <td colspan="3">Opening Balance</td>
                        <td class="text-end text-success">{{ number_format($openingQty, 2) }}</td>
                        <td class="text-end">—</td>
                        <td class="text-end">{{ number_format($openingQty, 2) }}</td>
                    </tr>
                    @php $runningBal = $openingQty; @endphp
                    @forelse($itemLedger as $row)
                        @php
                            $qtyIn  = (float)($row->qty_in  ?? 0);
                            $qtyOut = (float)($row->qty_out ?? 0);
                            $runningBal += ($qtyIn - $qtyOut);
                            $badgeClass = match($row->type ?? '') {
                                'Purchase'        => 'bg-success',
                                'Sale'            => 'bg-danger',
                                'Customization'   => 'bg-dark',
                                'Purchase Return' => 'bg-warning text-dark',
                                'Sale Return'     => 'bg-info text-dark',
                                default           => 'bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td><span class="badge {{ $badgeClass }}">{{ $row->type }}</span></td>
                            <td>{{ $row->description }}</td>
                            <td class="text-end text-success">{{ $qtyIn  > 0 ? number_format($qtyIn,  2) : '—' }}</td>
                            <td class="text-end text-danger">{{ $qtyOut > 0 ? number_format($qtyOut, 2) : '—' }}</td>
                            <td class="text-end fw-bold">{{ number_format($runningBal, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No transactions in this period.</td></tr>
                    @endforelse
                    @if($itemLedger->count() > 0)
                    <tr class="table-secondary fw-bold">
                        <td colspan="3" class="text-end">Closing Balance</td>
                        <td class="text-end">{{ number_format($totalIn, 2) }}</td>
                        <td class="text-end">{{ number_format($totalOut, 2) }}</td>
                        <td class="text-end">{{ number_format($closing, 2) }}</td>
                    </tr>
                    @endif
                @else
                    <tr><td colspan="6" class="text-center text-muted py-3">Please select a product to generate the ledger.</td></tr>
                @endif
                </tbody>
            </table>
        </div>

        {{-- ================= STOCK IN HAND ================= --}}
        <div id="SR" class="tab-pane fade {{ $tab=='SR'?'show active':'' }}">
            <form method="GET" class="mb-3 no-print">
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
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="text-end">Quantity</th>
                        @if(auth()->user()->hasRole('superadmin'))
                            <th class="text-end">Purchase Rate</th>
                            <th class="text-end">Bilty/Unit</th>
                            <th class="text-end">Total Rate</th>
                            <th class="text-end">Stock Value</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockInHand as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row['product'] }}</td>
                            <td class="text-end">{{ number_format($row['quantity'], 2) }}</td>
                            @if(auth()->user()->hasRole('superadmin'))
                                <td class="text-end">{{ number_format($row['purchase_price'], 2) }}</td>
                                <td class="text-end">
                                    @if($row['bilty_price'] > 0)
                                        <span class="text-success">+ {{ number_format($row['bilty_price'], 2) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end"><strong>{{ number_format($row['price'], 2) }}</strong></td>
                                <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasRole('superadmin') ? 7 : 3 }}" class="text-center text-muted">
                                No stock found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="2" class="text-end">Grand Total</th>
                        <th class="text-end">{{ number_format($grandQty, 2) }}</th>
                        @if(auth()->user()->hasRole('superadmin'))
                            <th>—</th>
                            <th>—</th>
                            <th>—</th>
                            <th class="text-end">{{ number_format($grandTotal, 2) }}</th>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
</div>

<script>
$(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
});
</script>
@endsection