<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user    = auth()->user();
        $product = Product::find($request->product_id);

        if (!$product) {
            return response()->json(['error' => 'المنتج غير موجود'], 404);
        }

        $requestedQuantity = $request->quantity ?? 1;

        $cartItem = CartItem::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $cartItem->update(['quantity' => $cartItem->quantity + $requestedQuantity]);
        } else {
            $cartItem = CartItem::create([
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'quantity'   => $requestedQuantity,
            ]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'تمت الإضافة للسلة بنجاح',
            'data'    => $cartItem,
        ]);
    }

    public function viewCart()
    {
        $user = auth()->user();
        $cart = CartItem::where('user_id', $user->id)->with('product')->get();

        return response()->json([
            'status' => true,
            'data'   => $cart,
            'total'  => $cart->sum(fn($item) => $item->quantity * ($item->product->price ?? 0)),
        ]);
    }

    public function removeItem($id)
    {
        $user = Auth::user();

        $item = CartItem::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Item not found'], 404);
        }

        $item->delete();

        return response()->json(['status' => true, 'message' => 'Item removed']);
    }
}