## stack overview
## v0.4.121

HTTP (nginx/php-fpm)
  → public/index.php
    → framework/init.php + helpers.php
    → app/init.php
    → Providers:
        - Database/Cache/Event/Session/Auth/Gate/Security/Ability/Lang/View
        - ForumViewServiceProvider
        - AppServiceProvider
        - RoutingServiceProvider
        - HttpMiddlewareServiceProvider
        - AuthContainerServiceProvider
        - ViewModelComplianceServiceProvider
    → Router (routes/web.php)
       ├─ GET /                 (home closure)
       ├─ GET /forum/           → App\Http\Controllers\Forum\Pages\ForumIndexAction::__invoke
       ├─ GET /forum/c/{slug}/  → ...\ShowCategoryAction
       ├─ GET /forum/t/{slug}/  → ...\ShowTopicAction
       ├─ ... (reply/edit/delete/restore/tags/moderation) + CSRF/Auth middleware
       ├─ GET/POST /login,/register,/logout
       ├─ /admin (MVC, Auth + AdminOnly)
       └─ /mod   (MVC, Auth + ModOnly)

Middleware цепочка:
  SessionMiddleware → AuthContext → SetLocale → ThrottleRequests → VerifyCsrfToken (на POST)

Контроллеры (тонкие, слой Pages):
  App\Http\Controllers\Forum\Pages\*
    ├─ ForumIndexAction       (главная форума)
    ├─ ShowCategoryAction     (категория)
    ├─ ShowHubAction          (хаб/тег)
    ├─ ShowTopicAction + Reply*/Update*/Moderation* ...
    └─ CreateTopic* ...

Сервисы (домен/инфраструктура):
  App\Services\Forum\* (CategoryQueryService, ForumHomeService, ...)
  App\Services\Layout\LayoutService (сборка лэйаута)
  App\Services\Admin\* (ContractChecker, ChecksumService)
  App\Services\Auth\*  (AbilityService, VisibilityPolicy, ...)

View Composers:
  App\Http\Composers\LayoutComposer → дергает LayoutService::build(...)
  и прокидывает готовый $layout в каждый рендер

ViewModels (страничные и лэйаут):
  App\Http\ViewModels\Forum\ForumIndexPageVM (index)
  App\Http\ViewModels\Layout\LayoutVM
  (+ прочие VM под страницы форума)

Views (строгий Blade):
  resources/views/layouts/xen/theme.blade.php
  resources/views/layouts/xen/nav.blade.php
  resources/views/forum/index.blade.php
  resources/views/forum/{category,hub,topic,create_topic}.blade.php

BladeEngine (строгий):
  - запрещены inline PHP и @php/@endphp
  - {{ ... }} — только переменные/доступ по ключам
  - макеты/инклюды через фабрику, кэш компиляции
