<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * 👑 【仕様書完全適合：エラーレスポンスの統一ガード（Laravel 10版）】
     * API（api/*）エンドポイントでエラーが起きた際、指定の日本語JSONを一文字違わず自動返却させます！
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                
                // ① 404 Not Found
                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'error' => '勤怠情報が見つかりませんでした。'
                    ], 404);
                }

                // ② 403 Forbidden
                if ($e instanceof AuthorizationException) {
                    return response()->json([
                        'error' => 'この操作を実行する権限がありません。'
                    ], 403);
                }
            }
        });
    }

    /**
     * ③ 401 Unauthorized (Sanctum未認証時の一文字違いメッセージ適合)
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }
}
