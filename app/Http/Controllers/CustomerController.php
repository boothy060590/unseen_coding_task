<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\AuditService;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private AuditService $auditService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $filters = $request->only(['search', 'organization', 'created_from', 'created_to']);
        $dashboardData = $this->customerService->getDashboardData($user, $filters);

        return view('customers.index', $dashboardData);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('customers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = auth()->user();
            $customer = $this->customerService->createCustomer(
                $user,
                $request->validated()
            );

            return redirect()
                ->route('customers.show', $customer->slug)
                ->with('success', "Customer '{$customer->full_name}' was created successfully.");

        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): View
    {
        /** @var User $user */
        $user = auth()->user();

        // Get recent audit trail for this customer
        $auditTrail = $this->auditService->getCustomerAuditTrail(
           $user,
            $customer,
            10
        );

        return view('customers.show', compact('customer', 'auditTrail'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = auth()->user();
            $updatedCustomer = $this->customerService->updateCustomer(
                $user,
                $customer,
                $request->validated()
            );

            return redirect()
                ->route('customers.show', $updatedCustomer->slug)
                ->with('success', "Customer '{$updatedCustomer->full_name}' was updated successfully.");

        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->validator)
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $customerName = $customer->full_name;
        /** @var User $user */
        $user = auth()->user();
        $this->customerService->deleteCustomer($user, $customer);

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer '{$customerName}' was deleted successfully.");
    }
}
