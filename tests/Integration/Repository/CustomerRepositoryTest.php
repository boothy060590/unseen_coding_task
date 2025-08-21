<?php

namespace Tests\Integration\Repository;

use App\Models\Customer;
use App\Models\User;
use App\Repositories\CustomerRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CustomerRepository::class)]
class CustomerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function testGetAllForUser(): void
    {
        // No Filters
        $validUser = User::factory()->create();
        $invalidUser = User::factory()->create();
        Customer::factory(2)->create(['user_id' => $validUser->id]); // Will be returned
        Customer::factory(2)->create(['user_id' => $invalidUser->id]); //Won't be

        $repository = new CustomerRepository();
        $result = $repository->getAllForUser($validUser);

        $this->assertCount(2, $result);
        $result->each(function (Customer $customer) use ($validUser, $invalidUser) {
            $this->assertSame($validUser->id, $customer->user_id);
            $this->assertNotSame($invalidUser->id, $customer->user_id);
        });
    }
}
