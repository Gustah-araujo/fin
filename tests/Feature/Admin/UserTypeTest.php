<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserTypeTest extends TestCase
{
    public function test_new_users_default_to_customer_type(): void
    {
        $user = User::factory()->create();

        $storedType = DB::table('users')->where('id', $user->id)->value('type');

        $this->assertSame('customer', $storedType);
    }

    public function test_default_value_is_customer_on_schema(): void
    {
        $userId = DB::table('users')->insertGetId([
            'uuid' => (string) str()->uuid(),
            'name' => 'Raw User',
            'email' => 'raw@example.com',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storedType = DB::table('users')->where('id', $userId)->value('type');

        $this->assertSame('customer', $storedType);
    }

    public function test_type_is_not_mass_assignable(): void
    {
        $created = User::create([
            'uuid' => (string) str()->uuid(),
            'name' => 'Direct Create',
            'email' => 'direct@example.com',
            'password' => 'password',
            'type' => 'admin',
        ]);

        $created->refresh();
        $this->assertSame('customer', $created->type->value);

        $fillAttempt = new User;
        $fillAttempt->fill([
            'name' => 'Fill Attempt',
            'type' => 'admin',
        ]);

        $this->assertNull($fillAttempt->type);
    }

    public function test_manual_db_update_persists_as_admin(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['type' => UserType::Admin])->save();
        $user->refresh();

        $this->assertSame(UserType::Admin, $user->type);
        $this->assertSame('admin', DB::table('users')->where('id', $user->id)->value('type'));
    }

    public function test_type_enum_cast_returns_user_type(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['type' => UserType::Admin])->save();
        $admin->refresh();

        $customer = User::factory()->create();
        $customer->refresh();

        $this->assertInstanceOf(UserType::class, $admin->type);
        $this->assertInstanceOf(UserType::class, $customer->type);
        $this->assertSame('Administrador', $admin->type->label());
        $this->assertSame('Cliente', $customer->type->label());
    }
}
