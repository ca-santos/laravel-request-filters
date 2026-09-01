<?php

declare(strict_types=1);

namespace CaueSantos\LaravelRequestFilters\Tests;

use CaueSantos\LaravelRequestFilters\RequestFiltersServiceProvider;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\Company;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\Post;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\Tag;
use CaueSantos\LaravelRequestFilters\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [RequestFiltersServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('laravel-request-filters.models_folder', __DIR__.'/Fixtures');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('status')->default('active');
            $table->unsignedInteger('age')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id');
            $table->foreignId('tag_id');
        });
    }

    protected function makeCompany(array $attributes = []): Company
    {
        return Company::create(array_merge(['name' => 'Acme'], $attributes));
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'age' => 30,
        ], $attributes));
    }

    protected function makePost(User $user, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Hello world',
            'body' => 'Some body text',
        ], $attributes));
    }

    protected function makeTag(array $attributes = []): Tag
    {
        return Tag::create(array_merge(['name' => 'php'], $attributes));
    }
}
