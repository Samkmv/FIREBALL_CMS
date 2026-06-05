<?php

namespace App\Controllers;

use App\Services\InstallService;
use FBL\Controller;

final class InstallController extends Controller
{
    private InstallService $installer;

    public function __construct()
    {
        $this->installer = new InstallService();
    }

    public function index()
    {
        $step = (string)request()->get('step', 'language');
        $data = (array)session()->get('install.data', []);
        $result = (array)session()->get('install.result', []);
        session()->remove('install.result');

        return view('install/index', [
            'title' => 'FIREBALL CMS Installation',
            'step' => $step,
            'data' => $data,
            'result' => $result,
            'requirements' => $this->installer->requirements(),
            'requirements_pass' => $this->installer->requirementsPass(),
            'languages' => LANGS,
            'default_site_url' => $this->installer->defaultSiteUrl(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ], false);
    }

    public function submit(): void
    {
        $step = (string)request()->post('step', 'language');
        $data = (array)session()->get('install.data', []);

        if ($step === 'language') {
            $locale = (string)request()->post('locale', 'ru');
            if (!array_key_exists($locale, LANGS)) {
                $locale = array_key_first(LANGS);
            }
            $data['locale'] = $locale;
            session()->set('install.data', $data);
            response()->redirect(base_url('/install?step=requirements'));
        }

        if ($step === 'requirements') {
            if (!$this->installer->requirementsPass()) {
                session()->set('install.result', ['status' => 'error', 'message' => 'System requirements are not met.']);
                response()->redirect(base_url('/install?step=requirements'));
            }
            response()->redirect(base_url('/install?step=database'));
        }

        if ($step === 'database') {
            $db = [
                'driver' => 'mysql',
                'host' => trim((string)request()->post('db_host', 'localhost')),
                'port' => (int)request()->post('db_port', 3306),
                'database' => trim((string)request()->post('db_name', '')),
                'username' => trim((string)request()->post('db_user', '')),
                'password' => (string)request()->post('db_password', ''),
                'prefix' => trim((string)request()->post('db_prefix', '')),
            ];
            $test = $this->installer->testDatabase($db);
            if (empty($test['ok'])) {
                session()->set('install.result', ['status' => 'error', 'message' => (string)($test['message'] ?? 'Database connection failed.')]);
                response()->redirect(base_url('/install?step=database'));
            }

            $data['db'] = $db;
            $data['db_tables'] = $test['tables'] ?? [];
            session()->set('install.data', $data);
            response()->redirect(base_url('/install?step=site'));
        }

        if ($step === 'site') {
            $data['site'] = [
                'name' => trim((string)request()->post('site_name', 'FIREBALL CMS')),
                'url' => rtrim(trim((string)request()->post('site_url', $this->installer->defaultSiteUrl())), '/'),
                'timezone' => trim((string)request()->post('timezone', APP_TIMEZONE)),
            ];
            session()->set('install.data', $data);
            response()->redirect(base_url('/install?step=admin'));
        }

        if ($step === 'admin') {
            $data['admin'] = [
                'login' => trim((string)request()->post('admin_login', 'creator')),
                'email' => mb_strtolower(trim((string)request()->post('admin_email', ''))),
                'password' => (string)request()->post('admin_password', ''),
                'password_confirmation' => (string)request()->post('admin_password_confirmation', ''),
            ];
            $data['demo'] = (bool)request()->post('install_demo', false);
            $data['allow_existing'] = (bool)request()->post('allow_existing', false);

            $result = $this->installer->install([
                'locale' => (string)($data['locale'] ?? 'ru'),
                'db' => (array)($data['db'] ?? []),
                'site' => (array)($data['site'] ?? []),
                'admin' => (array)($data['admin'] ?? []),
                'demo' => $data['demo'],
                'allow_existing' => $data['allow_existing'],
            ]);

            if (empty($result['ok'])) {
                session()->set('install.data', $data);
                session()->set('install.result', [
                    'status' => 'error',
                    'message' => (string)($result['message'] ?? 'Installation failed.'),
                    'requires_confirmation' => !empty($result['requires_confirmation']),
                    'tables' => $result['tables'] ?? [],
                ]);
                response()->redirect(base_url('/install?step=admin'));
            }

            session()->remove('install.data');
            session()->set('install.result', ['status' => 'success'] + $result);
            response()->redirect(base_url('/install?step=finish'));
        }

        response()->redirect(base_url('/install'));
    }
}
