<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id02UserLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 02 - 1: メールアドレスが未入力の場合のバリデーション検証
     */
    public function test_id02_1_email_required_validation(): void
    {
        // 検証用の一般ユーザーを1名作成（メール認証済みの正規ユーザーとします）
        $user = User::create([
            'name' => 'テストユーザー一号',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    /**
     * ID 02 - 2: パスワードが未入力の場合のバリデーション検証
     */
    public function test_id02_2_password_required_validation(): void
    {
        // 検証用の一般ユーザーを1名作成
        $user = User::create([
            'name' => 'テストユーザー一号',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'user1@example.com',
            'password' => '',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    /**
     * ID 02 - 3: 登録内容と一致しない場合のログインブロック検証
     */
    public function test_id02_3_wrong_credentials_validation(): void
    {
        // 検証用の一般ユーザーを1名作成
        $user = User::create([
            'name' => 'テストユーザー一号',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'password',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
    }
}
