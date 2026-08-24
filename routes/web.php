<?php

/*
 * Okatana - A Newfoundcodes project.
 *
 * Copyright (C) 2026 Jonathan Eldy Baldivicio
 *
 * Author: Jonathan Eldy Baldivicio
 * Contact: jonathaneldy.baldivicio@newfoundcodes.com
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiCredentialController;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\DocumentCommentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EditorAssetController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\EnsureApiDocumentationAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureApiDocumentationAccess::class)->group(function (): void {
  Route::get(config('okatana.api_docs.spec_path', '/docs/openapi.yaml'), [
    ApiDocumentationController::class,
    'specification',
  ])->name('openapi.spec');
});

Route::view('/', 'app');
Route::view('/app/{any?}', 'app')->where('any', '.*');
Route::view('/join/{token}', 'app');

Route::prefix('app-api')->group(function (): void {
  Route::get('/auth/config', [AuthController::class, 'config']);
  Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
  Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
  Route::get('/auth/two-factor/pending', [AuthController::class, 'pendingTwoFactorChallenge']);

  Route::post('/auth/two-factor/challenge', [
    AuthController::class,
    'twoFactorChallenge',
  ])->middleware('throttle:security');

  Route::get('/invitations/{token}', [InvitationController::class, 'show']);
  Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->middleware(
    'throttle:auth',
  );

  Route::middleware('auth')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/email/verify-code', [AuthController::class, 'verifyEmailCode'])->middleware(
      'throttle:security',
    );

    Route::post('/auth/email/resend-code', [AuthController::class, 'resendEmailCode'])->middleware(
      'throttle:security',
    );

    Route::get('/auth/two-factor/setup', [AuthController::class, 'twoFactorSetup'])->middleware(
      'throttle:security',
    );

    Route::post('/auth/two-factor/confirm', [
      AuthController::class,
      'confirmTwoFactor',
    ])->middleware('throttle:security');
    Route::middleware('account.secure')->group(function (): void {
      Route::get('/user', [UserController::class, 'show']);
      Route::patch('/user', [UserController::class, 'update']);
      Route::post('/user/avatar', [UserController::class, 'updateAvatar']);
      Route::delete('/user/avatar', [UserController::class, 'destroyAvatar']);
      Route::get('/users/{user}/avatar', [UserController::class, 'avatar']);
      Route::get('/user/export', [UserController::class, 'export']);
      Route::get('/notifications', [NotificationController::class, 'index']);
      Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

      Route::post('/notifications/{notification}/read', [
        NotificationController::class,
        'markRead',
      ]);

      Route::post('/editor/images', [EditorAssetController::class, 'storeImage']);
      Route::get('/editor/images/{owner}/{filename}', [EditorAssetController::class, 'showImage']);
      Route::get('/organizations', [OrganizationController::class, 'index']);
      Route::post('/organizations', [OrganizationController::class, 'store']);
      Route::post('/imports/organization', [TransferController::class, 'importOrganization']);
      Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
      Route::patch('/organizations/{organization}', [OrganizationController::class, 'update']);
      Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);

      Route::get('/organizations/{organization}/members', [
        OrganizationMemberController::class,
        'index',
      ]);

      Route::post('/organizations/{organization}/invitations', [
        OrganizationMemberController::class,
        'invite',
      ]);

      Route::patch('/organizations/{organization}/members/{user}', [
        OrganizationMemberController::class,
        'update',
      ]);

      Route::delete('/organizations/{organization}/members/{user}', [
        OrganizationMemberController::class,
        'destroy',
      ]);

      Route::delete('/invitations/{invitation}', [InvitationController::class, 'revoke']);
      Route::get('/documents', [DocumentController::class, 'index']);
      Route::post('/documents', [DocumentController::class, 'store']);
      Route::get('/documents/editor-options', [DocumentController::class, 'editorOptions']);
      Route::get('/documents/editor-candidates', [DocumentController::class, 'editorCandidates']);
      Route::get('/documents/mention-candidates', [DocumentController::class, 'mentionCandidates']);

      Route::get('/organizations/{organization}/document-tags', [
        DocumentController::class,
        'tags',
      ]);

      Route::get('/documents/audit-logs', [DocumentController::class, 'audit']);
      Route::get('/documents/{document}', [DocumentController::class, 'show']);
      Route::patch('/documents/{document}', [DocumentController::class, 'update']);
      Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
      Route::post('/documents/{document}/archive', [DocumentController::class, 'archive']);
      Route::post('/documents/{document}/favorite', [DocumentController::class, 'favorite']);
      Route::get('/documents/{document}/pdf', [DocumentController::class, 'pdf']);
      Route::post('/documents/{document}/comments', [DocumentCommentController::class, 'store']);
      Route::patch('/document-comments/{comment}', [DocumentCommentController::class, 'update']);
      Route::delete('/document-comments/{comment}', [DocumentCommentController::class, 'destroy']);
      Route::get('/projects', [ProjectController::class, 'workspaceIndex']);
      Route::get('/organizations/{organization}/projects', [ProjectController::class, 'index']);
      Route::post('/organizations/{organization}/projects', [ProjectController::class, 'store']);

      Route::post('/organizations/{organization}/imports/project', [
        TransferController::class,
        'importProject',
      ]);

      Route::post('/organizations/{organization}/imports/timeline', [
        TransferController::class,
        'importTimeline',
      ]);

      Route::get('/projects/{project}', [ProjectController::class, 'show']);
      Route::patch('/projects/{project}', [ProjectController::class, 'update']);
      Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
      Route::put('/projects/{project}/members', [ProjectMemberController::class, 'sync']);
      Route::post('/projects/{project}/boards', [BoardController::class, 'store']);
      Route::put('/projects/{project}/boards/reorder', [BoardController::class, 'reorder']);
      Route::patch('/boards/{board}', [BoardController::class, 'update']);
      Route::delete('/boards/{board}', [BoardController::class, 'destroy']);
      Route::get('/projects/{project}/tickets', [TicketController::class, 'index']);
      Route::post('/projects/{project}/tickets', [TicketController::class, 'store']);
      Route::put('/projects/{project}/tickets/reorder', [TicketController::class, 'reorder']);

      Route::get('/projects/{project}/tickets/by-number/{number}', [
        TicketController::class,
        'showByNumber',
      ])->whereNumber('number');

      Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
      Route::get('/tickets/{ticket}/revisions', [RevisionController::class, 'ticket']);
      Route::patch('/tickets/{ticket}', [TicketController::class, 'update']);
      Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);
      Route::post('/tickets/{ticket}/move', [TicketController::class, 'move']);
      Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
      Route::get('/comments/{comment}/revisions', [RevisionController::class, 'comment']);
      Route::patch('/comments/{comment}', [TicketCommentController::class, 'update']);
      Route::delete('/comments/{comment}', [TicketCommentController::class, 'destroy']);
      Route::post('/tickets/{ticket}/attachments', [AttachmentController::class, 'storeForTicket']);

      Route::post('/comments/{comment}/attachments', [
        AttachmentController::class,
        'storeForComment',
      ]);

      Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
      Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);
      Route::post('/projects/{project}/labels', [LabelController::class, 'store']);
      Route::patch('/labels/{label}', [LabelController::class, 'update']);
      Route::delete('/labels/{label}', [LabelController::class, 'destroy']);
      Route::get('/projects/{project}/analytics', [AnalyticsController::class, 'project']);

      Route::get('/organizations/{organization}/audit-logs', [
        AuditLogController::class,
        'organization',
      ]);

      Route::get('/projects/{project}/audit-logs', [AuditLogController::class, 'project']);
      Route::get('/organizations/{organization}/webhooks', [WebhookController::class, 'index']);
      Route::post('/organizations/{organization}/webhooks', [WebhookController::class, 'store']);
      Route::patch('/webhooks/{webhook}', [WebhookController::class, 'update']);
      Route::post('/webhooks/{webhook}/rotate', [WebhookController::class, 'rotate']);
      Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy']);
      Route::get('/webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries']);

      Route::get('/organizations/{organization}/api-credentials', [
        ApiCredentialController::class,
        'index',
      ]);

      Route::post('/organizations/{organization}/api-credentials', [
        ApiCredentialController::class,
        'store',
      ]);

      Route::delete('/api-credentials/{credential}', [ApiCredentialController::class, 'destroy']);

      Route::get('/organizations/{organization}/export', [
        TransferController::class,
        'exportOrganization',
      ]);

      Route::get('/projects/{project}/export', [TransferController::class, 'exportProject']);

      Route::get('/projects/{project}/timeline/export', [
        TransferController::class,
        'exportProjectTimeline',
      ]);

      Route::get('/organizations/{organization}/timeline/export', [
        TransferController::class,
        'exportTimeline',
      ]);
    });
  });
});

Route::get('/auth/oauth/{provider}', [OAuthController::class, 'redirect']);
Route::get('/auth/oauth/{provider}/callback', [OAuthController::class, 'callback']);
Route::get('/auth/sso/{driver}', [SsoController::class, 'redirect']);
Route::match(['get', 'post'], '/auth/sso/{driver}/callback', [SsoController::class, 'callback']);
