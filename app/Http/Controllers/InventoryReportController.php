<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab           = $request->get('tab', 'IL');
        $itemId        = $request->get('item_id');
        $from          = $request->get('from_date', date('Y-m-01'));
        $to            = $request->get('to_date', date('Y-m-d'));
        $costingMethod = $request->get('costing_method', 'avg');

        $products    = Product::orderBy('name', 'asc')->get();
        $itemLedger  = collect();
        $openingQty  = 0;
        $stockInHand = collect();

        // ================================================================
        // TAB 1 — ITEM LEDGER
        // ================================================================
        if ($tab == 'IL' && $itemId) {

            $product = Product::find($itemId);

            // opening_stock from products table always counts as stock in
            // from the very beginning, so it's always part of opening balance
            $productOpeningStock = (float) ($product->opening_stock ?? 0);

            $opPurchase = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_invoice_items.item_id', $itemId)
                ->where('purchase_invoices.invoice_date', '<', $from)
                ->whereNull('purchase_invoices.deleted_at')
                ->sum('purchase_invoice_items.quantity');

            $opSale = DB::table('sale_invoice_items')
                ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                ->where('sale_invoice_items.product_id', $itemId)
                ->where('sale_invoices.date', '<', $from)
                ->whereNull('sale_invoices.deleted_at')
                ->sum('sale_invoice_items.quantity');

            $opCustom = DB::table('sale_item_customization')
                ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                ->where('sale_item_customization.item_id', $itemId)
                ->where('sale_invoices.date', '<', $from)
                ->whereNull('sale_invoices.deleted_at')
                ->sum('sale_invoice_items.quantity');

            $opPurchaseReturn = DB::table('purchase_return_items')
                ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                ->where('purchase_return_items.item_id', $itemId)
                ->where('purchase_returns.return_date', '<', $from)
                ->sum('purchase_return_items.quantity');

            $opSaleReturn = DB::table('sale_return_items')
                ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                ->where('sale_return_items.product_id', $itemId)
                ->where('sale_returns.return_date', '<', $from)
                ->sum('sale_return_items.qty');

            // opening_stock is a permanent addition to the opening balance
            $openingQty = $productOpeningStock
                        + (float) $opPurchase
                        + (float) $opSaleReturn
                        - (float) $opSale
                        - (float) $opCustom
                        - (float) $opPurchaseReturn;

            // ── Period Transactions ───────────────────────────────────

            $purchases = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->select(
                    'purchase_invoices.invoice_date as date',
                    DB::raw("'Purchase' as type"),
                    'purchase_invoices.invoice_no as description',
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
                    DB::raw("CONCAT(sale_invoices.invoice_no, ' (Rate: ', sale_invoice_items.sale_price, ')') as description"),
                    DB::raw('0 as qty_in'),
                    'sale_invoice_items.quantity as qty_out'
                )
                ->where('sale_invoice_items.product_id', $itemId)
                ->whereNull('sale_invoices.deleted_at')
                ->whereBetween('sale_invoices.date', [$from, $to]);

            $customizations = DB::table('sale_item_customization')
                ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                ->select(
                    'sale_invoices.date as date',
                    DB::raw("'Customization' as type"),
                    'sale_invoices.invoice_no as description',
                    DB::raw('0 as qty_in'),
                    'sale_invoice_items.quantity as qty_out'
                )
                ->where('sale_item_customization.item_id', $itemId)
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
                ->unionAll($customizations)
                ->unionAll($sales)
                ->unionAll($purchaseReturns)
                ->unionAll($saleReturns)
                ->orderBy('date', 'asc')
                ->get();
        }

        // ================================================================
        // TAB 2 — STOCK IN HAND
        // ================================================================
        if ($tab == 'SR') {
            $query = Product::query();
            if ($itemId) $query->where('id', $itemId);

            $stockInHand = $query->orderBy('name')->get()->map(function ($product) use ($costingMethod, $to) {

                // opening_stock from products table
                $productOpeningStock = (float) ($product->opening_stock ?? 0);

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

                $tCustom = DB::table('sale_item_customization')
                    ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                    ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                    ->where('sale_item_customization.item_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                $tPurchaseReturn = DB::table('purchase_return_items')
                    ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                    ->where('purchase_return_items.item_id', $product->id)
                    ->where('purchase_returns.return_date', '<=', $to)
                    ->sum('purchase_return_items.quantity');

                $tSaleReturn = DB::table('sale_return_items')
                    ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                    ->where('sale_return_items.product_id', $product->id)
                    ->where('sale_returns.return_date', '<=', $to)
                    ->sum('sale_return_items.qty');

                // opening_stock + purchases + sale returns
                // - sales - customizations - purchase returns
                $qty = $productOpeningStock
                     + (float) $tIn
                     + (float) $tSaleReturn
                     - (float) $tOut
                     - (float) $tCustom
                     - (float) $tPurchaseReturn;

                $priceQuery = DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('purchase_invoice_items.item_id', $product->id)
                    ->where('purchase_invoices.invoice_date', '<=', $to)
                    ->whereNull('purchase_invoices.deleted_at');

                $purchasePrice = $costingMethod === 'latest'
                    ? ($priceQuery->latest('purchase_invoices.invoice_date')->value('purchase_invoice_items.price') ?? 0)
                    : (function () use ($priceQuery) {
                        $stats = (clone $priceQuery)
                            ->selectRaw('SUM(purchase_invoice_items.quantity * purchase_invoice_items.price) as total_value, SUM(purchase_invoice_items.quantity) as total_qty')
                            ->first();
                        return ($stats && $stats->total_qty > 0) ? ($stats->total_value / $stats->total_qty) : 0;
                    })();

                $biltyPrice = DB::table('purchase_bilty_details')
                    ->join('purchase_bilty', 'purchase_bilty_details.bilty_id', '=', 'purchase_bilty.id')
                    ->where('purchase_bilty_details.item_id', $product->id)
                    ->where('purchase_bilty.bilty_date', '<=', $to)
                    ->whereNull('purchase_bilty.deleted_at')
                    ->avg('purchase_bilty_details.price') ?? 0;

                $unitCost = (float) $purchasePrice + (float) $biltyPrice;

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
            'products', 'itemLedger', 'openingQty', 'stockInHand', 'tab', 'from', 'to'
        ));
    }
}