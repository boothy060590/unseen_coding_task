<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\SearchService;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Requests\BulkCustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private SearchService $searchService
    ) {}

    /**
     * Display a listing of customers
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['search', 'organization', 'job_title', 'created_from', 'created_to']);
        $perPage = min($request->get('per_page', 15), 100);

        if (!empty($filters)) {
            $customers = $this->searchService->searchCustomers($user, $filters, $perPage);
        } else {
            $dashboardData = $this->customerService->getDashboardData($user, [], $perPage);
            $customers = $dashboardData['customers'];
        }

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->createCustomer(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'message' => 'Customer created successfully',
                'data' => $customer,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'data' => $customer,
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        try {
            $updatedCustomer = $this->customerService->updateCustomer(
                $request->user(),
                $customer,
                $request->validated()
            );

            return response()->json([
                'message' => 'Customer updated successfully',
                'data' => $updatedCustomer,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->customerService->deleteCustomer(auth()->user(), $customer);

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Search customers
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 25), 100);
        
        if (strlen($query) < 2) {
            return response()->json([
                'data' => [],
                'message' => 'Query must be at least 2 characters',
            ]);
        }

        $customers = $this->searchService->searchCustomersByText($user, $query, [], $limit);

        return response()->json([
            'data' => $customers,
            'query' => $query,
            'count' => $customers->count(),
        ]);
    }

    /**
     * Get customer statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $statistics = $this->customerService->getCustomerStatistics($user);

        return response()->json([
            'data' => $statistics,
        ]);
    }

    /**
     * Get customers by organization
     */
    public function byOrganization(Request $request, string $organization): JsonResponse
    {
        $user = $request->user();
        $customers = $this->customerService->getCustomersByOrganization($user, $organization);

        return response()->json([
            'data' => $customers,
            'organization' => $organization,
            'count' => $customers->count(),
        ]);
    }

    /**
     * Bulk delete customers
     */
    public function bulkDelete(BulkCustomerRequest $request): JsonResponse
    {
        $user = $request->user();
        $customerIds = $request->get('customer_ids');

        // Get customers (validation already ensures they belong to user)
        $customers = Customer::whereIn('id', $customerIds)
            ->where('user_id', $user->id)
            ->get();

        $deletedCount = 0;
        foreach ($customers as $customer) {
            $this->customerService->deleteCustomer($user, $customer);
            $deletedCount++;
        }

        return response()->json([
            'message' => "Successfully deleted {$deletedCount} customers",
            'deleted_count' => $deletedCount,
        ]);
    }
}
