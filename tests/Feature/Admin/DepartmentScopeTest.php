<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_departments_are_seeded(): void
    {
        $this->seed(DepartmentSeeder::class);

        $this->assertGreaterThan(0, Department::count());
        $this->assertTrue(Department::where('code', 'GW')->exists());
    }
}
