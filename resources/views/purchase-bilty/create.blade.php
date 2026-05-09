@extends('layouts.app')
@section('title', 'Purchase | New Bilty')
@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_bilty.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
          </ul>
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Purchase Bilty</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-2 mb-3">
              <label>Date</label>
              <input type="date" name="bilty_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-2 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control">
            </div>
            <div class="col-md-3 mb-3">
              <label>Purchase #</label>
              <div class="input-group">
                <select name="bilty_purchase_id" id="bilty_purchase_id" class="form-control select2-js">
                  <option value="">Select Purchase #</option>
                  @foreach ($purchaseInvoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->invoice_no }}</option>
                  @endforeach
                </select>
                <button type="button" class="btn btn-info" onclick="fetchInvoiceProducts()">
                  <i class="fas fa-sync"></i>
                </button>
              </div>
            </div>
            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>
            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
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
                <tr>
                  <td>
                    <select name="items[0][item_id]" id="item_name1"
                            class="form-control select2-js product-select"
                            onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-unit-id="{{ $product->measurement_unit }}"
                                data-bilty-charges="{{ $product->bilty_charges ?? 0 }}">
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" name="items[0][quantity]" id="bilty_qty1"
                           class="form-control quantity" value="0" step="any"
                           onchange="onQtyChange()">
                  </td>
                  <td>
                    <select name="items[0][unit]" id="unit1" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    {{-- hidden price: computed as bilty_amount / total_qty --}}
                    <input type="hidden" name="items[0][price]" id="bilty_price1" value="0">
                    <input type="number" id="amount1" class="form-control row-amount" value="0" disabled>
                  </td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow_btn()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          {{-- Summary row --}}
          <div class="row mb-3 align-items-end">
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="number" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>
            <div class="col-md-2">
              <label>Bilty Amount <span class="text-danger">*</span></label>
              <input type="number" name="bilty_amount" id="payable_amount"
                     class="form-control" step="any" required placeholder="Total freight">
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
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Bilty</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var index = 2;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
    recalcAll();
  });

  function onItemNameChange(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const unitId  = selectedOption.getAttribute('data-unit-id');
    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;
    $(`#unit${idMatch[0]}`).val(String(unitId)).trigger('change.select2');
    recalcAll();
  }

  function onQtyChange() { recalcAll(); }

  function removeRow(button) {
    if ($('#BiltyItemTable tr').length > 1) {
      $(button).closest('tr').remove();
      recalcAll();
    }
  }

  function addNewRow_btn() { addNewRow(); }

  function addNewRow() {
    let rowIndex = index - 1;
    let newRow = `
      <tr>
        <td>
          <select name="items[${rowIndex}][item_id]" id="item_name${index}"
                  class="form-control select2-js product-select"
                  onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(p =>
              `<option value="${p.id}"
                       data-unit-id="${p.measurement_unit}"
                       data-bilty-charges="${p.bilty_charges ?? 0}">
                ${p.name}
              </option>`).join('')}
          </select>
        </td>
        <td>
          <input type="number" name="items[${rowIndex}][quantity]"
                 id="bilty_qty${index}" class="form-control quantity"
                 value="0" step="any" onchange="onQtyChange()">
        </td>
        <td>
          <select name="items[${rowIndex}][unit]" id="unit${index}"
                  class="form-control" required>
            <option value="">-- Select --</option>
            @foreach ($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
            @endforeach
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
    $('#BiltyItemTable').append(newRow);
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

  function fetchInvoiceProducts() {
    let invoiceId = $('#bilty_purchase_id').val();
    if (!invoiceId) { alert('Please select a Purchase Invoice first.'); return; }
    if (!confirm('This will clear current items. Continue?')) return;

    const btn = event.currentTarget;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    $.ajax({
      url: `/get-purchase-items/${invoiceId}`,
      method: 'GET',
      success: function (items) {
        $('#BiltyItemTable').empty();
        index = 1;
        if (items.length === 0) {
          alert('No items found.');
          addNewRow();
        } else {
          items.forEach(function (item) {
            addNewRow();
            let r = index - 1;
            $(`#item_name${r}`).val(item.item_id).trigger('change');
            $(`#bilty_qty${r}`).val(item.quantity);
          });
          recalcAll();
        }
      },
      error: function () { alert('Error fetching items.'); },
      complete: function () { btn.innerHTML = orig; }
    });
  }
</script>
@endsection