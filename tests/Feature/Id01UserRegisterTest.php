<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id01UserRegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 01 - 1: お名前が未入力の場合のバリデーション検証
     */
    public function test_id01_1_name_required_validation(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    /**
     * ID 01 - 2: メールアドレスが未入力の場合のバリデーション検証
     */
    public function test_id01_2_email_required_validation(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    /**
     * ID 01 - 3: パスワードが8文字未満の場合のバリデーション検証
     */
    public function test_id01_3_password_length_validation(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '7char',
            'password_confirmation' => '7char',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    /**
     * ID 01 - 4: パスワードが確認用と一致しない場合のバリデーション検証
     */
    public function test_id01_4_password_mismatch_validation(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_pass',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません']);
    }

    /**
     * ID 01 - 5: パスワードが未入力の場合のバリデーション検証
     */
    public function test_id01_5_password_required_validation(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    /**
     * ID 01 - 6: 重複メールアドレス時の登録ブロック検証
     */
    public function test_id01_6_duplicate_email_validation(): void
    {
        User::create([
            'name' => '既存ユーザー',
            'email' => 'duplicate@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/register', [
            'name' => '新規ユーザー',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email']);
    }

    /**
     * ID 01 - 7: 正しい入力値での正常会員登録フローの検証
     */
    public function test_id01_7_successful_registration_flow(): void
    {
        $response = $this->post('/register', [
            'name' => '新しい一般スタッフ',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'name' => '新しい一般スタッフ',
            'email' => 'newuser@example.com',
            'admin_status' => false,
            'email_verified_at' => null, // 会員登録直後は未認証
        ]);
    }
}
