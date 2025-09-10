<?php // v0.4.8
/* app/Http/Composers/ForumBasePageComposer.php
Purpose: Legacy-обёртка над LayoutComposer для сохранения совместимости с
         существующей регистрацией композера под старым именем. Никакой собственной
         логики не содержит — делегирует вызов compose() во внутренний LayoutComposer.
FIX: Убран forbidden-extends от final-класса. Реализован безопасный делегат:
     композиция вместо наследования.
*/

namespace App\Http\Composers;

use Faravel\View\View;

/**
 * @deprecated Используйте App\Http\Composers\LayoutComposer. Этот класс оставлен
 *             как делегирующая совместимая обёртка на период миграции.
 */
final class ForumBasePageComposer
{
    /** @var LayoutComposer Внутренний «настоящий» композер лэйаута. */
    private LayoutComposer $inner;

    public function __construct()
    {
        // Note: dependency-free; при наличии контейнера можно инжектить.
        $this->inner = new LayoutComposer();
    }

    /**
     * Делегирует инъекцию layout во View «настоящему» композеру.
     *
     * Зачем: существующие регистрации на ForumBasePageComposer продолжают работать,
     * пока код мигрирует на LayoutComposer.
     *
     * @param View $view Экземпляр представления, куда нужно подмешать $layout.
     *
     * Preconditions:
     * - LayoutComposer корректно настроен и доступен.
     *
     * Side effects:
     * - Внутри LayoutComposer: чтение request(), вызов LayoutService::build().
     *
     * @return void
     * @example
     *  // В конфиге зарегистрирован ForumBasePageComposer:
     *  // он попадёт сюда, а логика выполнится во внутреннем LayoutComposer.
     */
    public function compose(View $view): void
    {
        $this->inner->compose($view);
    }
}
