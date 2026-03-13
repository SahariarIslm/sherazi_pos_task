<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index()
    {
        $seconds = 3600;
        $page = request()->get('page',1);
        $data = Cache::tags(['products'])->remember('products_page_'.$page, $seconds, function () {
            // eager loading
            $products = Product::with('category')->paginate(15);

            $result = [];
            foreach ($products as $product) {
                $result[] = [
                    'id'       => $product->id,
                    'name'     => $product->name,
                    'price'    => $product->price,
                    'stock'    => $product->stock,
                    'category' => $product->category->name,
                ];
            }

            return $result;
        });

        return response()->json($data);
    }

    public function salesReport()
    {

        $seconds = 3600;
        $page = request()->get('page',1);
        $data = Cache::tags(['sales_report'])->remember('sales_report_page_'.$page, $seconds, function () {

            $orders = Order::with(['items.product', 'customer'])->get();

            $report = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $report[] = [
                        'order_id'     => $order->id,
                        'product_name' => $item->product->name,
                        'qty'          => $item->quantity,
                        'total'        => $item->quantity * $item->product->price,
                        'customer'     => $order->customer->name,
                    ];
                }
            }
            return $report;
        });

        return response()->json($data);
    }

    public function dashboard()
    {
        $seconds = 3600;
        $data = Cache::tags(['dashboard'])->remember('dashboard_stats', $seconds, function () {

            $totalProducts = Product::count();
            $totalOrders   = Order::count();
            $totalRevenue  = Order::sum('total_amount');
            $categories    = Category::all();

            $topProducts = Product::all()
                ->sortByDesc('sold_count')
                ->take(5)
                ->values();

            return [
                'total_products' => $totalProducts,
                'total_orders'   => $totalOrders,
                'total_revenue'  => $totalRevenue,
                'categories'     => $categories,
                'top_products'   => $topProducts,
            ];
        });
        return response()->json($data);

    }

    public function search(Request $request)
    {
        $keyword  = $request->input('q');
        $products = Product::where('name', 'LIKE', '%' . $keyword . '%')
                           ->orWhere('description', 'LIKE', '%' . $keyword . '%')
                           ->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->all());
        // flush cache after a new product been added
        Cache::tags(['dashboard','products'])->flush();
        return response()->json($product, 201);
    }
}
