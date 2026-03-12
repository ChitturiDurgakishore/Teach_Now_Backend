<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication()
    {
        $response = $this->getJson('/api/auth/profile');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_profile()
    {
        $user = User::factory()->create();
        $this->actingAs($user); // uses web guard, session cookies

        $response = $this->getJson('/api/auth/profile');
        $response->assertStatus(200)
                 ->assertJson([ 
                     'status' => true,
                     'data' => [
                         'id' => $user->id,
                         'email' => $user->email,
                         'name' => $user->name,
                         'role' => $user->role,
                     ],
                 ]);
    }
}
