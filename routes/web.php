<?php // v0.4.119
/* routes/web.php
Purpose: Декларативные маршруты приложения Faravel: форум (категории/хабы/темы/посты),
         а также auth, админка и мод-панель. Тонкие контроллеры Pages/*.
FIX: Добавлены back-compat алиасы для /forum/h/{tag_slug}/ (show/create GET/POST),
     указывающие на те же экшены, что и канонический /forum/f/{tag_slug}/.
     Это устраняет 404 для уже отрендерённых ссылок. Главная (/) возвращает 200 OK.
*/

use Faravel\Routing\Router;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\VerifyCsrfToken as Csrf;
use App\Http\Controllers\AuthController;

// Admin/mod controllers and middleware
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminForumController;
use App\Http\Controllers\ModController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\ModOnly;

// Pages (Topics)
use App\Http\Controllers\Forum\Pages\ShowTopicAction;
use App\Http\Controllers\Forum\Pages\ReplyFormAction;
use App\Http\Controllers\Forum\Pages\ReplyToTopicAction;
use App\Http\Controllers\Forum\Pages\UpdateTopicTagsAction;
use App\Http\Controllers\Forum\Pages\CloseTopicAction;
use App\Http\Controllers\Forum\Pages\OpenTopicAction;
use App\Http\Controllers\Forum\Pages\PinTopicAction;
use App\Http\Controllers\Forum\Pages\UnpinTopicAction;
use App\Http\Controllers\Forum\Pages\MoveTopicAction;
use App\Http\Controllers\Forum\Pages\MergeTopicAction;
use App\Http\Controllers\Forum\Pages\SplitTopicAction;

// Pages (Posts)
use App\Http\Controllers\Forum\Pages\PostGotoAction;
use App\Http\Controllers\Forum\Pages\EditPostFormAction;
use App\Http\Controllers\Forum\Pages\UpdatePostAction;
use App\Http\Controllers\Forum\Pages\DeletePostAction;
use App\Http\Controllers\Forum\Pages\RestorePostAction;
use App\Http\Controllers\Forum\Pages\AddReactionAction;
use App\Http\Controllers\Forum\Pages\RemoveReactionAction;

// Pages (Hubs/Tags)
use App\Http\Controllers\Forum\Pages\ShowHubAction;
use App\Http\Controllers\Forum\Pages\CreateTopicFormAction;
use App\Http\Controllers\Forum\Pages\CreateTopicAction;

// Pages (Category)
use App\Http\Controllers\Forum\Pages\ShowCategoryAction;

// Pages (Forum root)
use App\Http\Controllers\Forum\Pages\ForumIndexAction;

/* ===================== /forum/ (root) ===================== */

Router::get('/forum/', [ForumIndexAction::class, '__invoke'])
    ->name('forum.index');

/* ===================== C/ категории ===================== */

Router::get('/forum/c/{category_slug}/', [ShowCategoryAction::class, '__invoke'])
    ->name('forum.category.show');

/* ===================== T/ темы ===================== */

Router::get('/forum/t/{topic_slug}/', [ShowTopicAction::class, '__invoke'])
    ->name('forum.topic.show');

Router::get('/forum/t/{topic_slug}/reply', [ReplyFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])
    ->name('forum.topic.reply.form');

