<?php

namespace App\Helpers;

use App\Models\Category;
use App\Models\Cart;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;

class Helper
{
    public static function getHeaderCategory()
    {
        $categories = Category::where('is_parent', 1)->where('status', 'active')->get();

        $output = '';
        foreach ($categories as $category) {
            $output .= '<li><a href="#">' . $category->title . '</a></li>';
        }

        return $output;
    }

    public static function cartCount()
    {
        if (Auth::check()) {
            return Cart::where('user_id', Auth::id())->count();
        }
        return 0;
    }

    public static function wishlistCount()
    {
        if (Auth::check()) {
            return Wishlist::where('user_id', Auth::id())->count();
        }
        return 0;
    }

    public static function getAllProductFromCart()
    {
        if (Auth::check()) {
            return Cart::where('user_id', Auth::id())->with('product')->get();
        }
        return collect();
    }

    public static function getAllProductFromWishlist()
    {
        if (Auth::check()) {
            return Wishlist::where('user_id', Auth::id())->with('product')->get();
        }
        return collect();
    }

    public static function totalCartPrice()
    {
        return self::getAllProductFromCart()->sum(function ($item) {
            return $item->quantity * $item->price;
        });
    }

    public static function totalWishlistPrice()
    {
        return self::getAllProductFromWishlist()->sum(function ($item) {
            return $item->quantity * $item->price;
        });
    }

    public static function getAllCategory()
    {
        return Category::where('status', 'active')->orderBy('title', 'asc')->get();
    }
}
