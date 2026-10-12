<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        // Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        // Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        // Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // 新規登録時に使用するBladeファイルの指定
        Fortify::registerView(function () {
            return view('user.register');
        });

        // 一般ユーザーログイン時に使用するBladeファイルの指定
        Fortify::loginView(function () {
            // 💡 URLにadminが含まれる場合は、管理者専用のログイン画面を表示します [FN016]
            if (request()->is('admin/*') || request()->is('admin/login')) {
                return view('admin.admin-login');
            }
            return view('user.user-login');
        });

        Fortify::verifyEmailView(function () {
            return view('auth.verify-email');
        });

        // 1. ログイン成功後の遷移先を設定（ここは元のまま触りません）
        $this->app->instance(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            new class implements \Laravel\Fortify\Contracts\LoginResponse {
                public function toResponse($request)
                {
                    $user = auth()->user();

                    if ($request->is('admin/*') || ($user && $user->admin_status)) {
                        return redirect()->intended('/admin/attendance/list');
                    }

                    return redirect()->intended('/attendance');
                }
            }
        );

        // ログアウト成功後のリダイレクト先を自動出し分け
        $this->app->instance(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            new class implements \Laravel\Fortify\Contracts\LogoutResponse {
                public function toResponse($request)
                {
                    if (str_contains(request()->headers->get('referer'), '/admin/')) {
                        return redirect('/admin/login');
                    }

                    return redirect('/login');
                }
            }
        );

        // 会員登録直後の遷移先を設定（メール認証未実装時は打刻画面へ直行）
        $this->app->instance(
            \Laravel\Fortify\Contracts\RegisterResponse::class,
            new class implements \Laravel\Fortify\Contracts\RegisterResponse {
                public function toResponse($request)
                {
                    return redirect('/attendance');
                }
            }
        );
    }

}
