@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.update', $invoice->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Purchase Invoice: {{ $invoice->bill_no }}</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control"
                     value="{{ $invoice->invoice_date }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}"
                    {{ $invoice->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Bill #</label>
              <input type="text" name="bill_no" class="form-control" value="{{ $invoice->bill_no }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control" value="{{ $invoice->ref_no }}">
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple
                     accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $invoice->remarks }}</textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>S.No</th>
                  <th>Item</th>
                  <th>Variation</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="PurchaseTableBody">
                @foreach($invoice->items as $key => $item)
                <tr>
                  <td class="serial-no">{{ $key + 1 }}</td>

                  {{-- Item --}}
                  <td>
                    <select name="items[{{ $key }}][item_id]"
                            id="item_name{{ $key + 1 }}"
                            class="form-control select2-js product-select"
                            onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-unit-id="{{ $product->measurement_unit }}"
                                {{ $item->item_id == $product->id ? 'selected' : '' }}>
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>

                  {{-- Variation --}}
                  <td>
                    <select name="items[{{ $key }}][variation_id]"
                            id="variation{{ $key + 1 }}"
                            class="form-control select2-js">
                      <option value="">No Variation</option>
                      @foreach($item->product->variations as $v)
                        <option value="{{ $v->id }}"
                          {{ $item->variation_id == $v->id ? 'selected' : '' }}>
                          {{ $v->sku }}
                        </option>
                      @endforeach
                    </select>
                  </td>

                  {{-- Quantity --}}
                  <td>
                    <input type="number"
                           name="items[{{ $key }}][quantity]"
                           id="pur_qty{{ $key + 1 }}"
                           class="form-control quantity"
                           value="{{ $item->quantity }}"
                           step="any"
                           onchange="rowTotal({{ $key + 1 }})">
                  </td>

                  {{-- Unit — id added so JS can find it --}}
                  <td>
                    <select name="items[{{ $key }}][unit]"
                            id="unit{{ $key + 1 }}"
                            class="form-control select2-js">
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}"
                          {{ $item->unit == $unit->id ? 'selected' : '' }}>
                          {{ $unit->name }} ({{ $unit->shortcode }})
                        </option>
                      @endforeach
                    </select>
                  </td>

                  {{-- Price --}}
                  <td>
                    <input type="number"
                           name="items[{{ $key }}][price]"
                           id="pur_price{{ $key + 1 }}"
                           class="form-control"
                           value="{{ $item->price }}"
                           step="any"
                           onchange="rowTotal({{ $key + 1 }})">
                  </td>

                  {{-- Amount --}}
                  <td>
                    <input type="number"
                           id="amount{{ $key + 1 }}"
                           class="form-control"
                           value="{{ $item->quantity * $item->price }}"
                           disabled>
                  </td>

                  {{-- Action --}}
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Invoice
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var units    = @json($units);
  var index    = {{ count($invoice->items) + 1 }};

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
    tableTotal();
    updateSerialNumbers();
  });

  function updateSerialNumbers() {
    $('#PurchaseTableBody tr').each(function (i) {
      $(this).find('.serial-no').text(i + 1);
    });
  }

  function addNewRow() {
    let rowIndex = index - 1;

    let newRow = `
      <tr>
        <td class="serial-no"></td>
        <td>
          <select name="items[${rowIndex}][item_id]"
                  id="item_name${index}"
                  class="form-control select2-js product-select"
                  onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(p =>
              `<option value="${p.id}" data-unit-id="${p.measurement_unit}">${p.name}</option>`
            ).join('')}
          </select>
        </td>
        <td>
          <select name="items[${rowIndex}][variation_id]"
                  id="variation${index}"
                  class="form-control select2-js">
            <option value="">Select Variation</option>
          </select>
        </td>
        <td>
          <input type="number" name="items[${rowIndex}][quantity]"
                 id="pur_qty${index}" class="form-control quantity"
                 value="0" step="any" onchange="rowTotal(${index})">
        </td>
        <td>
          <select name="items[${rowIndex}][unit]"
                  id="unit${index}"
                  class="form-control select2-js">
            <option value="">-- Select --</option>
            ${units.map(u => `<option value="${u.id}">${u.name} (${u.shortcode})</option>`).join('')}
          </select>
        </td>
        <td>
          <input type="number" name="items[${rowIndex}][price]"
                 id="pur_price${index}" class="form-control"
                 value="0" step="any" onchange="rowTotal(${index})">
        </td>
        <td>
          <input type="number" id="amount${index}" class="form-control" value="0" disabled>
        </td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
            <i class="fas fa-times"></i>
          </button>
        </td>
      </tr>`;

    $('#PurchaseTableBody').append(newRow);
    $(`#item_name${index}`).select2({ width: '100%' });
    $(`#variation${index}`).select2({ width: '100%' });
    $(`#unit${index}`).select2({ width: '100%' });
    index++;
    updateSerialNumbers();
  }

  function rowTotal(row) {
    let quantity = parseFloat($('#pur_qty'   + row).val()) || 0;
    let price    = parseFloat($('#pur_price' + row).val()) || 0;
    $('#amount' + row).val((quantity * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0;
    let qty   = 0;

    $('#PurchaseTableBody tr').each(function () {
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
      qty   += parseFloat($(this).find('input.quantity').val())      || 0;
    });

    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));
    netTotal();
  }

  function netTotal() {
    let total = parseFloat($('#totalAmount').val()) || 0;
    let net   = total.toFixed(2);
    $('#netTotal').text(formatNumberWithCommas(net));
    $('#net_amount').val(net);
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function removeRow(btn) {
    if ($('#PurchaseTableBody tr').length > 1) {
      $(btn).closest('tr').remove();
      updateSerialNumbers();
      tableTotal();
    }
  }

  function onItemNameChange(selectElement) {
    const itemId      = selectElement.value;
    const idMatch     = selectElement.id.match(/\d+$/);
    if (!idMatch) return;
    const rowIndex    = idMatch[0];

    // Unit auto-select
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const unitId         = selectedOption.getAttribute('data-unit-id');
    if (unitId) {
      $(`#unit${rowIndex}`).val(String(unitId)).trigger('change.select2');
    }

    // Reset price and amount when item changes
    $(`#pur_price${rowIndex}`).val(0);
    $(`#amount${rowIndex}`).val(0);
    tableTotal();

    // Reload variations
    const variationSelect = $(`#variation${rowIndex}`);

    if (!itemId) {
      variationSelect.html('<option value="">No Variation</option>').trigger('change.select2');
      return;
    }

    variationSelect.html('<option value="">Loading...</option>').trigger('change.select2');

    fetch(`/product/${itemId}/variations`)
      .then(res => res.json())
      .then(data => {
        variationSelect.html('<option value="">No Variation</option>');
        if (data.success && data.variation && data.variation.length > 0) {
          data.variation.forEach(v => {
            variationSelect.append(`<option value="${v.id}">${v.sku}</option>`);
          });
        }
        variationSelect.trigger('change.select2');
      })
      .catch(() => {
        variationSelect.html('<option value="">Error loading</option>').trigger('change.select2');
      });
  }
</script>
@endsection