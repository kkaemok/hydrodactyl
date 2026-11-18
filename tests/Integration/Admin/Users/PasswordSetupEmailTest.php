<?php

namespace Pterodactyl\Tests\Integration\Admin\Users;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Pterodactyl\Models\User;
use Pterodactyl\Notifications\SendPasswordReset;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class PasswordSetupEmailTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    public function testAnAdminCanResendPasswordSetupEmailWithoutChangingThePassword(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $existingHash = $user->password;

        $this->actingAs($admin)
            ->postJson("/admin/api/users/{$user->id}/password-reset")
            ->assertStatus(202)
            ->assertJsonPath('status', 'queued');

        Notification::assertSentTo($user, SendPasswordReset::class, function (SendPasswordReset $notification) use ($user) {
            return Password::tokenExists($user, $notification->token);
        });

        $this->assertSame($existingHash, $user->fresh()->password);
    }

    public function testANonAdminCannotQueueAnotherUsersPasswordReset(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/admin/api/users/{$target->id}/password-reset")
            ->assertForbidden();

        Notification::assertNothingSent();
    }
}
