<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    public function test_each_role_reaches_only_its_areas(): void
    {
        $school = $this->school('BNG');
        $student = Student::factory()->for($school)->create();

        $expectations = [
            // route => [school_admin, registrar, printer, viewer]
            route('students.index') => [200, 200, 200, 200],
            route('students.create') => [200, 200, 403, 403],
            route('students.edit', $student) => [200, 200, 403, 403],
            route('students.import') => [200, 200, 403, 403],
            route('staff.create') => [200, 200, 403, 403],
            route('templates.id-cards.index') => [200, 200, 200, 200],
            route('templates.id-cards.create') => [200, 403, 403, 403],
            route('id-cards.generate') => [200, 403, 200, 403],
            route('certificates.generate') => [200, 200, 403, 403],
            route('print.index') => [200, 200, 200, 200],
            route('reports.index') => [200, 200, 403, 200],
            route('users.index') => [200, 403, 403, 403],
            route('audit.index') => [200, 403, 403, 403],
            route('school-profile.edit') => [200, 403, 403, 403],
            route('schools.index') => [403, 403, 403, 403],
            route('settings.edit') => [403, 403, 403, 403],
            route('roles.index') => [403, 403, 403, 403],
        ];

        foreach ([Role::SCHOOL_ADMIN, Role::REGISTRAR, Role::PRINTER, Role::VIEWER] as $i => $role) {
            $user = $this->userFor($school, $role);
            foreach ($expectations as $url => $statuses) {
                $this->actingAs($user)->get($url)->assertStatus($statuses[$i], "{$role} → {$url}");
            }
        }
    }

    public function test_super_admin_has_full_access(): void
    {
        $school = $this->school('BNG');
        $super = $this->userFor(null);

        foreach (['schools.index', 'schools.create', 'settings.edit', 'roles.index', 'audit.index', 'users.index', 'students.index', 'reports.index'] as $route) {
            $this->actingAs($super)->get(route($route))->assertOk();
        }
        $this->actingAs($super)->get(route('schools.show', $school))->assertOk();
    }

    public function test_super_admin_must_select_a_school_before_creating_school_records(): void
    {
        $school = $this->school('BNG');
        $super = $this->userFor(null);

        $this->actingAs($super)->get(route('students.create'))->assertRedirect(route('context.select', ['return' => route('students.create')]));
        $this->actingAs($super)->withSession(['active_school_id' => $school->id])->get(route('students.create'))->assertOk();
    }

    public function test_role_permissions_can_be_edited_but_reserved_ones_are_never_granted(): void
    {
        $super = $this->userFor(null);
        $viewer = Role::where('slug', Role::VIEWER)->first();
        $ids = Permission::whereIn('slug', ['students.view', 'students.create', 'settings.system'])->pluck('id')->all();

        $this->actingAs($super)->put(route('roles.update', $viewer), ['permissions' => $ids])->assertRedirect();

        $slugs = $viewer->fresh()->permissions->pluck('slug')->all();
        $this->assertContains('students.create', $slugs);
        $this->assertNotContains('settings.system', $slugs);

        $school = $this->school('BNG');
        $this->actingAs($this->userFor($school, Role::VIEWER))->get(route('students.create'))->assertOk();
    }

    public function test_school_admin_cannot_edit_roles(): void
    {
        $school = $this->school('BNG');
        $viewer = Role::where('slug', Role::VIEWER)->first();

        $this->actingAs($this->userFor($school))->put(route('roles.update', $viewer), ['permissions' => []])->assertForbidden();
    }
}
