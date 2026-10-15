# Bootstrap 5.3.8

Локальная копия официальной сборки: https://getbootstrap.com/docs/5.3/getting-started/download/

- `bootstrap.min.css`: https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css
- `bootstrap.bundle.min.js` (включает Popper): https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js
- `LICENSE`: лицензия MIT из официального пакета Bootstrap 5.3.8.

Официальные контрольные суммы SHA-384 (SRI):

```
bootstrap.min.css
sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB

bootstrap.bundle.min.js
sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI
```

Сайт загружает файлы только из этой папки. `bootstrap.min.css` — исходник для сборки, непосредственно подключается `bootstrap.scoped.css`. Его создаёт `tools/build-tape-room-bootstrap.py`, чтобы Bootstrap не менял стили аудиоплеера. Скрипт оставляет глобальными только правила `.modal-backdrop`: Bootstrap добавляет этот элемент непосредственно в `body`.
