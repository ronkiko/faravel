<!-- docs/viewmodels.md v0.2.2 -->
# ViewModels (контракты Faravel)

> Обязательно к прочтению перед изменением Blade и сервисов.  
> Шаблоны — «немые»: только `@if/@foreach/@include` и вывод `{{ }}`. Никакого PHP.

## LayoutVM — ОБЯЗАТЕЛЬНЫЙ контракт

С **v0.4.4** контракт окончательно зафиксирован как `site.*` (вместо старого `brand.*`).

### Данные

```php
array{
  locale: string,
  title: string,

  site: array{                 // ВЕРХНИЙ БЛОК (баннер с логотипом)
    title: string,             // подпись (обычно 'FARAVEL')
    logo: array{ url: string}, // site.logo.url
    home: array{ url: string}, // site.home.url
  },

  nav: array{
    active: string,            // ОБЯЗАТЕЛЬНО: 'home'|'forum'|'admin'|'login'|'register'|...
    links: array{              // базовые всегда присутствуют
      home: string,
      forum: string,
      login: string,
      register: string,
      admin?: string,
      logout?: string,
    },
    auth: array{
      is_auth: bool,
      is_admin: bool,
      username: string,
    }
  }
}
