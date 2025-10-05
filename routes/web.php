<?php // v0.4.122
/* routes/web.php
Purpose: Декларативные маршруты приложения Faravel: форум (категории/хабы/темы/посты),
         а также auth, админка и мод-панель. Тонкие контроллеры Pages/*.
FIX: Маршруты /forum/(f|h)/{tag_slug}/create указывают на ['__invoke'] вместо bare class,
     чтобы удовлетворить Router и убрать "Invalid route action type".
*/

use Faravel\Routing\Router;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\VerifyCsrfToken as Csrf;
use App\Http\Controllers\AuthController;

// Admin/mod controllers and middleware
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminForumController;
use App\Http\Controllers\AdminAbilityController;
use App\Http\Controllers\AdminPerkController;
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

use App\Http\Controllers\Forum\Pages\PostGotoAction;
use App\Http\Controllers\Forum\Pages\EditPostFormAction;
use App\Http\Controllers\Forum\Pages\UpdatePostAction;
use App\Http\Controllers\Forum\Pages\DeletePostAction;
use App\Http\Controllers\Forum\Pages\RestorePostAction;
use App\Http\Controllers\Forum\Pages\AddReactionAction;
use App\Http\Controllers\Forum\Pages\RemoveReactionAction;

use App\Http\Controllers\Forum\Pages\ShowHubAction;
use App\Http\Controllers\Forum\Pages\CreateTopicFormAction;
use App\Http\Controllers\Forum\Pages\CreateTopicAction;

use App\Http\Controllers\Forum\Pages\ShowCategoryAction;

use App\Http\Controllers\Forum\Pages\ForumIndexAction;

/* ===================== Auth ===================== */

Router::get('/login', [AuthController::class, 'loginForm'])->name('login');
Router::post('/login', [AuthController::class, 'login'])->middleware([Csrf::class]);
Router::post('/logout', [AuthController::class, 'logout'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('logout');

Router::get('/register', [AuthController::class, 'showRegisterForm']);
Router::post('/register',[AuthController::class, 'register']);

/* ===================== Forum: Index ===================== */

Router::get('/forum', [ForumIndexAction::class, 'show'])->name('forum.index');
Router::get('/forum/', [ForumIndexAction::class, 'show']);

/* ===================== Forum: Categories ===================== */

Router::get('/forum/c/{slug}', [ShowCategoryAction::class, 'show'])
    ->name('forum.category.show');

/* ===================== Forum: Hubs (aliases & canonical) ===================== */

Router::get('/forum/f/{tag_slug}', [ShowHubAction::class, 'show'])
    ->name('forum.hub.show');

Router::get('/forum/h/{tag_slug}', [ShowHubAction::class, 'show']);
Router::get('/forum/h/{tag_slug}/', [ShowHubAction::class, 'show']);

/* ===================== Forum: Topic creation ===================== */

Router::get('/forum/f/{tag_slug}/create', [CreateTopicFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])
    ->name('forum.topic.create.form');

Router::post('/forum/f/{tag_slug}/create', [CreateTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.create');

Router::get('/forum/h/{tag_slug}/create', [CreateTopicFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class]);

Router::post('/forum/h/{tag_slug}/create', [CreateTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class]);

/* ===================== Forum: Topics ===================== */

Router::get('/forum/t/{id}', [ShowTopicAction::class, 'show'])
    ->name('forum.topic.show');
Router::get('/forum/t/{id}/reply', [ReplyFormAction::class, 'show'])
    ->middleware([AuthMiddleware::class])
    ->name('forum.topic.reply.form');
Router::post('/forum/t/{id}/reply', [ReplyToTopicAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.reply');

Router::post('/forum/t/{id}/tags', [UpdateTopicTagsAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.tags');

Router::post('/forum/t/{id}/close', [CloseTopicAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.close');
Router::post('/forum/t/{id}/open', [OpenTopicAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.open');
Router::post('/forum/t/{id}/pin', [PinTopicAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.pin');
Router::post('/forum/t/{id}/unpin', [UnpinTopicAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.topic.unpin');

Router::get('/forum/p/{id}', [PostGotoAction::class, 'goto'])
    ->name('forum.post.goto');
Router::get('/forum/p/{id}/edit', [EditPostFormAction::class, 'show'])
    ->middleware([AuthMiddleware::class])
    ->name('forum.post.edit.form');
Router::post('/forum/p/{id}/edit', [UpdatePostAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.edit');
Router::post('/forum/p/{id}/delete', [DeletePostAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.delete');
Router::post('/forum/p/{id}/restore', [RestorePostAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.restore');
Router::post('/forum/p/{id}/react', [AddReactionAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.react');
Router::post('/forum/p/{id}/unreact', [RemoveReactionAction::class, 'handle'])
    ->middleware([AuthMiddleware::class, Csrf::class])
    ->name('forum.post.unreact');

/* ===================== Admin Panel ===================== */

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

/* Abilities management */
Router::get('/admin/abilities', [AdminAbilityController::class, 'index'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.abilities');

Router::get('/admin/abilities/create', [AdminAbilityController::class, 'create'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.abilities.create');

Router::post('/admin/abilities', [AdminAbilityController::class, 'store'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.abilities.store');

Router::get('/admin/abilities/{id}/edit', [AdminAbilityController::class, 'edit'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.abilities.edit');

Router::post('/admin/abilities/{id}', [AdminAbilityController::class, 'update'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.abilities.update');

Router::post('/admin/abilities/{id}/delete', [AdminAbilityController::class, 'delete'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.abilities.delete');

/* Perks management */
Router::get('/admin/perks', [AdminPerkController::class, 'index'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.perks');

Router::get('/admin/perks/create', [AdminPerkController::class, 'create'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.perks.create');

Router::post('/admin/perks', [AdminPerkController::class, 'store'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.perks.store');

Router::get('/admin/perks/{id}/edit', [AdminPerkController::class, 'edit'])
    ->middleware([AuthMiddleware::class, AdminOnly::class])
    ->name('admin.perks.edit');

Router::post('/admin/perks/{id}', [AdminPerkController::class, 'update'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.perks.update');

Router::post('/admin/perks/{id}/delete', [AdminPerkController::class, 'delete'])
    ->middleware([AuthMiddleware::class, AdminOnly::class, Csrf::class])
    ->name('admin.perks.delete');

/* ===================== Mod Panel ===================== */

Router::get('/mod', [ModController::class, 'index'])
    ->middleware([AuthMiddleware::class, ModOnly::class])
    ->name('mod.index');

/* ===================== Home ===================== */

Router::get('/', function ($request) {
    return response()->view('home');
})->name('home');
