<?php // v0.4.4
// routes/web.php
// Назначение: декларативные маршруты форума (тонкие контроллеры Pages/*).
// FIX: /forum/f/{tag_slug} теперь обслуживает постоянный ShowHubAction;
//      удалена ссылка на временный ShowHubBySlugAction; остальное без изменений.

use Faravel\Routing\Router;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\VerifyCsrfToken as Csrf;
use App\Http\Controllers\AuthController;
use Faravel\Http\Response;

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

Router::get('/forum/', [ForumIndexAction::class, '__invoke'])->name('forum.index');

/* ===================== C/ категории ===================== */

Router::get('/forum/c/{category_slug}/', [ShowCategoryAction::class, '__invoke'])
    ->name('forum.category.show');

/* ===================== T/ темы ===================== */

Router::get('/forum/t/{topic_slug}/', [ShowTopicAction::class, '__invoke'])
    ->name('forum.topic.show');

Router::get('/forum/t/{topic_slug}/reply', [ReplyFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])->name('forum.topic.reply.form');

Router::post('/forum/t/{topic}/reply', [ReplyToTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.reply');

/* Модерация тем */
Router::post('/forum/t/{topic}/close', [CloseTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.close');

Router::post('/forum/t/{topic}/open', [OpenTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.open');

Router::post('/forum/t/{topic}/pin', [PinTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.pin');

Router::post('/forum/t/{topic}/unpin', [UnpinTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.unpin');

Router::post('/forum/t/{topic}/move', [MoveTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.move');

Router::post('/forum/t/{topic}/merge', [MergeTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.merge');

Router::post('/forum/t/{topic}/split', [SplitTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.split');

/* Теги темы */
Router::post('/forum/t/{topic}/tags', [UpdateTopicTagsAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.topic.tags.update');

/* ===================== P/ посты ===================== */

Router::get('/forum/p/{post_id}/', [PostGotoAction::class, '__invoke'])
    ->name('forum.post.goto');

Router::get('/forum/p/{post_id}/edit', [EditPostFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])->name('forum.post.edit.form');

Router::post('/forum/p/{post_id}/edit', [UpdatePostAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.post.edit');

Router::post('/forum/p/{post_id}/delete', [DeletePostAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.post.delete');

Router::post('/forum/p/{post_id}/restore', [RestorePostAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.post.restore');

Router::post('/forum/p/{post_id}/react', [AddReactionAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.post.react.add');

Router::post('/forum/p/{post_id}/react/remove', [RemoveReactionAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.post.react.remove');

/* ===================== F/ хабы (taggable hubs) ===================== */

Router::get('/forum/f/{tag_slug}/', [ShowHubAction::class, '__invoke'])
    ->name('forum.hub.show');

Router::get('/forum/f/{tag_slug}/create', [CreateTopicFormAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class])->name('forum.hub.create.form');

Router::post('/forum/f/{tag_slug}/create', [CreateTopicAction::class, '__invoke'])
    ->middleware([AuthMiddleware::class, Csrf::class])->name('forum.hub.create');

// ========== Auth ==========
Router::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Router::post('/login', [AuthController::class, 'login'])
    ->middleware([Csrf::class])->name('login.post');

Router::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Router::post('/register', [AuthController::class, 'register'])
    ->middleware([Csrf::class])->name('register.post');

Router::post('/logout', [AuthController::class, 'logout'])
    ->middleware([Csrf::class])->name('logout');

Router::get('/', function () {
    return (new Response())->redirect('/forum/');
})->name('home');
