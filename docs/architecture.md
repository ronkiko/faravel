<!-- docs/architecture.md -->
# Архитектура (сокращённо)

- **Controller (тонкий):** получает доменные данные, формирует Page-VM, передаёт
  `layout_overrides` (минимум: `nav_active`, `title`). **Не** строит layout.
- **Service (LayoutService):** единственная точка сборки LayoutVM (`site.*`, `nav.*`, `title`).
- **View Composer (LayoutComposer):** единая точка инъекции layout во View:
  берёт `layout_overrides` из данных вида, вызывает `LayoutService::build(...)`,
  инъецирует `$layout`. Уважает готовые `$layout['__built']=true` (legacy).
- **Blade:** «немой», печатает готовые ключи VM; никакой PHP-логики.

## VM-контракт (layout)

Обязательные ключи:
- `site.logo.url` — URL логотипа,
- `site.home.url` — URL ссылки «домой»,
- `site.title` — заголовок сайта (текст),
- `nav.active` — ключ активного пункта меню (`home|forum|login|register|admin`),
- `title` — заголовок страницы/вкладки.

## Поток запроса

Request
→ Controller (PageVM + layout_overrides)
→ View
↳ LayoutComposer (берёт overrides → LayoutService::build → $layout)
→ Blade (немой рендер)
→ Response


## Примечания

- Старое имя композера — `ForumBasePageComposer` — сохранено как делегирующая обёртка
  для совместимости, но новые регистрации используют `LayoutComposer`.
