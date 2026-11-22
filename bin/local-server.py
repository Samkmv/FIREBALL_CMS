#!/usr/bin/env python3
"""Start/stop the project's private native PHP/MySQL installation."""
import fcntl
import os
from pathlib import Path
import signal
import socket
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parent.parent
LOCAL = ROOT / '.local'
RUN = LOCAL / 'run'
LOGS = LOCAL / 'logs'
PHP = LOCAL / 'php/php'
MYSQL = LOCAL / 'mysql/bin/mysqld'
URL = 'http://127.0.0.1:8080'


def pid_for(name):
    try:
        pid = int((RUN / (name + '.pid')).read_text().strip())
        if pid <= 1:
            return None
        command = subprocess.check_output(
            ['ps', '-p', str(pid), '-o', 'command='], text=True,
            stderr=subprocess.DEVNULL).strip()
        expected = str(PHP if name == 'php' else MYSQL)
        return pid if command.startswith(expected + ' ') else None
    except (OSError, ValueError, subprocess.CalledProcessError):
        return None


def port_open(port):
    with socket.socket() as connection:
        connection.settimeout(0.2)
        return connection.connect_ex(('127.0.0.1', port)) == 0


def spawn(name, arguments):
    environment = dict(os.environ, TMPDIR=str(LOCAL / 'tmp'), LC_ALL='C')
    if name == 'php':
        environment['PHP_CLI_SERVER_WORKERS'] = '4'
    with (LOGS / (name + '.log')).open('ab') as log:
        process = subprocess.Popen(arguments, cwd=ROOT, env=environment,
                                   stdin=subprocess.DEVNULL, stdout=log,
                                   stderr=log, start_new_session=True)
    (RUN / (name + '.pid')).write_text(str(process.pid) + '\n')
    return process


def stop_one(name):
    pid = pid_for(name)
    if pid is None:
        return
    # The PHP workers share the new process group; MySQL shuts down on SIGTERM.
    try:
        os.killpg(pid, signal.SIGTERM)
    except ProcessLookupError:
        return
    for _ in range(300):
        if pid_for(name) is None:
            (RUN / (name + '.pid')).unlink(missing_ok=True)
            return
        time.sleep(0.1)
    raise RuntimeError('Не удалось остановить ' + name + '; см. .local/logs/')


def start():
    for executable in (PHP, MYSQL):
        if not executable.is_file():
            raise RuntimeError('Не найдена локальная установка: ' + str(executable))
    if not (LOCAL / 'data/mysql').is_dir():
        raise RuntimeError('Локальная база MySQL ещё не инициализирована.')
    started = []
    try:
        for name, port in (('mysql', 3306), ('php', 8080)):
            if pid_for(name) is not None:
                continue
            if port_open(port):
                raise RuntimeError('Порт %s уже занят другим процессом.' % port)
            if name == 'mysql':
                arguments = [str(MYSQL), '--defaults-file=' + str(LOCAL / 'my.cnf')]
            else:
                arguments = [str(PHP), '-c', str(LOCAL / 'php.ini'),
                             '-S', '127.0.0.1:8080', '-t', str(ROOT / 'public'),
                             str(ROOT / 'bin/local-router.php')]
            process = spawn(name, arguments)
            started.append(name)
            for _ in range(300):
                if process.poll() is not None:
                    raise RuntimeError(name + ' завершился; см. .local/logs/' + name + '.log')
                if port_open(port):
                    break
                time.sleep(0.1)
            else:
                raise RuntimeError('Истекло время запуска ' + name)
    except Exception:
        for name in reversed(started):
            stop_one(name)
        raise
    print('FIREBALL CMS: ' + URL)
    print('MySQL: 127.0.0.1:3306; база fireball_local')
    print('Данные доступа: .local/credentials.txt')


def main():
    action = sys.argv[1] if len(sys.argv) == 2 else 'help'
    if action not in ('start', 'stop', 'restart', 'status'):
        print('Использование: python3 bin/local-server.py start|stop|restart|status')
        return 2
    for directory in (RUN, LOGS, LOCAL / 'tmp', LOCAL / 'sessions'):
        directory.mkdir(parents=True, exist_ok=True)
    with (RUN / 'control.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        if action in ('stop', 'restart'):
            stop_one('php')
            stop_one('mysql')
            print('PHP и MySQL остановлены. База данных сохранена.')
        if action in ('start', 'restart'):
            start()
        if action == 'status':
            for name in ('mysql', 'php'):
                pid = pid_for(name)
                print(name + ': ' + ('работает (PID %s)' % pid if pid else 'остановлен'))
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, RuntimeError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
