<?php

namespace App\Http\Controllers\Admin;

use App\Actions\MarkOrderPaid;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManage();

        $status = $request->string('status')->toString();

        if (! in_array($status, ['pending', 'paid', 'failed', 'cancelled', 'refunded', 'all'], true)) {
            $status = 'pending';
        }

        $search = trim((string) $request->input('q', ''));

        $orders = Order::query()
            ->with(['user', 'package'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    if (ctype_digit($search)) {
                        $query->where('id', (int) $search);
                    }

                    $query->orWhereHas('user', function ($query) use ($search): void {
                        $like = '%'.addcslashes($search, '%_\\').'%';
                        $query->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monetization.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'search' => $search,
            'counts' => [
                'pending' => Order::query()->where('status', OrderStatus::Pending)->count(),
                'today' => Order::query()->whereDate('created_at', now()->toDateString())->count(),
                'paid_today' => Order::query()
                    ->where('status', OrderStatus::Paid)
                    ->whereDate('updated_at', now()->toDateString())
                    ->count(),
            ],
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorizeManage();

        $order->load([
            'user',
            'package',
            'payments',
            'verification',
            'boost.post',
            'listingPromotion.listing',
        ]);

        return view('admin.monetization.orders.show', [
            'order' => $order,
        ]);
    }

    public function markPaid(Request $request, Order $order, MarkOrderPaid $markOrderPaid): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(in_array($order->status, [OrderStatus::Pending, OrderStatus::Paid], true), 403);

        $markOrderPaid->handle($order, $request->user());

        return redirect()
            ->route('admin.monetization.orders.show', $order)
            ->with('success', 'Order was marked paid.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless($order->status === OrderStatus::Pending, 403);

        $order->cancelIfPending();

        return redirect()
            ->route('admin.monetization.orders.index')
            ->with('success', 'Order was cancelled.');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::ManageMonetization), 403);
    }
}
