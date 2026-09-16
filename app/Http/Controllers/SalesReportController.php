<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleInvoice;
use App\Models\SaleReturn;
use App\Models\ChartOfAccounts;
use App\Models\PurchaseInvoiceItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    private function getLandedCost(int $productId): float
    {
        return \Cache::remember("landed_cost_prod_{$productId}", 3600, function () use ($productId) {
            $avgPurchasePrice = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_invoice_items.item_id', $productId)
                ->whereNull('purchase_invoices.deleted_at')
                ->avg('purchase_invoice_items.price') ?? 0;

            $avgBiltyPerUnit = DB::table('purchase_bilty_details')
                ->join('purchase_bilty', 'purchase_bilty_details.bilty_id', '=', 'purchase_bilty.id')
                ->where('purchase_bilty_details.item_id', $productId)
                ->whereNull('purchase_bilty.deleted_at')
                ->avg('purchase_bilty_details.price') ?? 0;

            return (float) $avgPurchasePrice + (float) $avgBiltyPerUnit;
        });
    }

    public function saleReports(Request $request)
    {
        $tab        = $request->get('tab', 'SR');
        $from       = $request->get('from_date', Carbon::now()->startOfMonth()->toDateString());
        $to         = $request->get('to_date', Carbon::now()->toDateString());
        $customerId = $request->get('customer_id');

        $sales        = collect();
        $returns      = collect();
        $customerWise = collect();

        // Profit report: superadmin only
        if ($tab === 'PR' && !auth()->user()->hasRole('superadmin')) {
            return redirect()->route('reports.sale', ['tab' => 'SR'])
                ->with('error', 'You do not have permission to view Profit Reports.');
        }

        // ── SALES REGISTER ────────────────────────────────────────────
        if ($tab === 'SR') {
            $sales = SaleInvoice::with(['account', 'items'])
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    $total = $sale->items->sum(fn($item) => ($item->sale_price ?? 0) * $item->quantity);
                    return (object)[
                        'id'       => $sale->id,
                        'date'     => $sale->date,
                        'invoice'  => $sale->invoice_no,
                        'customer' => $sale->account->name ?? '—',
                        'revenue'  => $total - ($sale->discount ?? 0),
                        'total'    => $total,
                        'cost'     => 0,
                        'profit'   => 0,
                        'margin'   => 0,
                    ];
                });
        }

        // ── SALES RETURN ──────────────────────────────────────────────
        if ($tab === 'SRET') {
            $returns = SaleReturn::with(['items'])
                ->whereBetween('return_date', [$from, $to])
                ->get()
                ->map(function ($ret) {
                    $total        = $ret->items->sum(fn($item) => ($item->qty ?? 0) * ($item->price ?? 0));
                    $customerName = '—';

                    // SaleReturn may use account() or customer() — try both
                    if (method_exists($ret, 'account') && $ret->relationLoaded('account')) {
                        $customerName = $ret->account->name ?? '—';
                    }
                    if ($customerName === '—' && method_exists($ret, 'customer')) {
                        $ret->load('customer');
                        $customerName = $ret->customer->name ?? '—';
                    }
                    if ($customerName === '—') {
                        // fallback: load account_id directly
                        $acct = \App\Models\ChartOfAccounts::find($ret->account_id ?? $ret->customer_id ?? null);
                        $customerName = $acct->name ?? '—';
                    }

                    return (object)[
                        'date'     => $ret->return_date,
                        'invoice'  => $ret->invoice_no ?? $ret->id,
                        'customer' => $customerName,
                        'total'    => $total,
                    ];
                });
        }

        // ── CUSTOMER WISE ─────────────────────────────────────────────
        if ($tab === 'CW') {
            $query = SaleInvoice::with(['account', 'items.product'])
                ->whereBetween('date', [$from, $to]);

            if ($customerId) {
                $query->where('account_id', $customerId);
            }

            $customerWise = $query->get()
                // Filter out invoices with no account to avoid null key grouping
                ->filter(fn($sale) => !is_null($sale->account_id))
                ->groupBy('account_id')
                ->map(function ($sales) {
                    $customerName = $sales->first()->account->name ?? 'Unknown Customer';
                    $items        = collect();

                    foreach ($sales as $sale) {
                        foreach ($sale->items as $item) {
                            $qty   = (float) ($item->quantity  ?? 0);
                            $price = (float) ($item->sale_price ?? 0);

                            $items->push((object)[
                                'invoice_date' => $sale->date,
                                'invoice_no'   => $sale->invoice_no,
                                'item_name'    => $item->product->name ?? 'N/A',
                                'quantity'     => $qty,
                                'rate'         => $price,
                                'total'        => $qty * $price,
                            ]);
                        }
                    }

                    return (object)[
                        'customer_name' => $customerName,
                        'items'         => $items,
                        'total_qty'     => $items->sum('quantity'),
                        'total_amount'  => $items->sum('total'),
                    ];
                })
                ->values();
        }

        // ── PROFIT REPORT ─────────────────────────────────────────────
        if ($tab === 'PR') {
            $sales = SaleInvoice::with(['account', 'items.customizations'])
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    $invoiceRevenue = 0;
                    $invoiceCost    = 0;

                    foreach ($sale->items as $item) {
                        $invoiceRevenue += ($item->sale_price ?? 0) * $item->quantity;
                        $unitCost        = $this->getLandedCost($item->product_id);

                        foreach ($item->customizations ?? [] as $custom) {
                            $unitCost += $this->getLandedCost($custom->item_id);
                        }

                        $invoiceCost += $unitCost * $item->quantity;
                    }

                    $netRevenue = $invoiceRevenue - ($sale->discount ?? 0);

                    return (object)[
                        'id'       => $sale->id,
                        'date'     => $sale->date,
                        'invoice'  => $sale->invoice_no,
                        'customer' => $sale->account->name ?? '—',
                        'revenue'  => $netRevenue,
                        'cost'     => $invoiceCost,
                        'profit'   => $netRevenue - $invoiceCost,
                        'margin'   => $netRevenue > 0
                            ? (($netRevenue - $invoiceCost) / $netRevenue) * 100
                            : 0,
                    ];
                });
        }

        $customers = ChartOfAccounts::where('account_type', 'customer')->get();

        return view('reports.sales_reports', compact(
            'tab', 'from', 'to',
            'sales', 'returns', 'customerWise',
            'customers', 'customerId'
        ));
    }

    public function printProfitReport($id)
    {
        $invoice = SaleInvoice::with([
            'account',
            'items.product',
            'items.customizations.item',
        ])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetTitle('Profit Analysis - ' . $invoice->invoice_no);
        $pdf->AddPage();

        $logoPath = public_path('assets/img/bf_logo.jpg');
        if (file_exists($logoPath)) $pdf->Image($logoPath, 12, 8, 35);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Profit Analysis Report', 0, 1, 'R');
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 9);

        $infoHtml = '
        <table border="1" cellpadding="3">
            <tr>
                <td width="15%" bgcolor="#f5f5f5"><b>Customer:</b></td>
                <td width="50%">' . ($invoice->account->name ?? '-') . '</td>
                <td width="15%" bgcolor="#f5f5f5"><b>Date:</b></td>
                <td width="20%">' . Carbon::parse($invoice->date)->format('d-m-Y') . '</td>
            </tr>
            <tr>
                <td bgcolor="#f5f5f5"><b>Invoice #:</b></td>
                <td>' . $invoice->invoice_no . '</td>
                <td bgcolor="#f5f5f5"><b>Currency:</b></td>
                <td>PKR</td>
            </tr>
        </table>';
        $pdf->writeHTML($infoHtml);

        $html = '
        <table border="1" cellpadding="4" style="font-size:8px;text-align:center;">
            <tr style="background-color:#f5f5f5;font-weight:bold;">
                <th width="5%">#</th>
                <th width="35%">Product & Customizations</th>
                <th width="8%">Qty</th>
                <th width="14%">Sale Rate / Total</th>
                <th width="18%">Landed Cost / Total</th>
                <th width="20%">Line Profit</th>
            </tr>';

        $totalRevenue    = 0;
        $totalLandedCost = 0;

        foreach ($invoice->items as $idx => $item) {
            $mainUnitCost  = $this->getLandedCost($item->product_id);
            $totalUnitCost = $mainUnitCost;
            $customLines   = [];

            foreach ($item->customizations ?? [] as $custom) {
                $cCost          = $this->getLandedCost($custom->item_id);
                $totalUnitCost += $cCost;
                $partName       = $custom->item->name ?? 'Custom Part';
                $customLines[]  = '<span style="color:#555;">+ ' . $partName
                                . ' (@' . number_format($cCost, 2) . ')</span>';
            }

            $lineRev  = ($item->sale_price ?? 0) * $item->quantity;
            $lineCost = $totalUnitCost * $item->quantity;

            $totalRevenue    += $lineRev;
            $totalLandedCost += $lineCost;

            $productDisplay = '<b>' . ($item->product->name ?? '-') . '</b>'
                            . ' (@' . number_format($mainUnitCost, 2) . ')';
            if (!empty($customLines)) {
                $productDisplay .= '<br>' . implode('<br>', $customLines);
            }

            $html .= '<tr>
                <td>' . ($idx + 1) . '</td>
                <td align="left">' . $productDisplay . '</td>
                <td>' . number_format($item->quantity, 2) . '</td>
                <td>' . number_format($item->sale_price ?? 0, 2) . '<br><b>'
                      . number_format($lineRev, 2) . '</b></td>
                <td>' . number_format($totalUnitCost, 2) . '<br><b>'
                      . number_format($lineCost, 2) . '</b></td>
                <td style="font-weight:bold;">'
                      . number_format($lineRev - $lineCost, 2) . '</td>
            </tr>';
        }

        $discount  = $invoice->discount ?? 0;
        $netRev    = $totalRevenue - $discount;
        $netProfit = $netRev - $totalLandedCost;
        $margin    = $netRev > 0 ? ($netProfit / $netRev) * 100 : 0;

        $html .= '
        <tr style="background-color:#f9f9f9;">
            <td colspan="3" align="right"><b>Gross Totals</b></td>
            <td><b>' . number_format($totalRevenue, 2) . '</b></td>
            <td><b>' . number_format($totalLandedCost, 2) . '</b></td>
            <td><b>' . number_format($totalRevenue - $totalLandedCost, 2) . '</b></td>
        </tr>
        <tr>
            <td colspan="5" align="right">Less: Discount</td>
            <td>(' . number_format($discount, 2) . ')</td>
        </tr>
        <tr style="background-color:#e8f5e9;">
            <td colspan="5" align="right"><b>NET PROFIT</b></td>
            <td style="color:green;"><b>' . number_format($netProfit, 2) . '</b></td>
        </tr>
        <tr style="background-color:#f5f5f5;">
            <td colspan="5" align="right"><b>Margin %</b></td>
            <td><b>' . number_format($margin, 2) . '%</b></td>
        </tr>
        </table>';

        $pdf->writeHTML($html);

        return $pdf->Output('Profit_' . $invoice->invoice_no . '.pdf', 'I');
    }
}