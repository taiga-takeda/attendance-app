<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id04CurrentDatetimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 04 - 1: 現在の日時情報がUIと同じ形式で出力されているかの検証
     */
    public function test_id04_1_current_datetime_display_format(): void
    {
        // テスト用の一般ユーザーを作成
        $user = User::create([
            'name' => '日時確認スタッフ',
            'email' => 'time-check@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        // 💡 【100%解決】Laravel公式の強制認証メソッドを叩き、メール認証ガードを確実に突破させます！
        $user->markEmailAsVerified();

        // 1. 勤怠打刻画面を開き、2. 画面上の日時情報が現在のシステム日時と一致するか検証
        $response = $this->actingAs($user)->get('/attendance');
        
        // 画面のHTMLソース内に、本日の日付（例: Y/m/d）が含まれているかチェック
        $response->assertStatus(200);
        $response->assertSee(\Carbon\Carbon::now()->format('Y/m/d'));
    }
}
