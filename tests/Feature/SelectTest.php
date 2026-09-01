<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests\Feature;

use CaueSantos\LaravelRequestFilters\Criteria\SelectCriteria;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use CaueSantos\LaravelRequestFilters\Tests\TestCase;

class SelectTest extends TestCase
{
    public function test_select_local_fields(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'last_name' => 'Doe', 'email' => 'a@x.com']);

        $result = (new SelectCriteria(
            User::query(),
            collect(['select' => 'first_name,email']),
            User::criteria()
        ))->apply()->first();

        $this->assertSame('Alice', $result->first_name);
        $this->assertSame('a@x.com', $result->email);
    }

    public function test_select_relation_fields_hydrates_the_relation(): void
    {
        $company = $this->makeCompany(['name' => 'Acme']);
        $this->makeUser(['first_name' => 'Alice', 'company_id' => $company->id]);

        $result = (new SelectCriteria(
            User::query(),
            collect(['select' => 'first_name,company.name']),
            User::criteria()
        ))->apply()->first();

        $this->assertSame('Alice', $result->first_name);
        $this->assertTrue($result->relationLoaded('company'));
        $this->assertSame('Acme', $result->company->name);
    }

    public function test_select_nested_relation_fields(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUser(['company_id' => $company->id]);
        $this->makePost($user, ['title' => 'Nested select']);

        $result = (new SelectCriteria(
            \CaueSantos\LaravelRequestFilters\Tests\Fixtures\Company::query(),
            collect(['select' => 'name,users.posts.title']),
            (new \CaueSantos\LaravelRequestFilters\Tests\Fixtures\Company)->criteria()
        ))->apply()->first();

        $this->assertSame('Acme', $result->name);
    }
}
