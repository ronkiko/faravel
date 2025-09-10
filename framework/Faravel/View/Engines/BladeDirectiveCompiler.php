<?php // v0.4.5
namespace Faravel\View\Engines;

final class BladeDirectiveCompiler
{
    private const RESERVED = [
        'if','elseif','else','endif','foreach','endforeach','for','endfor','while','endwhile',
        'extends','section','endsection','yield','show','append','overwrite','push','endpush','stack',
        'include','each',
    ];

    public static function compile(string $code, array $options = []): string
    {
        $disallowRaw = (bool)($options['disallowRawEcho'] ?? false);
        $onlyVars    = (bool)($options['echoesOnlyVars']  ?? false);
        $directives  = (array)($options['directives']     ?? []);

        $code = preg_replace('~\{\{\-\-.*?\-\-\}\}~s', '', $code) ?? $code;
        $code = preg_replace('~<!--.*?-->~s',          '', $code) ?? $code;

        if (!empty($directives)) {
            $code = self::compileCustomDirectives($code, $directives);
        }

        if ($disallowRaw && preg_match('~\{!!~', $code) === 1) {
            throw new \RuntimeException('Raw echo {!! !!} is disabled in strict mode.');
        }

        if (!$disallowRaw) {
            $code = self::compileRawEchos($code);
        } else {
            $code = preg_replace('/\{!!\s*.*?\s*!!\}/s', '<?php /* raw echo disabled */ ?>', $code) ?? $code;
        }

        $code = self::compileEscapedEchos($code, $onlyVars);

        $PAREN = '\((?>[^()]+|(?R))*\)';

        $code = preg_replace('/@if\s*(' . $PAREN . ')/',      '<?php if $1 { ?>', $code) ?? $code;
        $code = preg_replace('/@elseif\s*(' . $PAREN . ')/',  '<?php } elseif $1 { ?>', $code) ?? $code;
        $code = preg_replace('/@else\b/',                     '<?php } else { ?>', $code) ?? $code;
        $code = preg_replace('/@endif\b/',                    '<?php } ?>', $code) ?? $code;

        $code = preg_replace('/@foreach\s*(' . $PAREN . ')/', '<?php foreach $1 { ?>', $code) ?? $code;
        $code = preg_replace('/@endforeach\b/',               '<?php } ?>', $code) ?? $code;

        $code = preg_replace('/@for\s*(' . $PAREN . ')/',     '<?php for $1 { ?>', $code) ?? $code;
        $code = preg_replace('/@endfor\b/',                   '<?php } ?>', $code) ?? $code;

        $code = preg_replace('/@while\s*(' . $PAREN . ')/',   '<?php while $1 { ?>', $code) ?? $code;
        $code = preg_replace('/@endwhile\b/',                 '<?php } ?>', $code) ?? $code;

        $code = preg_replace('/@php\s*(.*?)\s*@endphp/s', '<?php /* @php forbidden */ ?>', $code) ?? $code;

        return $code;
    }

    private static function compileCustomDirectives(string $code, array $directives): string
    {
        return preg_replace_callback(
            '/@([A-Za-z_][A-Za-z0-9_]*)\s*(\((?>[^()]+|(?2))*\))?/',
            static function (array $m) use ($directives): string {
                $name = strtolower($m[1]);
                if (in_array($name, self::RESERVED, true)) {
                    return $m[0];
                }
                if (!array_key_exists($name, $directives)) {
                    return $m[0];
                }
                $expr = $m[2] ?? '';
                $cb   = $directives[$name];
                $replacement = (string)$cb($expr);
                if (str_contains($replacement, '<?php') || str_contains($replacement, '<?=') || str_contains($replacement, '<?=')) {
                    throw new \RuntimeException(
                        "Directive @$name must not return PHP code in strict mode. Return Blade/HTML/{{ ... }} only."
                    );
                }
                return $replacement;
            },
            $code
        ) ?? $code;
    }

    private static function compileRawEchos(string $code): string
    {
        return preg_replace_callback('~\{!!\s*(.*?)\s*!!\}~s', static function ($m) {
            return '<?= ' . $m[1] . ' ?>';
        }, $code) ?? $code;
    }

    private static function compileEscapedEchos(string $code, bool $onlyVars): string
    {
        return preg_replace_callback('~\{\{\s*(.*?)\s*\}\}~s', static function ($m) use ($onlyVars) {
            $expr = trim($m[1]);
            if ($onlyVars) {
                if (str_contains($expr, '(') || str_contains($expr, '::') || preg_match('/->\s*\(/', $expr)) {
                    throw new \RuntimeException('Echo allows variables/props/indexes only: ' . $expr);
                }
                $ok = (bool)preg_match(
                    '/^\$[A-Za-z_][A-Za-z0-9_]*(?:(\?->|->)[A-Za-z_][A-Za-z0-9_]*|\[[^\]\[]+\])*$/',
                    $expr
                );
                if (!$ok) {
                    throw new \RuntimeException('Echo allows variables/props/indexes only: ' . $expr);
                }
            }
            return '<?= htmlspecialchars(' . $expr . ', ENT_QUOTES, \'UTF-8\') ?>';
        }, $code) ?? $code;
    }
}
