<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Creator;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $limit = 8;

        switch ($type) {
            case 'users':
                $items = User::query()
                    ->where(function ($qb) use ($q) {
                        $qb->where('name', 'like', "%{$q}%")
                            ->orWhere('phone_number', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    })
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($u) => [
                        'value' => $u->name ?: $u->phone_number,
                        'label' => trim(($u->name ?: 'N/A') . ' • ' . ($u->phone_number ?: 'No phone')),
                    ]);
                break;
            case 'videos':
            case 'home':
                $items = Video::query()
                    ->where('title', 'like', "%{$q}%")
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($v) => [
                        'value' => $v->title,
                        'label' => $v->title,
                    ]);
                break;
            case 'creators':
                $items = Creator::query()
                    ->where('name', 'like', "%{$q}%")
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($c) => [
                        'value' => $c->name,
                        'label' => $c->name,
                    ]);
                break;
            case 'payments':
                $items = Payment::query()
                    ->with('user')
                    ->where(function ($qb) use ($q) {
                        $qb->where('razorpay_payment_id', 'like', "%{$q}%")
                            ->orWhere('razorpay_order_id', 'like', "%{$q}%")
                            ->orWhere('order_id', 'like', "%{$q}%");
                    })
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($p) => [
                        'value' => $p->razorpay_payment_id ?? $p->razorpay_order_id ?? $p->order_id,
                        'label' => trim(($p->user->name ?? $p->user->phone_number ?? 'Unknown') . ' • ' . ($p->razorpay_payment_id ?? $p->razorpay_order_id ?? $p->order_id)),
                    ]);
                break;
            case 'subscriptions':
                $items = Subscription::query()
                    ->with('user')
                    ->where(function ($qb) use ($q) {
                        $qb->where('status', 'like', "%{$q}%")
                            ->orWhere('razorpay_subscription_id', 'like', "%{$q}%");
                    })
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($s) => [
                        'value' => $s->razorpay_subscription_id ?? $s->status,
                        'label' => trim(($s->user->name ?? $s->user->phone_number ?? 'Unknown') . ' • ' . ($s->razorpay_subscription_id ?? $s->status)),
                    ]);
                break;
            case 'categories':
                $items = Category::query()
                    ->where('name', 'like', "%{$q}%")
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($c) => [
                        'value' => $c->name,
                        'label' => $c->name,
                    ]);
                break;
            default:
                $items = collect();
                break;
        }

        return response()->json(['items' => $items->values()]);
    }
}
