<?php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
 * Helper: create an authenticated customer with one saved address.
 */
function aCustomerWithAddress(): array
{
    $user = \App\Models\User::factory()->create();
    $address = \App\Models\Address::factory()->default()->forUser($user)->create();
    return [$user, $address];
}
