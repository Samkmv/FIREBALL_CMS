# Подключение Camera Manager к RTSP-серверу

Для MAXIPAPA рекомендуется режим `HTTPS pull`:

- CMS размещена на `https://maxipapa.ru` в SprintHost и загружается по FTP;
- потоки обслуживает отдельный RTSP-сервер;
- RTSP-сервер сам проверяет CMS раз в минуту;
- CMS не получает SSH-доступ к RTSP-серверу.

## 1. Загрузка плагина на SprintHost

Загрузите весь каталог `plugins/camera-manager` по FTP в одноимённый каталог CMS. В административной панели FIREBALL CMS установите и активируйте Camera Manager.

## 2. Создание общего токена

На RTSP-сервере от `root` выполните:

```bash
openssl rand -hex 32
```

Скопируйте полученную строку из 64 символов. В FIREBALL CMS откройте «Камеры → Настройки»:

```text
Режим:            HTTPS: RTSP-сервер забирает настройки
Секретный токен:  полученная строка
Базовый HLS URL:  https://rtsp.ddns.net/rtsp
```

После сохранения CMS оставляет только SHA-256 хеш. Исходный токен понадобится ещё один раз в конфигурации RTSP-сервера.

## 3. Установка агента на RTSP-сервере

Передайте на RTSP-сервер следующие файлы из каталога `server`:

```text
fireball-camera-pull
fireball-camera-verify
fireball-camera-diagnostics
fireball-camera-pull.service
fireball-camera-pull.timer
fireball-camera-pull.conf.example
```

В каталоге с переданными файлами выполните от `root`:

```bash
install -o root -g root -m 0755 fireball-camera-pull /usr/local/sbin/fireball-camera-pull
install -d -o root -g root -m 0755 /usr/local/libexec
install -o root -g root -m 0755 fireball-camera-verify /usr/local/libexec/fireball-camera-verify
install -o root -g root -m 0755 fireball-camera-diagnostics /usr/local/libexec/fireball-camera-diagnostics
install -o root -g root -m 0600 fireball-camera-pull.conf.example /etc/fireball-camera-pull.conf
install -o root -g root -m 0644 fireball-camera-pull.service /etc/systemd/system/fireball-camera-pull.service
install -o root -g root -m 0644 fireball-camera-pull.timer /etc/systemd/system/fireball-camera-pull.timer
install -d -o root -g root -m 0700 /var/lib/fireball-camera-manager
```

Откройте `/etc/fireball-camera-pull.conf` и замените значение `CMS_TOKEN` на тот же токен:

```text
CMS_ENDPOINT=https://cms.example.com/api/camera-manager/pull
CMS_TOKEN=64_СИМВОЛА_ИЗ_OPENSSL
```

Файл должен остаться доступным только root:

```bash
chown root:root /etc/fireball-camera-pull.conf
chmod 0600 /etc/fireball-camera-pull.conf
```

## 4. Проверка без изменения камер

До первой публикации ревизия CMS равна нулю, поэтому следующая команда только проверит HTTPS и авторизацию:

```bash
perl -c /usr/local/sbin/fireball-camera-pull
/usr/local/sbin/fireball-camera-pull --self-test
perl -c /usr/local/libexec/fireball-camera-verify
perl -c /usr/local/libexec/fireball-camera-diagnostics
/usr/local/libexec/fireball-camera-diagnostics --self-test
systemctl daemon-reload
systemctl start fireball-camera-pull.service
systemctl status --no-pager fireball-camera-pull.service
```

После успешного запуска в «Камеры → Настройки → Проверить подключение» появится время последнего обращения агента. `streams.pl` на этом этапе не меняется.

## 5. Включение таймера

```bash
systemctl enable --now fireball-camera-pull.timer
systemctl list-timers --all fireball-camera-pull.timer
```

Таймер проверяет новую ревизию примерно раз в минуту. Если изменений нет, агент ничего не пишет и не перезапускает.

## 6. Первая публикация

В CMS создайте объект и камеры, откройте «Конфигурация» и проверьте ключи потоков. После нажатия «Опубликовать» RTSP-сервер:

1. добавит только блок Camera Manager;
2. сохранит существующие записи без изменений;
3. выполнит `perl -c`;
4. создаст backup в `/var/www/html/rtsp/.fireball-camera-manager-backups`;
5. перезапустит службу и проверит, что она активна;
6. при ошибке автоматически вернёт исходный `streams.pl`.

Результат можно проверить командами:

```bash
systemctl start fireball-camera-pull.service
journalctl -u fireball-camera-pull.service -n 50 --no-pager
perl -c /var/www/html/rtsp/streams.pl
systemctl is-active rtsp-streams.service
```

## Подключение нового объекта

Пример ниже содержит только placeholder-адреса:

```text
Объект:        34
Router:        192.168.34.1
VPN:           10.10.0.34
LAN:           192.168.34.0/24
Recorder:      192.168.34.100
RTSP:          554
External RTSP: 55434
```

Порядок: Keenetic/Netcraze → WireGuard peer → firewall WireGuard/LAN → route на RTSP-сервере → сгенерированные NAT scripts → RTSP auto-detect → камеры → публикация → HLS/poster.

Camera Manager показывает peer и содержимое `.up.sh`/`.down.sh`, но не записывает `/etc/wireguard/wg0.conf`, не запускает iptables и не перезапускает WireGuard. Скрипты используют `iptables -C ... || iptables -A ...`; down-файл удаляет только правила и маршрут конкретного объекта.

## Whitelist diagnostics

Обновлённый `fireball-camera-pull` объявляет capability `diagnostics_v1`. Старые агенты продолжают публиковать камеры, но не выполняют новые проверки.

Разрешены только структурированные операции `wg_peer`, `route_check`, `ping_check`, `tcp_check`, `port_discovery`, `rtsp_probe`, `hls_check`. Помощник повторно проверяет IP, порты, interface, PublicKey и RTSP presets. Произвольная команда или shell fragment отклоняются.

Credentials RTSP не входят в аргументы helper-процесса: JSON передаётся через stdin. Пароль не возвращается в CMS, не печатается в stdout/stderr и не попадает в journalctl. Полный URL с паролем не сохраняется в diagnostic queue.

## Альтернативный SSH-режим

Файлы `fireball-camera-agent` и `fireball-camera-verify` сохраняют поддержку forced-command SSH. Этот режим предназначен для CMS-серверов с полноценным SSH и возможностью хранить приватный ключ вне web root. Для дополнительного FTP-аккаунта SprintHost он не используется.
