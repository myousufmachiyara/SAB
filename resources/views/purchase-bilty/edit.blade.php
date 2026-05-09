@extends('layouts.app')
@section('title', 'Purchase | Edit Bilty')
@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_bilty.update', $bilty->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Bilty</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-2 mb-3">
              <label>Bilty Date</label>
              <input type="date" name="bilty_date" class="form-control"
                     value="{{ $bilty->bilty_date }}" required>
            </div>
            <div class="col-md-3 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}"
                    {{ $bilty->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2 mb-3">
              <label>Ref #</label>
              <input type="text" name="ref_no" class="form-control" value="{{ $bilty->ref_no }}">
            </div>
            <div class="col-md-3 mb-3">
              <label>Purchase Invoice</label>
              <select name="purchase_id" class="form-control select2-js">
                <option value="">Select Invoice</option>
                @foreach ($purchaseInvoices as $inv)
                  <option value="{{ $inv->id }}"
                    {{ $bilty->purchase_id == $inv->id ? 'selected' : '' }}>
                    {{ $inv->invoice_no }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $bilty->remarks }}</textarea>
            </div>
          </div>

          {{-- Items Table --}}
          <div class="table-responsive mb-3">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Est. Bilty (auto)</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="BiltyItemTable">
                @foreach ($bilty->details as $i => $detail)
                  <tr>
                    <td>
                      <select name="items[{{ $i }}][item_id]"
                              id="item_name{{ $i + 1 }}"
                              class="form-control select2-js product-select"
                              onchange="onItemNameChange(this)">
                        <option value="">Select Item</option>
                        @foreach ($products as $product)
                          <option value="{{ $product->id }}"
                                  data-unit-id="{{ $product->measurement_unit }}"
                                  {{ $detail->item_id == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <input type="number"
                             name="items[{{ $i }}][quantity]"
                             id="bilty_qty{{ $i + 1 }}"
                             class="form-control quantity"
                             value="{{ $detail->quantity }}"
                             step="any"
                             onchange="onQtyChange()">
                    </td>
                    <td>
                      <select name="items[{{ $i }}][unit]"
                              id="unit{{ $i + 1 }}"
                              class="form-control select2-js" required>
                        <option value="">-- Select --</option>
                        @foreach ($units as $unit)
                          <option value="{{ $unit->id }}"
                            {{ $detail->unit == $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }} ({{ $unit->shortcode }})
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <input type="hidden"
                             name="items[{{ $i }}][price]"
                             id="bilty_price{{ $i + 1 }}"
                             value="{{ $detail->price ?? 0 }}">
                      <input type="number"
                             id="amount{{ $i + 1 }}"
                             class="form-control row-amount"
                             value="{{ number_format($detail->quantity * ($detail->price ?? 0), 2, '.', '') }}"
                             disabled>
                    </td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                        <i class="fas fa-times"></i>
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow_btn()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          {{-- Summary --}}
          <div class="row mb-3 align-items-end">
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="number" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>
            <div class="col-md-2">
              <label>Bilty Amount <span class="text-danger">*</span></label>
              <input type="number" name="bilty_amount" id="payable_amount"
                     class="form-control" step="any" required
                     value="{{ $bilty->bilty_amount }}">
            </div>
            <div class="col-md-2">
              <label>Per-unit Charge</label>
              <input type="text" id="per_unit_display" class="form-control bg-light" disabled>
            </div>
            <div class="col-md-2">
              <label>Est. Total</label>
              <input type="number" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>
            <div class="col-md-4 text-end">
              <h4 class="mb-0">Bilty Amount: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Update Bilty
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var units    = @json($units);
  var index    = {{ count($bilty->details) + 1 }};

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    recalcAll();
  });

  function onItemNameChange(el) {
    const unitId  = el.options[el.selectedIndex].getAttribute('data-unit-id');
    const idMatch = el.id.match(/\d+$/);
    if (!idMatch) return;
    $(`#unit${idMatch[0]}`).val(String(unitId)).trigger('change.select2');
    recalcAll();
  }

  function onQtyChange() { recalcAll(); }

  function removeRow(btn) {
    if ($('#BiltyItemTable tr').length > 1) {
      $(btn).closest('tr').remove();
      recalcAll();
    }
  }

  function addNewRow_btn() {
    let rowIndex = index - 1;
    let row = `
      <tr>
        <td>
          <select name="items[${rowIndex}][item_id]"
                  id="item_name${index}"
                  class="form-control select2-js"
                  onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(p =>
              `<option value="${p.id}" data-unit-id="${p.measurement_unit}">${p.name}</option>`
            ).join('')}
          </select>
        </td>
        <td>
          <input type="number" name="items[${rowIndex}][quantity]"
                 id="bilty_qty${index}" class="form-control quantity"
                 value="0" step="any" onchange="onQtyChange()">
        </td>
        <td>
          <select name="items[${rowIndex}][unit]" id="unit${index}"
                  class="form-control select2-js" required>
            ${units.map(u =>
              `<option value="${u.id}">${u.name} (${u.shortcode})</option>`
            ).join('')}
          </select>
        </td>
        <td>
          <input type="hidden" name="items[${rowIndex}][price]"
                 id="bilty_price${index}" value="0">
          <input type="number" id="amount${index}"
                 class="form-control row-amount" value="0" disabled>
        </td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
            <i class="fas fa-times"></i>
          </button>
        </td>
      </tr>`;
    $('#BiltyItemTable').append(row);
    $(`#item_name${index}`).select2({ width: '100%' });
    $(`#unit${index}`).select2({ width: '100%' });
    index++;
    recalcAll();
  }

  function recalcAll() {
    let biltyAmt = parseFloat($('#payable_amount').val()) || 0;
    let totalQty = 0;

    $('#BiltyItemTable tr').each(function () {
      totalQty += parseFloat($(this).find('input.quantity').val()) || 0;
    });

    let perUnit  = totalQty > 0 ? biltyAmt / totalQty : 0;
    let estTotal = 0;

    $('#BiltyItemTable tr').each(function () {
      let qty    = parseFloat($(this).find('input.quantity').val()) || 0;
      let rowAmt = qty * perUnit;
      estTotal  += rowAmt;
      $(this).find('input[id^="bilty_price"]').val(perUnit.toFixed(4));
      $(this).find('input[id^="amount"]').val(rowAmt.toFixed(2));
    });

    $('#total_quantity').val(totalQty.toFixed(2));
    $('#total_quantity_show').val(totalQty.toFixed(2));
    $('#totalAmount').val(estTotal.toFixed(2));
    $('#total_amount_show').val(estTotal.toFixed(2));
    $('#per_unit_display').val(totalQty > 0 ? 'PKR ' + fmt(perUnit.toFixed(4)) : '—');
    $('#netTotal').text(fmt(biltyAmt.toFixed(2)));
    $('#net_amount').val(biltyAmt.toFixed(2));
  }

  $('#payable_amount').on('input', recalcAll);

  function fmt(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
</script>
@endsection