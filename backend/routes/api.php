<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminUserPasswordResetController;
use App\Http\Controllers\Admin\AdminUserStatusController;
use App\Http\Controllers\Admin\AdminUserSummaryController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserSetupController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BrowserController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileDownloadController;
use App\Http\Controllers\FileMoveController;
use App\Http\Controllers\FilePreviewController;
use App\Http\Controllers\FileRequestController;
use App\Http\Controllers\FileStarController;
use App\Http\Controllers\FileTrashController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FolderMoveController;
use App\Http\Controllers\FolderStarController;
use App\Http\Controllers\FolderTrashController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\PublicLinkListController;
use App\Http\Controllers\PublicShareBrowserController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RecentController;
use App\Http\Controllers\SecuritySessionController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\SharedBrowserController;
use App\Http\Controllers\SharedController;
use App\Http\Controllers\StarredController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/invitations/{token}', [InvitationController::class, 'preview'])->name('invitations.preview');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
Route::get('/email-verification/{token}', [EmailVerificationController::class, 'preview'])->name('verification.preview');
Route::post('/email-verification/{token}/verify', [EmailVerificationController::class, 'verify'])->name('verification.verify');

Route::prefix('public/shares')->middleware(['throttle:public-share', 'available'])->group(function (): void {
    Route::get('/{token}', [PublicShareController::class, 'show'])->name('public.shares.show');
    Route::get('/{token}/preview', [PublicShareController::class, 'preview'])->name('public.shares.preview');
    Route::get('/{token}/download', [PublicShareController::class, 'download'])->name('public.shares.download');
    Route::get('/{token}/browser', PublicShareBrowserController::class)->name('public.shares.browser');
    Route::get('/{token}/files/{file}/preview', [PublicShareController::class, 'filePreview'])->name('public.shares.files.preview');
    Route::get('/{token}/files/{file}/download', [PublicShareController::class, 'fileDownload'])->name('public.shares.files.download');
});

