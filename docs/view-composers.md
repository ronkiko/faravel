<!-- docs/view-composers.md -->
# View Composers

**Единый композер:** `App\Http\Composers\LayoutComposer`  
Роль: инъецирует в каждый таргетный Blade готовый `$layout`, вызывая
`LayoutService::build($request, $overrides)` и соблюдая VM-контракт (`site.*`, `nav.active`,
`title`). Blade остаётся «немым».

## Регистрация

```php
use App\Http\Composers\LayoutComposer;
use Faravel\Support\Facades\View;

View::composer(['forum.*', 'layouts.*'], LayoutComposer::class);
