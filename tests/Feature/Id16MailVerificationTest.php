<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * [ID16] ★ メール認証機能に関する応用自動テスト
 */
class Id16MailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録後、認証メールが送信され、メール内のリンク完了で勤怠登録画面に遷移することを検証
     *
     * @return void
     */
    public function test_mail_verification_flow_is_perfect(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => '認証テストスタッフ',
            'email' => 'verify-test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'verify-test@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $verifyResponse = $this->actingAs($user)->get($verificationUrl);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $verifyResponse->assertRedirect(route('attendance') . '?verified=1');
    }
}
