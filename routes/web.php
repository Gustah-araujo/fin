<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware("guest")->group(function () {
    Route::get("register", [RegisteredUserController::class, "create"])->name("register");
    Route::post("register", [RegisteredUserController::class, "store"]);
    Route::get("login", [AuthenticatedSessionController::class, "create"])->name("login");
    Route::post("login", [AuthenticatedSessionController::class, "store"]);
    Route::get("forgot-password", [PasswordResetLinkController::class, "create"])->name("password.request");
    Route::post("forgot-password", [PasswordResetLinkController::class, "store"])->name("password.email");
    Route::get("reset-password/{token}", [NewPasswordController::class, "create"])->name("password.reset");
    Route::post("reset-password", [NewPasswordController::class, "store"])->name("password.store");
});

// Google OAuth
Route::get("auth/google", [SocialiteController::class, "redirect"])->name("google.redirect");
Route::get("auth/google/callback", [SocialiteController::class, "callback"])->name("google.callback");

// Authenticated routes
Route::middleware("auth")->group(function () {
    Route::post("logout", [AuthenticatedSessionController::class, "destroy"])->name("logout");

    Route::get("verify-email", EmailVerificationPromptController::class)->name("verification.notice");
    Route::get("verify-email/{id}/{hash}", VerifyEmailController::class)
        ->middleware("signed")->name("verification.verify");
    Route::post("email/verification-notification", [EmailVerificationNotificationController::class, "store"])
        ->middleware("throttle:6,1")->name("verification.send");

    Route::get("settings/password", [PasswordController::class, "edit"])->name("password.edit");
    Route::put("settings/password", [PasswordController::class, "update"])->name("password.update");

    Route::post("invites/{invite}/accept", [InviteController::class, "accept"])->name("invites.accept");
    Route::post("invites/{invite}/decline", [InviteController::class, "decline"])->name("invites.decline");
});

// Auth + Verified routes
Route::middleware(["auth", "verified"])->group(function () {
    Route::get("workspace/create", [WorkspaceController::class, "create"])->name("workspace.create");
    Route::post("workspace", [WorkspaceController::class, "store"])->name("workspace.store");
    Route::get("workspace/select", [WorkspaceController::class, "select"])->name("workspace.select");
    Route::post("workspace/activate", [WorkspaceController::class, "activate"])->name("workspace.activate");
});

// Routes requiring a workspace
Route::middleware(["auth", "verified", "ensure.has.workspace"])->group(function () {
    Route::get("/", fn () => redirect()->route("workspace.select"));

    Route::prefix("w/{workspace}")->group(function () {
        Route::get("/", function () {
            return inertia("Home");
        })->name("dashboard");

        Route::get("members", [WorkspaceMemberController::class, "index"])->name("workspace.members.index");
        Route::delete("members/{user}", [WorkspaceMemberController::class, "destroy"])->name("workspace.members.destroy");
        Route::put("members/{user}/role", [WorkspaceMemberController::class, "updateRole"])->name("workspace.members.role");

        Route::post("invites", [InviteController::class, "store"])->name("workspace.invites.store");

        Route::resource("accounts", AccountController::class);
        Route::resource("categories", CategoryController::class);
        Route::resource("tags", TagController::class);
        Route::resource("transactions", TransactionController::class);
        Route::post("transactions/{transaction}/pay", [TransactionController::class, "pay"])
            ->name("transactions.pay");
        Route::post("transactions/{transaction}/unpay", [TransactionController::class, "unpay"])
            ->name("transactions.unpay");
        Route::resource("cards", CreditCardController::class);
    });
});
