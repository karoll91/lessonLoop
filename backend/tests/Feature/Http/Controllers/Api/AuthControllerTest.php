<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_returns_a_token_and_records_the_time(): void
    {
        $user = User::factory()->create(['login' => 'sh.mahkamov']);

        $this->postJson(route('login'), ['login' => 'sh.mahkamov', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.login', 'sh.mahkamov')
            ->assertJsonPath('user.must_change_password', false);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_returns_422_for_a_wrong_password(): void
    {
        User::factory()->create(['login' => 'sh.mahkamov']);

        $this->postJson(route('login'), ['login' => 'sh.mahkamov', 'password' => 'wrong-pass'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');
    }

    public function test_changing_the_password_clears_the_flag_and_signs_out_other_devices(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $user->createToken('old-phone');
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->postJson(route('me.password'), [
            'current_password' => 'password',
            'password' => 'yangi-parol-2026',
            'password_confirmation' => 'yangi-parol-2026',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);

        $user->refresh();
        $this->assertTrue(Hash::check('yangi-parol-2026', $user->password));
        $this->assertSame(['web'], $user->tokens()->pluck('name')->all());
    }

    public function test_returns_422_when_the_current_password_is_wrong(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->postJson(route('me.password'), [
            'current_password' => 'not-it',
            'password' => 'yangi-parol-2026',
            'password_confirmation' => 'yangi-parol-2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }
}
