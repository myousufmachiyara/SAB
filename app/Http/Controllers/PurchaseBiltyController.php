<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBilty;
use App\Models\PurchaseBiltyDetail;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PurchaseBiltyController extends Controller
{
    public function index()
    {
        $invoices = PurchaseBilty::with('vendor')->latest()->get();
        return view('purchase-bilty.index', compact('invoices'));
    }

    public function create()
    {
        $products         = Product::get();
        $vendors          = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units            = MeasurementUnit::all();
        $purchaseInvoices = PurchaseInvoice::orderBy('id', 'desc')->get(['id', 'invoice_no']);

        return view('purchase-bilty.create', compact('products', 'vendors', 'units', 'purchaseInvoices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'          => 'required|exists:chart_of_accounts,id',
            'bilty_date'         => 'required|date',
            'bilty_amount'       => 'required|numeric|min:0',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0',
            'items.*.unit'       => 'required|exists:measurement_units,id',
        ]);

        try {
            DB::beginTransaction();

            $totalQty = collect($request->items)->sum('quantity');
            $perUnit  = $totalQty > 0 ? round($request->bilty_amount / $totalQty, 4) : 0;

            $bilty = PurchaseBilty::create([
                'purchase_id'  => $request->bilty_purchase_id ?? null,
                'vendor_id'    => $request->vendor_id,
                'bilty_date'   => $request->bilty_date,
                'ref_no'       => $request->ref_no ?? null,
                'remarks'      => $request->remarks ?? null,
                'bilty_amount' => $request->bilty_amount,
                'total_qty'    => $totalQty,
                'created_by'   => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                PurchaseBiltyDetail::create([
                    'bilty_id' => $bilty->id,
                    'item_id'  => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit'     => $item['unit'],
                    'price'    => $perUnit,
                    'remarks'  => $item['remarks'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('purchase_bilty.index')
                ->with('success', 'Purchase Bilty created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Bilty Store Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $bilty            = PurchaseBilty::with('details')->findOrFail($id);
            $products         = Product::get();
            $vendors          = ChartOfAccounts::where('account_type', 'vendor')->get();
            $units            = MeasurementUnit::all();
            $purchaseInvoices = PurchaseInvoice::orderBy('id', 'desc')->get(['id', 'invoice_no']);

            return view('purchase-bilty.edit', compact(
                'bilty', 'products', 'vendors', 'units', 'purchaseInvoices'
            ));
        } catch (\Exception $e) {
            Log::error('Purchase Bilty Edit Error', ['message' => $e->getMessage()]);
            return redirect()->route('purchase_bilty.index')
                ->with('error', 'Unable to load Purchase Bilty for editing.');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'vendor_id'          => 'required|exists:chart_of_accounts,id',
            'bilty_date'         => 'required|date',
            'bilty_amount'       => 'required|numeric|min:0',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0',
            'items.*.unit'       => 'required|exists:measurement_units,id',
        ]);

        try {
            DB::beginTransaction();

            $totalQty = collect($request->items)->sum('quantity');
            $perUnit  = $totalQty > 0 ? round($request->bilty_amount / $totalQty, 4) : 0;

            $bilty = PurchaseBilty::findOrFail($id);
            $bilty->update([
                'purchase_id'  => $request->purchase_id ?? null,
                'vendor_id'    => $request->vendor_id,
                'bilty_date'   => $request->bilty_date,
                'ref_no'       => $request->ref_no ?? null,
                'remarks'      => $request->remarks ?? null,
                'bilty_amount' => $request->bilty_amount,
                'total_qty'    => $totalQty,
            ]);

            PurchaseBiltyDetail::where('bilty_id', $bilty->id)->delete();

            foreach ($request->items as $item) {
                PurchaseBiltyDetail::create([
                    'bilty_id' => $bilty->id,
                    'item_id'  => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit'     => $item['unit'],
                    'price'    => $perUnit,
                    'remarks'  => $item['remarks'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('purchase_bilty.index')
                ->with('success', 'Purchase Bilty updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Bilty Update Error', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $bilty = PurchaseBilty::findOrFail($id);
        $bilty->delete();

        return redirect()->route('purchase_bilty.index')
            ->with('success', 'Purchase Bilty deleted successfully.');
    }

    public function getInvoiceItems($id)
    {
        $items = PurchaseInvoiceItem::where('purchase_invoice_id', $id)->get();
        return response()->json($items);
    }

    public function print($id)
    {
        $bilty = PurchaseBilty::with([
            'vendor', 'purchase', 'details.product', 'details.measurementUnit'
        ])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('Bilwani Furnitures');
        $pdf->SetTitle('BILTY-' . $bilty->id);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoPath = public_path('assets/img/bf_logo.jpg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 40);
        }

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Purchase Bilty', 0, 1, 'R');
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
            <tr><td width="30%"><b>Vendor</b></td><td>' . ($bilty->vendor->name ?? '-') . '</td></tr>
            <tr><td><b>Bilty No</b></td><td>BILTY-' . $bilty->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . Carbon::parse($bilty->bilty_date)->format('d-m-Y') . '</td></tr>
            <tr><td><b>Ref No</b></td><td>' . ($bilty->ref_no ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold;background-color:#f5f5f5;">
                <th width="10%">#</th>
                <th width="55%">Item</th>
                <th width="20%">Qty</th>
                <th width="15%">Unit</th>
            </tr>';

        $count    = 0;
        $totalQty = 0;
        foreach ($bilty->details as $item) {
            $count++;
            $html .= '<tr>
                <td>' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . '</td>
                <td>' . ($item->measurementUnit->shortcode ?? '-') . '</td>
            </tr>';
            $totalQty += $item->quantity;
        }

        $html .= '<tr>
            <td colspan="2" align="right"><b>Total</b></td>
            <td><b>' . number_format($totalQty, 2) . '</b></td>
            <td></td>
        </tr></table>';
        $pdf->writeHTML($html, true, false, false, false, '');

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Bilty Amount: PKR ' . number_format($bilty->bilty_amount, 2), 0, 1, 'R');
        $pdf->Cell(0, 8, 'Per Unit: PKR ' . number_format($bilty->total_qty > 0 ? $bilty->bilty_amount / $bilty->total_qty : 0, 4), 0, 1, 'R');

        $pdf->Ln(20);
        $yPosition = $pdf->GetY();
        $lineWidth  = 60;
        $pdf->Line(28, $yPosition, 20 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 120 + $lineWidth, $yPosition);
        $pdf->Ln(5);
        $pdf->SetXY(23, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Approved By', 0, 0, 'C');
        $pdf->SetXY(125, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Received By', 0, 0, 'C');

        return $pdf->Output('purchase_bilty_' . $bilty->id . '.pdf', 'I');
    }
}