Route::prefix('public/file-requests')->middleware(['throttle:public-share', 'available'])->group(function (): void {
    Route::get('/{token}', [FileRequestController::class, 'show'])->name('public.file-requests.show');
    Route::post('/{token}/upload', [FileRequestController::class, 'upload'])->name('public.file-requests.upload');
});

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login')
        ->name('auth.login');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:password-reset-request')
        ->name('auth.forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->name('auth.reset-password');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', [AuthController::class, 'user'])
            ->middleware(['active.account', 'available'])
            ->name('auth.user');
        Route::patch('/profile', [AuthController::class, 'updateProfile'])
            ->middleware(['active.account', 'available'])
            ->name('auth.profile.update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});

Route::middleware(['auth:sanctum', 'active.account', 'available'])
    ->group(function (): void {
        Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
        Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
        Route::get('/folders/{folder}', [FolderController::class, 'show'])->name('folders.show');
        Route::patch('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
        Route::post('/folders/{folder}/move', FolderMoveController::class)->name('folders.move');
        Route::get('/folders/{folder}/breadcrumb', [FolderController::class, 'breadcrumb'])->name('folders.breadcrumb');
        Route::get('/files', [FileController::class, 'index'])->name('files.index');
        Route::get('/browser', BrowserController::class)->name('browser.index');
        Route::get('/recent', RecentController::class)->name('recent.index');
        Route::get('/starred', StarredController::class)->name('starred.index');
        Route::get('/storage', StorageController::class)->name('storage.show');
        Route::get('/activity', ActivityController::class)->name('activity.index');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
        Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
        Route::get('/security/sessions', [SecuritySessionController::class, 'index'])->name('security.sessions.index');
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('/push-subscriptions/current', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::get('/users/search', UserSearchController::class)->name('users.search');
        Route::get('/shared/with-me', [SharedController::class, 'withMe'])->name('shared.with-me');
        Route::get('/shared/by-me', [SharedController::class, 'byMe'])->name('shared.by-me');
        Route::get('/shared/links', PublicLinkListController::class)->name('shared.links');
        Route::get('/file-requests', [FileRequestController::class, 'index'])->name('file-requests.index');
        Route::post('/file-requests', [FileRequestController::class, 'store'])->name('file-requests.store');
        Route::patch('/file-requests/{fileRequest}', [FileRequestController::class, 'update'])->name('file-requests.update');
        Route::delete('/file-requests/{fileRequest}', [FileRequestController::class, 'destroy'])->name('file-requests.destroy');
        Route::get('/shared/folders/{folder}/browser', SharedBrowserController::class)->name('shared.folders.browser');
        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::delete('/trash', [TrashController::class, 'destroy'])->name('trash.destroy');
        Route::post('/files/upload', FileUploadController::class)->name('files.upload');
        Route::get('/files/{file}/download', FileDownloadController::class)->name('files.download');
        Route::get('/files/{file}/preview', FilePreviewController::class)->name('files.preview');
        Route::get('/files/{file}/details', [FileController::class, 'details'])->name('files.details');
        Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
        Route::patch('/files/{file}', [FileController::class, 'update'])->name('files.update');
        Route::post('/files/{file}/move', FileMoveController::class)->name('files.move');
        Route::post('/files/{file}/star', [FileStarController::class, 'store'])->name('files.star');
        Route::delete('/files/{file}/star', [FileStarController::class, 'destroy'])->name('files.unstar');
        Route::post('/folders/{folder}/star', [FolderStarController::class, 'store'])->name('folders.star');
        Route::get('/folders/{folder}/details', [FolderController::class, 'details'])->name('folders.details');
        Route::delete('/folders/{folder}/star', [FolderStarController::class, 'destroy'])->name('folders.unstar');
        Route::post('/files/{file}/public-link', [PublicLinkController::class, 'fileStore'])->name('files.public-link.store');
        Route::get('/files/{file}/public-link', [PublicLinkController::class, 'fileShow'])->name('files.public-link.show');
        Route::delete('/files/{file}/public-link', [PublicLinkController::class, 'fileDestroy'])->name('files.public-link.destroy');
        Route::post('/files/{file}/public-link/regenerate', [PublicLinkController::class, 'fileRegenerate'])->name('files.public-link.regenerate');
        Route::patch('/files/{file}/public-link', [PublicLinkController::class, 'fileUpdate'])->name('files.public-link.update');
        Route::post('/folders/{folder}/public-link', [PublicLinkController::class, 'folderStore'])->name('folders.public-link.store');
        Route::get('/folders/{folder}/public-link', [PublicLinkController::class, 'folderShow'])->name('folders.public-link.show');
        Route::delete('/folders/{folder}/public-link', [PublicLinkController::class, 'folderDestroy'])->name('folders.public-link.destroy');
        Route::post('/folders/{folder}/public-link/regenerate', [PublicLinkController::class, 'folderRegenerate'])->name('folders.public-link.regenerate');
        Route::patch('/folders/{folder}/public-link', [PublicLinkController::class, 'folderUpdate'])->name('folders.public-link.update');
        Route::post('/files/{file}/shares', [ShareController::class, 'fileStore'])->name('files.shares.store');
        Route::get('/files/{file}/shares', [ShareController::class, 'fileIndex'])->name('files.shares.index');
        Route::post('/folders/{folder}/shares', [ShareController::class, 'folderStore'])->name('folders.shares.store');
        Route::get('/folders/{folder}/shares', [ShareController::class, 'folderIndex'])->name('folders.shares.index');
        Route::patch('/shares/{share}', [ShareController::class, 'update'])->name('shares.update');
        Route::delete('/shares/{share}', [ShareController::class, 'destroy'])->name('shares.destroy');
        Route::post('/files/{file}/trash', [FileTrashController::class, 'trash'])->name('files.trash');
        Route::post('/files/{file}/restore', [FileTrashController::class, 'restore'])->name('files.restore');
        Route::delete('/files/{file}/permanent', [FileTrashController::class, 'permanent'])->name('files.permanent');
        Route::post('/folders/{folder}/trash', [FolderTrashController::class, 'trash'])->name('folders.trash');
        Route::post('/folders/{folder}/restore', [FolderTrashController::class, 'restore'])->name('folders.restore');
        Route::delete('/folders/{folder}/permanent', [FolderTrashController::class, 'permanent'])->name('folders.permanent');
    });

Route::prefix('admin/users')
    ->middleware(['auth:sanctum', 'active.account', 'admin'])
    ->group(function (): void {
        Route::get('/', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/summary', AdminUserSummaryController::class)->name('admin.users.summary');
        Route::post('/invitations', [UserSetupController::class, 'createInvitation'])->name('admin.users.invitations.create');
        Route::post('/', [UserSetupController::class, 'createUser'])->name('admin.users.create');
        Route::get('/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
        Route::patch('/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::post('/{user}/disable', [AdminUserStatusController::class, 'disable'])->name('admin.users.disable');
        Route::post('/{user}/enable', [AdminUserStatusController::class, 'enable'])->name('admin.users.enable');
        Route::post('/{user}/password-reset', AdminUserPasswordResetController::class)->middleware('throttle:mail-resend')->name('admin.users.password-reset');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/{user}/invitation/resend', [UserSetupController::class, 'resendInvitation'])->middleware('throttle:mail-resend')->name('admin.users.invitations.resend');
        Route::post('/{user}/invitation/regenerate', [UserSetupController::class, 'regenerateInvitation'])->middleware('throttle:mail-resend')->name('admin.users.invitations.regenerate');
        Route::post('/{user}/verification/resend', [UserSetupController::class, 'resendVerification'])->middleware('throttle:mail-resend')->name('admin.users.verification.resend');
    });

Route::prefix('admin/settings')
    ->middleware(['auth:sanctum', 'active.account', 'admin'])
    ->group(function (): void {
        Route::get('/', [SystemSettingController::class, 'index'])->name('admin.settings.index');
        Route::get('/{group}', [SystemSettingController::class, 'show'])->name('admin.settings.show');
        Route::patch('/{group}', [SystemSettingController::class, 'update'])->name('admin.settings.update');
    });
