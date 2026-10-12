<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id03AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 03 - 1: メールアドレスが未入力の場合のバリデーション検証
     */
    public function test_id03_1_email_required_validation(): void
    {
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    /**
     * ID 03 - 2: パスワードが未入力の場合のバリデーション検証
     */
    public function test_id03_2_password_required_validation(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    /**
     * ID 03 - 3: 登録内容と一致しない場合のログインブロック検証
     */
    public function test_id03_3_wrong_credentials_validation(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'wrong-admin@example.com',
            'password' => 'password',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
    }
}
