<?php // v0.4.2
/* framework/Faravel/View/Engines/PhpEngine.php
Purpose: простой PHP-движок для рендера «чистых» .php шаблонов, служит
базовой реализацией движка помимо Blade.
FIX: реализован EngineInterface; расширены PHPDoc для ясности типов.
*/
namespace Faravel\View\Engines;

use RuntimeException;

/**
 * Minimal PHP engine that includes the template in isolated scope.
 */
final class PhpEngine implements EngineInterface
{
    /**
     * @inheritDoc
     */
    public function get(string $path, array $data): string
    {
        if (!is_file($path)) {
            throw new RuntimeException("PHP template not found: {$path}");
        }

        $render = static function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                /** @psalm-suppress UnresolvableInclude */
                include $__path;
                return (string)ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        return $render($path, $data);
    }
}
