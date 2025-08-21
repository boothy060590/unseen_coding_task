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

        // Now let's start adding filters starting with organisation
        $filteringCustomers = collect(['organization1', 'organization2'])
            ->map(fn (string $organization) => Customer::factory()->create([
                'user_id' => $validUser->id,
                'organization' => $organization
            ]));
        $result = $repository->getAllForUser($validUser, ['organization' => 'organization1']);
        $this->assertCount(1, $result);
        $this->assertSame($result->first()->organization, $filteringCustomers->first()->organization);

        // now let's give the filtering customers the same org but different job_title
        $filteringCustomers->each(function (Customer $customer){
           $customer->organization = 'organization1';
           $customer->job_title = "Job title: $customer->id";
           $customer->save();
        });

        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title: ' . $filteringCustomers->first()->id
            ]
        );
        $this->assertCount(1, $result);
        $this->assertSame($result->first()->job_title, $filteringCustomers->first()->job_title);

        // now set the job title the same, but different created at dates.
        $subDay = true;
        $now = now();
        $filteringCustomers->each(function (Customer $customer) use (&$subDay, $now) {
            $customer->job_title = "Job title";
            $customer->created_at = $subDay ? $now->copy()->subDay() : $now;
            $subDay = false;
            $customer->save();
        });

        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subMinutes(10)
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame(
            $now->format('Y-m-d H:i:s'),
            $result->first()->created_at->format('Y-m-d H:i:s')
        );

        // Now add created_to filter and get the result created yesterday
        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->copy()->subDay()->addMinutes(10)
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame(
            $now->copy()->subDay()->format('Y-m-d H:i:s'),
            $result->last()->created_at->format('Y-m-d H:i:s')
        );

        // Now let's apply name filtering
        $searchTerm = $filteringCustomers->first()->full_name;

        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->addMinutes(10),
                'search' => $searchTerm
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame($result->first()->full_name, $searchTerm);

        // now lets filter on e-mail
        $searchTerm = $filteringCustomers->first()->email;
        $result = $repository->getAllForUser(
            $validUser,
            [
                'organization' => 'organization1',
                'job_title' => 'Job title',
                'created_from' => $now->copy()->subDays(10),
                'created_to' => $now->addMinutes(10),
                'search' => $searchTerm
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame($result->first()->email, $searchTerm);
    }
}
