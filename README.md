# 勤怠管理アプリケーション

一般ユーザーの打刻・修正申請機能、および管理者による勤怠管理・承認機能を備えた勤怠管理システムです。

## 使用技術

- PHP 8.2
- Laravel 10.x
- Laravel Fortify / Laravel Sanctum
- MySQL 8.x
- Docker / Laravel Sail
- Mailpit / Mailtrap

## 環境構築方法

プロジェクトのクローン後、ターミナルで以下のコマンドを順番に実行して環境構築を行ってください。

```bash
# 1. コンテナの起動
./vendor/bin/sail up -d

# 2. 暗号化キーの生成
./vendor/bin/sail artisan key:generate

# 3. マイグレーションとテスト用ダミーデータの作成
./vendor/bin/sail artisan migrate --seed

# 4. フロントエンドパッケージのインストール
./vendor/bin/sail npm install

# 5. ビルドサーバーの起動
./vendor/bin/sail npm run dev
```

## 動作確認用 URL

- **アプリケーション本体**: [http://localhost](http://localhost)
- **データベース管理（phpMyAdmin）**: [http://localhost:8080](http://localhost:8080)
- **メールボックス（Mailpit）**: [http://localhost:8025](http://localhost:8025)

## テスト用ログイン情報

動作確認の際は、以下のSeederで自動生成されるアカウント情報をご使用ください。

### 1. 一般ユーザー1（統計レポート検証用）
- **メールアドレス**: `user1@example.com`
- **パスワード**: `password`
- **状態**: メール認証済み
- **備考**: 過去6ヶ月分のテスト用勤怠データ（総労働時間744時間、遅刻2回、早退1回等）が自動的に登録されています。

### 2. 一般ユーザー2
- **メールアドレス**: `user2@example.com`
- **パスワード**: `password`
- **状態**: メール認証済み

### 3. 管理者ユーザー3
- **メールアドレス**: `user3@example.com`
- **パスワード**: `password`
- **状態**: メール認証済み（`admin_status = true`）

### 4. ER図
![ER図](ER.png)