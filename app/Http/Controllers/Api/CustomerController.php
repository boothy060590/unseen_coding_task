<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
        private SearchService $searchService
    ) {
        $this->middleware('auth:sanctum');
    }

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
    public function store(Request $request): JsonResponse
    {
        try {
            $customer = $this->customerService->createCustomer(
                $request->user(),
                $request->all()
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
    public function update(Request $request, Customer $customer): JsonResponse
    {
        try {
            $updatedCustomer = $this->customerService->updateCustomer(
                $request->user(),
                $customer,
                $request->all()
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
     * Bulk operations
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:delete,export',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $user = $request->user();
        $action = $request->get('action');
        $customerIds = $request->get('customer_ids');

        // Verify all customers belong to the user
        $customers = Customer::whereIn('id', $customerIds)
            ->where('user_id', $user->id)
            ->get();

        if ($customers->count() !== count($customerIds)) {
            return response()->json([
                'message' => 'Some customers not found or not accessible',
            ], 404);
        }

        switch ($action) {
            case 'delete':
                $deletedCount = 0;
                foreach ($customers as $customer) {
                    $this->customerService->deleteCustomer($user, $customer);
                    $deletedCount++;
                }

                return response()->json([
                    'message' => "Successfully deleted {$deletedCount} customers",
                    'deleted_count' => $deletedCount,
                ]);

            case 'export':
                // This would trigger an export job
                return response()->json([
                    'message' => 'Export started for selected customers',
                    'customer_count' => $customers->count(),
                ]);

            default:
                return response()->json([
                    'message' => 'Invalid action',
                ], 400);
        }
    }
}
