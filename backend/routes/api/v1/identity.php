<?php

declare(strict_types=1);

use App\Enums\Identity\Ability;
use App\Http\Controllers\Api\V1\Identity\AcceptInvitationController;
use App\Http\Controllers\Api\V1\Identity\ActivateUserController;
use App\Http\Controllers\Api\V1\Identity\ChangeOwnEmailController;
use App\Http\Controllers\Api\V1\Identity\ChangePasswordController;
use App\Http\Controllers\Api\V1\Identity\ConfirmMfaEnrollmentController;
use App\Http\Controllers\Api\V1\Identity\ConfirmOwnEmailChangeController;
use App\Http\Controllers\Api\V1\Identity\CurrentSessionController;
use App\Http\Controllers\Api\V1\Identity\DisableMfaController;
use App\Http\Controllers\Api\V1\Identity\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Identity\LoginController;
use App\Http\Controllers\Api\V1\Identity\LoginModesController;
use App\Http\Controllers\Api\V1\Identity\LogoutController;
use App\Http\Controllers\Api\V1\Identity\MfaChallengeController;
use App\Http\Controllers\Api\V1\Identity\MyPermissionsController;
use App\Http\Controllers\Api\V1\Identity\MySessionsController;
use App\Http\Controllers\Api\V1\Identity\PermissionController;
use App\Http\Controllers\Api\V1\Identity\PreferencesController;
use App\Http\Controllers\Api\V1\Identity\RecoveryCodesController;
use App\Http\Controllers\Api\V1\Identity\RequestOtpController;
use App\Http\Controllers\Api\V1\Identity\ResendInvitationController;
use App\Http\Controllers\Api\V1\Identity\ResendOtpController;
use App\Http\Controllers\Api\V1\Identity\ResetPasswordController;
use App\Http\Controllers\Api\V1\Identity\ResetUserMfaController;
use App\Http\Controllers\Api\V1\Identity\RevokeOtherSessionsController;
use App\Http\Controllers\Api\V1\Identity\RoleController;
use App\Http\Controllers\Api\V1\Identity\RolePermissionsController;
use App\Http\Controllers\Api\V1\Identity\StartMfaEnrollmentController;
use App\Http\Controllers\Api\V1\Identity\SuspendUserController;
use App\Http\Controllers\Api\V1\Identity\UnlockUserController;
use App\Http\Controllers\Api\V1\Identity\UserController;
use App\Http\Controllers\Api\V1\Identity\VerifyOtpController;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Route;

$authenticated = Ability::Authenticated->value;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::get('login-modes', LoginModesController::class)->middleware('throttle:public')->name('login-modes');
    Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
    Route::post('otp/request', RequestOtpController::class)->middleware('throttle:otp-request')->name('otp.request');
    Route::post('otp/verify', VerifyOtpController::class)->middleware('throttle:otp-verify')->name('otp.verify');
    Route::post('otp/resend', ResendOtpController::class)->middleware('throttle:otp-resend')->name('otp.resend');
    Route::post('mfa/challenge', MfaChallengeController::class)->middleware('throttle:mfa')->name('mfa.challenge');
    Route::post('mfa/enroll', StartMfaEnrollmentController::class)->middleware('throttle:mfa')->name('mfa.enroll');
    Route::post('mfa/confirm', ConfirmMfaEnrollmentController::class)->middleware('throttle:mfa')->name('mfa.confirm');
    Route::post('password/forgot', ForgotPasswordController::class)->middleware('throttle:password-reset')->name('password.forgot');
    Route::post('password/reset', ResetPasswordController::class)->middleware('throttle:password-reset')->name('password.reset');
    Route::post('invitations/{token}/accept', AcceptInvitationController::class)->middleware('throttle:invitation')->name('invitations.accept');
});

Route::middleware(['auth:sanctum', 'session.timeouts', 'mfa'])->group(function () use ($authenticated): void {
    Route::get('auth/session', CurrentSessionController::class)->middleware("can:{$authenticated}")->name('auth.session');
    Route::post('auth/logout', LogoutController::class)->middleware("can:{$authenticated}")->name('auth.logout');
    Route::post('auth/password/change', ChangePasswordController::class)->middleware(["can:{$authenticated}", 'throttle:sensitive'])->name('auth.password.change');

    Route::middleware('password.current')->group(function () use ($authenticated): void {
        Route::post('auth/mfa/recovery-codes', RecoveryCodesController::class)->middleware(["can:{$authenticated}", 'throttle:sensitive'])->name('auth.mfa.recovery-codes');
        Route::delete('auth/mfa', DisableMfaController::class)->middleware(["can:{$authenticated}", 'throttle:sensitive'])->name('auth.mfa.disable');

        Route::prefix('me')->name('me.')->middleware("can:{$authenticated}")->group(function (): void {
            Route::get('preferences', [PreferencesController::class, 'show'])->name('preferences.show');
            Route::patch('preferences', [PreferencesController::class, 'update'])->name('preferences.update');
            Route::get('sessions', [MySessionsController::class, 'index'])->name('sessions.index');
            Route::delete('sessions', RevokeOtherSessionsController::class)->name('sessions.destroy-others');
            Route::delete('sessions/{session}', [MySessionsController::class, 'destroy'])->name('sessions.destroy');
            Route::get('profile/permissions', MyPermissionsController::class)->name('profile.permissions');
        });

        Route::prefix('me/email')->name('me.email.')->middleware(['can:'.Ability::SuperAdminSelfService->value, 'throttle:sensitive'])->group(function (): void {
            Route::post('change', ChangeOwnEmailController::class)->name('change');
            Route::post('confirm', ConfirmOwnEmailChangeController::class)->name('confirm');
        });

        Route::get('users', [UserController::class, 'index'])->middleware('can:viewAny,'.User::class)->name('users.index');
        Route::post('users', [UserController::class, 'store'])->middleware(['can:create,'.User::class, 'throttle:sensitive'])->name('users.store');
        Route::get('users/{managedUser}', [UserController::class, 'show'])->middleware('can:view,managedUser')->name('users.show');
        Route::put('users/{managedUser}', [UserController::class, 'update'])->middleware(['can:update,managedUser', 'throttle:sensitive'])->name('users.update');
        Route::post('users/{managedUser}/suspend', SuspendUserController::class)->middleware(['can:changeStatus,managedUser', 'throttle:sensitive'])->name('users.suspend');
        Route::post('users/{managedUser}/activate', ActivateUserController::class)->middleware(['can:changeStatus,managedUser', 'throttle:sensitive'])->name('users.activate');
        Route::post('users/{managedUser}/unlock', UnlockUserController::class)->middleware(['can:changeStatus,managedUser', 'throttle:sensitive'])->name('users.unlock');
        Route::post('users/{managedUser}/invitation/resend', ResendInvitationController::class)->middleware(['can:update,managedUser', 'throttle:sensitive'])->name('users.invitation.resend');
        Route::post('users/{managedUser}/mfa/reset', ResetUserMfaController::class)->middleware(['can:resetMfa,managedUser', 'throttle:sensitive'])->name('users.mfa.reset');

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:'.Ability::ListRoles->value)->name('roles.index');
        Route::put('roles/{role:name}/permissions', RolePermissionsController::class)->middleware(['can:manageMatrix,role', 'throttle:sensitive'])->name('roles.permissions.update');
        Route::get('permissions', [PermissionController::class, 'index'])->middleware('can:viewMatrix,'.Role::class)->name('permissions.index');
        Route::put('permissions/{permission:name}', [PermissionController::class, 'update'])->middleware(['can:updateLabels,permission', 'throttle:sensitive'])->name('permissions.update');
    });
});
