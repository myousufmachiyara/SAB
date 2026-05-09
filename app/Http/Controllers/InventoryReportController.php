<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab     = $request->get('tab', 'IL');
        $itemId  = $request->get('item_id');
        $from    = $request->get('from_date', date('Y-m-01'));
        $to      = $request->get('to_date', date('Y-m-d'));

        $products    = Product::with('variations')->orderBy('name')->get();
        $itemLedger  = collect();
        $openingQty  = 0;
        $stockInHand = collect();

        // ================================================================
        // TAB 1 — ITEM LEDGER
        // ================================================================
        if ($tab === 'IL' && $itemId) {

            $opPurchased = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_invoice_items.item_id', $itemId)
                ->whereNull('purchase_invoices.deleted_at')
                ->where('purchase_invoices.invoice_date', '<', $from)
                ->sum('purchase_invoice_items.quantity');

            $opSold = DB::table('sale_invoice_items')
                ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                ->where('sale_invoice_items.product_id', $itemId)
                ->whereNull('sale_invoices.deleted_at')
                ->where('sale_invoices.date', '<', $from)
                ->sum('sale_invoice_items.quantity');

            $opPurchaseReturned = DB::table('purchase_return_items')
                ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                ->where('purchase_return_items.item_id', $itemId)
                ->where('purchase_returns.return_date', '<', $from)
                ->sum('purchase_return_items.quantity');

            $opSaleReturned = DB::table('sale_return_items')
                ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                ->where('sale_return_items.product_id', $itemId)
                ->where('sale_returns.return_date', '<', $from)
                ->sum('sale_return_items.qty');

            $openingQty = ((float)$opPurchased + (float)$opSaleReturned)
                        - ((float)$opSold      + (float)$opPurchaseReturned);

            $purchases = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->select(
                    'purchase_invoices.invoice_date as date',
                    DB::raw("'Purchase' as type"),
                    DB::raw("CONCAT('PI-', purchase_invoices.invoice_no) as description"),
                    'purchase_invoice_items.quantity as qty_in',
                    DB::raw('0 as qty_out')
                )
                ->where('purchase_invoice_items.item_id', $itemId)
                ->whereNull('purchase_invoices.deleted_at')
                ->whereBetween('purchase_invoices.invoice_date', [$from, $to]);

            $sales = DB::table('sale_invoice_items')
                ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                ->select(
                    'sale_invoices.date as date',
                    DB::raw("'Sale' as type"),
                    DB::raw("CONCAT('SI-', sale_invoices.invoice_no) as description"),
                    DB::raw('0 as qty_in'),
                    'sale_invoice_items.quantity as qty_out'
                )
                ->where('sale_invoice_items.product_id', $itemId)
                ->whereNull('sale_invoices.deleted_at')
                ->whereBetween('sale_invoices.date', [$from, $to]);

            $purchaseReturns = DB::table('purchase_return_items')
                ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                ->select(
                    'purchase_returns.return_date as date',
                    DB::raw("'Purchase Return' as type"),
                    DB::raw("CONCAT('PR-', purchase_returns.id) as description"),
                    DB::raw('0 as qty_in'),
                    'purchase_return_items.quantity as qty_out'
                )
                ->where('purchase_return_items.item_id', $itemId)
                ->whereBetween('purchase_returns.return_date', [$from, $to]);

            $saleReturns = DB::table('sale_return_items')
                ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                ->select(
                    'sale_returns.return_date as date',
                    DB::raw("'Sale Return' as type"),
                    DB::raw("CONCAT('SR-', sale_returns.id) as description"),
                    'sale_return_items.qty as qty_in',
                    DB::raw('0 as qty_out')
                )
                ->where('sale_return_items.product_id', $itemId)
                ->whereBetween('sale_returns.return_date', [$from, $to]);

            $itemLedger = $purchases
                ->union($sales)
                ->union($purchaseReturns)
                ->union($saleReturns)
                ->orderBy('date', 'asc')
                ->get()
                ->map(fn($row) => (array) $row);
        }

        if ($tab === 'SR') {
            $costingMethod = $request->get('costing_method', 'avg');
            $query = Product::query();
            if ($itemId) $query->where('id', $itemId);

            $stockInHand = $query->orderBy('name')->get()->map(function ($product) use ($costingMethod, $to) {

                $tIn = DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('purchase_invoice_items.item_id', $product->id)
                    ->where('purchase_invoices.invoice_date', '<=', $to)
                    ->whereNull('purchase_invoices.deleted_at')
                    ->sum('purchase_invoice_items.quantity');

                $tOut = DB::table('sale_invoice_items')
                    ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                    ->where('sale_invoice_items.product_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                $tPurchaseReturn = DB::table('purchase_return_items')
                    ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                    ->where('purchase_return_items.item_id', $product->id)
                    ->where('purchase_returns.return_date', '<=', $to)
                    ->whereNull('purchase_returns.deleted_at')
                    ->sum('purchase_return_items.quantity');

                $tSaleReturn = DB::table('sale_return_items')
                    ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                    ->where('sale_return_items.product_id', $product->id)
                    ->where('sale_returns.return_date', '<=', $to)
                    ->whereNull('sale_returns.deleted_at')
                    ->sum('sale_return_items.qty');

                $qty = $tIn - $tOut - $tPurchaseReturn + $tSaleReturn;

                $priceQuery = DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('purchase_invoice_items.item_id', $product->id)
                    ->where('purchase_invoices.invoice_date', '<=', $to)
                    ->whereNull('purchase_invoices.deleted_at');

                $purchasePrice = $costingMethod === 'latest'
                    ? ($priceQuery->latest('purchase_invoices.invoice_date')->value('purchase_invoice_items.price') ?? 0)
                    : ($priceQuery->avg('purchase_invoice_items.price') ?? 0);

                $biltyPrice = DB::table('purchase_bilty_details')
                    ->join('purchase_bilty', 'purchase_bilty_details.bilty_id', '=', 'purchase_bilty.id')
                    ->where('purchase_bilty_details.item_id', $product->id)
                    ->where('purchase_bilty.bilty_date', '<=', $to)
                    ->whereNull('purchase_bilty.deleted_at')
                    ->avg('purchase_bilty_details.price') ?? 0;

                $unitCost = $purchasePrice + $biltyPrice;

                return [
                    'product'        => $product->name,
                    'quantity'       => $qty,
                    'purchase_price' => round($purchasePrice, 2),
                    'bilty_price'    => round($biltyPrice, 2),
                    'price'          => round($unitCost, 2),
                    'total'          => round($qty * $unitCost, 2),
                ];
            })->filter(fn($row) => $row['quantity'] != 0);
        }

        return view('reports.inventory_reports', compact(
            'products',
            'itemLedger',
            'openingQty',
            'stockInHand',
            'tab',
            'from',
            'to'
        ));
    }
}