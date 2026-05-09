@extends('layouts.app')
@section('title', 'Purchase Bilty | All Invoices')
@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @elseif (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">All Purchase Bilty</h2>
        <a href="{{ route('purchase_bilty.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> New Bilty
        </a>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="purchaseBiltyTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Date</th>
                <th>Vendor</th>
                <th>Ref No</th>
                <th>Total Qty</th>
                <th>Bilty Amount</th>
                <th>Remarks</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($invoices as $index => $invoice)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($invoice->bilty_date)->format('d-M-Y') }}</td>
                <td>{{ $invoice->vendor->name ?? 'N/A' }}</td>
                <td>{{ $invoice->ref_no ?? '—' }}</td>
                <td>{{ number_format($invoice->total_qty, 2) }}</td>
                <td>{{ number_format($invoice->bilty_amount, 2) }}</td>
                <td>{{ $invoice->remarks ?? '—' }}</td>
                <td>
                  <a href="{{ route('purchase_bilty.edit', $invoice->id) }}" class="text-primary">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="{{ route('purchase_bilty.print', $invoice->id) }}" target="_blank" class="text-success ms-2">
                    <i class="fas fa-print"></i>
                  </a>
                  <form action="{{ route('purchase_bilty.destroy', $invoice->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 ms-2 text-danger"
                            onclick="return confirm('Delete this bilty?')">
                      <i class="fa fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>
<script>
  $(document).ready(function () {
    $('#purchaseBiltyTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
  });
</script>
@endsection