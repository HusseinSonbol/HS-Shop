<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\Customer;
use App\Services\Admin\CustomerManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'in:active,archived'],
        ], [
            'q.string' => __('admin.customers.validation.search_invalid'),
            'q.max' => __('admin.customers.validation.search_max'),
            'scope.in' => __('admin.customers.validation.scope_invalid'),
        ]);

        $customers = Customer::query()
            ->when(($filters['scope'] ?? 'active') === 'archived', fn ($query) => $query->onlyTrashed())
            ->when(! empty($filters['q']), function ($query) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $query->where(function ($search) use ($term): void {
                    $search->where('customer_number', 'like', $term)
                        ->orWhere('display_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => $filters + ['scope' => 'active'],
        ]);
    }

    public function create(): View
    {
        return view('admin.customers.form', ['customer' => new Customer]);
    }

    public function store(CustomerRequest $request, CustomerManagementService $customers): RedirectResponse
    {
        $customer = $customers->create($request->validated());

        return redirect()->route('admin.customers.show', $customer)->with('status', __('admin.customers.created'));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['user:id,name,email', 'addresses' => fn ($query) => $query->orderByDesc('is_default')->orderByDesc('updated_at')]);
        $orders = $customer->orders()->orderByDesc('placed_at')->paginate(10, [
            'id', 'order_number', 'status', 'currency', 'grand_total', 'placed_at',
        ], 'orders_page');

        return view('admin.customers.show', compact('customer', 'orders'));
    }

    public function edit(Customer $customer): View
    {
        abort_if($customer->trashed(), 404);

        return view('admin.customers.form', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer, CustomerManagementService $customers): RedirectResponse
    {
        abort_if($customer->trashed(), 404);
        $customers->update($customer, $request->validated());

        return redirect()->route('admin.customers.show', $customer)->with('status', __('admin.customers.updated'));
    }

    public function archive(Customer $customer, CustomerManagementService $customers): RedirectResponse
    {
        abort_if($customer->trashed(), 404);
        $customers->archive($customer);

        return redirect()->route('admin.customers.index')->with('status', __('admin.customers.archived'));
    }

    public function restore(Customer $customer, CustomerManagementService $customers): RedirectResponse
    {
        abort_unless($customer->trashed(), 404);
        $customers->restore($customer);

        return redirect()->route('admin.customers.show', $customer->id)->with('status', __('admin.customers.restored'));
    }
}