Router::post('/forum/t/{topic}/reply', [ReplyToTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.reply');

/* Topic moderation */
Router::post('/forum/t/{topic}/close', [CloseTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.close');

Router::post('/forum/t/{topic}/open', [OpenTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.open');

Router::post('/forum/t/{topic}/pin', [PinTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.pin');

Router::post('/forum/t/{topic}/unpin', [UnpinTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.unpin');

Router::post('/forum/t/{topic}/move', [MoveTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.move');

Router::post('/forum/t/{topic}/merge', [MergeTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.merge');

Router::post('/forum/t/{topic}/split', [SplitTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.split');

/* Topic tags */
Router::post('/forum/t/{topic}/tags', [UpdateTopicTagsAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.tags.update');

/* ===================== P/ посты ===================== */

Router::get('/forum/p/{post_id}/', [PostGotoAction::class, '__invoke'])
    ->name('forum.post.goto');

Router::get('/forum/p/{post_id}/edit', [EditPostFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])
    ->name('forum.post.edit.form');

Router::post('/forum/p/{post_id}/edit', [UpdatePostAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.edit');

Router::post('/forum/p/{post_id}/delete', [DeletePostAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.delete');

Router::post('/forum/p/{post_id}/restore', [RestorePostAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.restore');

Router::post('/forum/p/{post_id}/react', [AddReactionAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.react.add');

Router::post('/forum/p/{post_id}/react/remove', [RemoveReactionAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.react.remove');

/* ===================== F/ хабы (taggable hubs) ===================== */

Router::get('/forum/f/{tag_slug}/', [ShowHubAction::class, '__invoke'])
    ->name('forum.hub.show');

Router::get('/forum/f/{tag_slug}/create', [CreateTopicFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])
    ->name('forum.hub.create.form');

Router::post('/forum/f/{tag_slug}/create', [CreateTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.hub.create');

/* ---- Back-compat aliases for legacy /forum/h/{tag_slug}/ ----
 * These routes map to the same actions as /forum/f/{tag_slug}/ to keep
 * existing rendered links working while we migrate VMs/templates.
 */
Router::get('/forum/h/{tag_slug}/', [ShowHubAction::class, '__invoke'])
    ->name('forum.hub.show.h');

Router::get('/forum/h/{tag_slug}/create', [CreateTopicFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])
    ->name('forum.hub.create.form.h');

Router::post('/forum/h/{tag_slug}/create', [CreateTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.hub.create.h');

/* ===================== Auth ===================== */

Router::get('/login', [AuthController::class, 'showLoginForm'])
    ->name('login');

Router::post('/login', [AuthController::class, 'login'])
    ->middleware([Csrf::class])
    ->name('login.post');

Router::get('/register', [AuthController::class, 'showRegisterForm'])
    ->name('register');

Router::post('/register', [AuthController::class, 'register'])
    ->middleware([Csrf::class])
    ->name('register.post');

Router::post('/logout', [AuthController::class, 'logout'])
    ->middleware([Csrf::class])
    ->name('logout');

/* ===================== Admin Panel =====================
 * Admin routes are protected by AuthMiddleware + AdminOnly.
 * Admins (role_id ≥ 6) can manage settings, categories and forums.
 */

Router::get('/admin', [AdminController::class, 'index'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.index');

Router::get('/admin/settings', [AdminController::class, 'settings'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.settings');

Router::post('/admin/settings', [AdminController::class, 'settingsSave'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.settings.save');

/* Categories management */
Router::get('/admin/categories', [AdminCategoryController::class, 'index'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.categories');

Router::post('/admin/categories', [AdminCategoryController::class, 'store'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.categories.store');

Router::get('/admin/categories/{id}/edit', [AdminCategoryController::class, 'edit'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.categories.edit');

Router::post('/admin/categories/{id}', [AdminCategoryController::class, 'update'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.categories.update');

Router::post('/admin/categories/{id}/delete', [AdminCategoryController::class, 'destroy'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.categories.delete');

/* Forums management */
Router::get('/admin/forums', [AdminForumController::class, 'index'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.forums');

Router::get('/admin/forums/new', [AdminForumController::class, 'create'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.forums.create');

Router::post('/admin/forums/new', [AdminForumController::class, 'store'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.forums.store');

Router::get('/admin/forums/{id}/edit', [AdminForumController::class, 'edit'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.forums.edit');

Router::post('/admin/forums/{id}', [AdminForumController::class, 'update'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.forums.update');

Router::post('/admin/forums/{id}/delete', [AdminForumController::class, 'destroy'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.forums.delete');

/* ===================== Mod Panel ===================== */

Router::get('/mod', [ModController::class, 'index'])
    ->middleware([AuthMiddleware::class, ModOnly::class])
    ->name('mod.index');

/* ===================== Home =====================
 * Root returns 200 OK and renders 'home' view (no hard redirect).
 * This keeps curl checks happy and avoids premature pipeline exit.
 */
Router::get('/', function ($request) {
    // NOTE: Since v0.4.114, Router::executeAction unpacks args correctly.
    // Home route has no params; accept Request only for consistency.
    return response()->view('home');
})->name('home');
