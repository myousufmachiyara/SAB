@extends('layouts.app')
@section('title', 'Sales Reports')

@section('content')
<style>
@media print { .no-print { display: none !important; } }
.ref-link { text-decoration: none; font-weight: 500; }
.ref-link:hover { text-decoration: underline; }
</style>

<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab==='SR'?'active':'' }}"
               href="{{ route('reports.sale', ['tab'=>'SR','from_date'=>$from,'to_date'=>$to]) }}">
               Sales Register
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab==='SRET'?'active':'' }}"
               href="{{ route('reports.sale', ['tab'=>'SRET','from_date'=>$from,'to_date'=>$to]) }}">
               Sales Return
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab==='CW'?'active':'' }}"
               href="{{ route('reports.sale', ['tab'=>'CW','from_date'=>$from,'to_date'=>$to]) }}">
               Customer Wise
            </a>
        </li>
        @if(auth()->user()->hasRole('superadmin'))
        <li class="nav-item">
            <a class="nav-link {{ $tab==='PR'?'active':'' }}"
               href="{{ route('reports.sale', ['tab'=>'PR','from_date'=>$from,'to_date'=>$to]) }}">
               Profit Report
            </a>
        </li>
        @endif
    </ul>

    <div class="tab-content mt-3">

        {{-- ================= SALES REGISTER ================= --}}
        <div id="SR" class="tab-pane fade {{ $tab==='SR'?'show active':'' }}">
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3 no-print">
                <input type="hidden" name="tab" value="SR">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="from_date" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="to_date" value="{{ $to }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <button type="button" class="btn btn-danger"
                            onclick="exportPDF('sr-table', 'Sales Register', '{{ $from }} to {{ $to }}')">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                </div>

                {{-- Replace the SR summary card @if block --}}
                @if($tab === 'SR' && $sales->count())
                @php $srTotal = $sales->sum('revenue'); @endphp
                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <div class="card card-featured-left card-featured-success w-100 mb-0">
                        <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="card-title text-dark d-block"
                                    style="font-size:11px;letter-spacing:1px;">TOTAL SALES</small>
                                <span class="text-dark" style="font-size:11px;">
                                    {{ \Carbon\Carbon::parse($from)->format('d M') }}
                                    — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                                </span>
                            </div>
                            <div class="text-end">
                                @if(auth()->user()->hasRole('superadmin'))
                                <h3 class="amount m-0 text-success fw-bold">
                                    {{ number_format($srTotal, 2) }}
                                    <small class="text-dark h6"> PKR</small>
                                </h3>
                                @endif
                                <small class="text-muted">{{ $sales->count() }} invoice(s)</small>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </form>

            <div id="sr-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th class="text-end">Total</th>
                            <th class="no-print text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($sales as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                            <td>
                                <a href="{{ route('sale_invoices.print', $row->id) }}"
                                   target="_blank" class="ref-link text-primary">
                                    {{ $row->invoice }}
                                </a>
                            </td>
                            <td>{{ $row->customer }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->revenue, 2) }}</td>
                            {{-- Inside the SR tab tbody, replace the actions td --}}
                            <td class="text-center no-print">
                                <a href="{{ route('sale_invoices.print', $row->id) }}"
                                    target="_blank" class="btn btn-outline-success btn-sm" title="Print">
                                    <i class="fas fa-print"></i>
                                </a>
                                @if(auth()->user()->hasRole('superadmin'))
                                <a href="{{ route('sale_invoices.edit', $row->id) }}"
                                    class="btn btn-outline-primary btn-sm ms-1" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No sales found.</td></tr>
                    @endforelse
                    </tbody>
                    @if($sales->count() > 0)
                    <tfoot class="table-success fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Grand Total:</td>
                            <td class="text-end">{{ number_format($sales->sum('revenue'), 2) }}</td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ================= SALES RETURN ================= --}}
        <div id="SRET" class="tab-pane fade {{ $tab==='SRET'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3 no-print">
                <input type="hidden" name="tab" value="SRET">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="from_date" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="to_date" value="{{ $to }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <button type="button" class="btn btn-danger"
                            onclick="exportPDF('sret-table', 'Sales Returns', '{{ $from }} to {{ $to }}')">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                </div>

                @if($tab === 'SRET' && $returns->count())
                @php $sretTotal = $returns->sum('total'); @endphp
                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <div class="card card-featured-left card-featured-danger w-100 mb-0">
                        <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="card-title text-dark d-block" style="font-size:11px;letter-spacing:1px;">TOTAL RETURNS</small>
                                <span class="text-dark" style="font-size:11px;">
                                    {{ \Carbon\Carbon::parse($from)->format('d M') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                                </span>
                            </div>
                            <div class="text-end">
                                <h3 class="amount m-0 text-danger fw-bold">
                                    {{ number_format($sretTotal, 2) }}
                                    <small class="text-dark h6"> PKR</small>
                                </h3>
                                <small class="text-muted">{{ $returns->count() }} return(s)</small>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </form>

            <div id="sret-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Return No</th>
                            <th>Customer</th>
                            <th class="text-end">Total Return</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($returns as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                            <td>SR-{{ $row->invoice }}</td>
                            <td>{{ $row->customer }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No returns found.</td></tr>
                    @endforelse
                    </tbody>
                    @if($returns->count() > 0)
                    <tfoot class="table-danger fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Grand Total:</td>
                            <td class="text-end">{{ number_format($returns->sum('total'), 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ================= CUSTOMER WISE ================= --}}
        <div id="CW" class="tab-pane fade {{ $tab==='CW'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3 no-print">
                <input type="hidden" name="tab" value="CW">
                <div class="col-md-2">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $from }}">
                </div>
                <div class="col-md-2">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $to }}">
                </div>
                <div class="col-md-3">
                    <label>Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">-- All Customers --</option>
                        @foreach($customers as $cust)
                            <option value="{{ $cust->id }}"
                                {{ request('customer_id') == $cust->id ? 'selected' : '' }}>
                                {{ $cust->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-primary w-100">Filter</button>
                    <button type="button" class="btn btn-danger"
                            onclick="exportPDF('cw-table', 'Customer-wise Sales', '{{ $from }} to {{ $to }}')">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                </div>

                @if($tab === 'CW' && $customerWise->count())
                @php
                    $cwTotal = $customerWise->sum('total_amount');
                    $cwQty   = $customerWise->sum('total_qty');
                @endphp
                <div class="col-md-3 d-flex align-items-end">
                    <div class="card card-featured-left card-featured-info w-100 mb-0">
                        <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="card-title text-dark d-block" style="font-size:11px;letter-spacing:1px;">TOTAL SALES</small>
                                <span class="text-dark" style="font-size:11px;">
                                    Qty: <strong>{{ number_format($cwQty, 2) }}</strong>
                                </span>
                            </div>
                            <div class="text-end">
                                <h3 class="amount m-0 text-info fw-bold">
                                    {{ number_format($cwTotal, 2) }}
                                    <small class="text-dark h6"> PKR</small>
                                </h3>
                                <small class="text-muted">{{ $customerWise->count() }} customer(s)</small>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </form>

            <div id="cw-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Customer</th>
                            <th>Invoice Date</th>
                            <th>Invoice No</th>
                            <th>Item</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($customerWise as $custData)
                        <tr class="table-secondary">
                            <td colspan="7"><strong>{{ $custData->customer_name }}</strong></td>
                        </tr>
                        @foreach($custData->items as $item)
                            <tr>
                                <td></td>
                                <td>{{ \Carbon\Carbon::parse($item->invoice_date)->format('d-m-Y') }}</td>
                                <td>{{ $item->invoice_no }}</td>
                                <td>{{ $item->item_name }}</td>
                                <td class="text-end">{{ number_format($item->quantity, 2) }}</td>
                                <td class="text-end">{{ number_format($item->rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold table-light">
                            <td colspan="4" class="text-end">Customer Total</td>
                            <td class="text-end">{{ number_format($custData->total_qty, 2) }}</td>
                            <td class="text-end">—</td>
                            <td class="text-end">{{ number_format($custData->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No customer sale data found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                    @if($customerWise->count() > 0)
                    <tfoot class="table-primary fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Grand Total</td>
                            <td class="text-end">{{ number_format($customerWise->sum('total_qty'), 2) }}</td>
                            <td class="text-end">—</td>
                            <td class="text-end">{{ number_format($customerWise->sum('total_amount'), 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ================= PROFIT REPORT ================= --}}
        @if(auth()->user()->hasRole('superadmin'))
        <div id="PR" class="tab-pane fade {{ $tab==='PR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3 no-print">
                <input type="hidden" name="tab" value="PR">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="from_date" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="to_date" value="{{ $to }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Calculate Profit</button>
                </div>

                @if($tab === 'PR' && $sales->count())
                @php
                    $prRevenue = $sales->sum('revenue');
                    $prCost    = $sales->sum('cost');
                    $prProfit  = $sales->sum('profit');
                    $prMargin  = $prRevenue > 0 ? ($prProfit / $prRevenue) * 100 : 0;
                @endphp
                <div class="col-md-4 d-flex align-items-end">
                    <div class="card card-featured-left {{ $prProfit >= 0 ? 'card-featured-success' : 'card-featured-danger' }} w-100 mb-0">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <small class="card-title text-dark d-block" style="font-size:11px;letter-spacing:1px;">NET PROFIT</small>
                                    <small class="text-dark">
                                        Revenue: <strong>{{ number_format($prRevenue, 2) }}</strong> |
                                        Cost: <strong>{{ number_format($prCost, 2) }}</strong>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <h3 class="amount m-0 {{ $prProfit >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                        {{ number_format($prProfit, 2) }}
                                        <small class="text-dark h6"> PKR</small>
                                    </h3>
                                    <small class="text-muted">Margin: {{ number_format($prMargin, 1) }}%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </form>

            <table class="table table-bordered table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th class="text-end">Sale (Net)</th>
                        <th class="text-end">Landed Cost</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">Margin %</th>
                        <th class="no-print text-center">Detail</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sales as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                        <td>{{ $row->invoice }}</td>
                        <td>{{ $row->customer }}</td>
                        <td class="text-end">{{ number_format($row->revenue, 2) }}</td>
                        <td class="text-end">{{ number_format($row->cost, 2) }}</td>
                        <td class="text-end fw-bold {{ $row->profit >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($row->profit, 2) }}
                        </td>
                        <td class="text-end">{{ number_format($row->margin, 1) }}%</td>
                        <td class="text-center no-print">
                            <a href="{{ route('reports.print-profit', ['id' => $row->id]) }}"
                               target="_blank"
                               class="btn btn-sm btn-danger" title="Detailed PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">No data found. Click Calculate Profit to load.</td></tr>
                @endforelse
                </tbody>
                @if($sales->count() > 0)
                @php
                    $ftRevenue = $sales->sum('revenue');
                    $ftCost    = $sales->sum('cost');
                    $ftProfit  = $sales->sum('profit');
                    $ftMargin  = $ftRevenue > 0 ? ($ftProfit / $ftRevenue) * 100 : 0;
                @endphp
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Totals:</td>
                        <td class="text-end">{{ number_format($ftRevenue, 2) }}</td>
                        <td class="text-end">{{ number_format($ftCost, 2) }}</td>
                        <td class="text-end {{ $ftProfit >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($ftProfit, 2) }}
                        </td>
                        <td class="text-end">{{ number_format($ftMargin, 1) }}%</td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        @endif

    </div>
</div>

<script>
function exportPDF(tableId, title, period) {
    const el = document.getElementById(tableId);
    if (!el) return;
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.no-print').forEach(e => e.remove());
    clone.querySelectorAll('a').forEach(a => {
        const span = document.createElement('span');
        span.textContent = a.textContent.trim();
        a.replaceWith(span);
    });
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title>
    <style>
        body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
        h2{font-size:14px;margin-bottom:4px}p{font-size:10px;color:#555;margin:0 0 10px}
        table{width:100%;border-collapse:collapse}
        th{background:#1a1a2e;color:#fff;padding:5px 7px;text-align:left}
        td{padding:4px 7px;border-bottom:0.5px solid #ddd}
        tr:nth-child(even) td{background:#f9f9f9}
        .text-end{text-align:right}.text-center{text-align:center}.fw-bold{font-weight:bold}
        tfoot td{background:#f0f0f0;font-weight:bold}
    </style></head><body>
    <h2>${title}</h2><p>${period}</p>
    ${clone.innerHTML}
    <script>window.onload=function(){window.print();}<\/script>
    </body></html>`;
    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(html);
    win.document.close();
}
</script>
@endsection