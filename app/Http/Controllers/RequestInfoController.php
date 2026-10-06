<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Публичная страница диагностики: показывает посетителю его собственный запрос
 * в том виде, в каком он дошёл до PHP через nginx.
 * Ничего о самом сервере (секреты, версии, внутренние адреса) не выводит.
 */
class RequestInfoController extends Controller
{
    /** Значения этих заголовков скрываем: это сессии и токены. */
    private const HIDDEN_HEADERS = [
        'cookie',
        'authorization',
        'proxy-authorization',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    /** Заголовки, которые обычно ставят прокси. Показываем, есть ли они. */
    private const PROXY_HEADERS = [
        'x-forwarded-for' => 'Настоящий IP клиента (по словам прокси)',
        'x-forwarded-proto' => 'Схема, по которой клиент пришёл к прокси',
        'x-forwarded-host' => 'Домен, который запросил клиент',
        'x-forwarded-port' => 'Порт, на который пришёл клиент',
        'x-real-ip' => 'Настоящий IP клиента (вариант nginx)',
        'forwarded' => 'Стандартный (RFC 7239) вариант всего вышеперечисленного',
    ];

    /**
     * Переменные, которые nginx передаёт в PHP сам (fastcgi_params).
     * Строго по списку: весь $_SERVER выводить нельзя, там значения из .env.
     */
    private const NGINX_PARAMS = [
        'REMOTE_ADDR' => 'IP того, кто подключился к nginx',
        'REMOTE_PORT' => 'Порт на стороне подключившегося',
        'SERVER_NAME' => 'Имя из server_name в конфиге nginx',
        'SERVER_PORT' => 'Порт, на который пришёл запрос',
        'HTTPS' => '«on», если nginx расшифровал HTTPS',
        'REQUEST_SCHEME' => 'Схема: http или https',
        'SERVER_PROTOCOL' => 'Версия HTTP между браузером и nginx',
        'REQUEST_METHOD' => 'Метод запроса',
        'REQUEST_URI' => 'Запрошенный путь',
        'GATEWAY_INTERFACE' => 'Протокол связи nginx → PHP (FastCGI)',
    ];

    /** Чтобы гигантский заголовок не раздувал страницу. */
    private const MAX_VALUE_LENGTH = 500;

    public function __invoke(Request $request): Response
    {
        $summary = [
            [
                'label' => 'IP посетителя (по мнению Laravel)',
                'value' => $request->ip(),
                'hint' => 'Этот IP используется в логах и лимитах запросов',
            ],
            [
                'label' => 'Кто подключился напрямую (REMOTE_ADDR)',
                'value' => $request->server('REMOTE_ADDR'),
                'hint' => 'Прокси нет, поэтому должен совпадать со строкой выше',
            ],
            [
                'label' => 'Laravel поверил прокси-заголовкам',
                'value' => $request->isFromTrustedProxy() ? 'да' : 'нет',
                'hint' => 'Должно быть «нет»: доверять некому',
            ],
            [
                'label' => 'HTTPS',
                'value' => $request->isSecure() ? 'да' : 'нет',
                'hint' => 'nginx сам расшифровал соединение и сообщил PHP',
            ],
            [
                'label' => 'Домен (Host)',
                'value' => $request->getHost(),
                'hint' => 'Чужие домены отсекаются ещё в nginx',
            ],
        ];

        $headers = collect($request->headers->all())
            ->map(fn (array $values, string $name) => $this->headerValue($request, $name, $values))
            ->sortKeys();

        $proxyHeaders = collect(self::PROXY_HEADERS)
            ->map(fn (string $description, string $name) => [
                'value' => $request->headers->has($name)
                    ? $this->limit(implode(', ', $request->headers->all($name)))
                    : null,
                'description' => $description,
            ]);

        $nginxParams = collect(self::NGINX_PARAMS)
            ->map(fn (string $description, string $key) => [
                'value' => $this->limit((string) ($request->server($key) ?? '—')),
                'description' => $description,
            ]);

        return response()
            ->view('request-info', compact('summary', 'proxyHeaders', 'headers', 'nginxParams'))
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    private function headerValue(Request $request, string $name, array $values): string
    {
        if ($name === 'cookie') {
            $names = array_keys($request->cookies->all());

            return '•••• значения скрыты · имена: '.($names ? implode(', ', $names) : '—');
        }

        if (in_array($name, self::HIDDEN_HEADERS, true)) {
            return '•••• скрыто';
        }

        return $this->limit(implode(', ', $values));
    }

    private function limit(string $value): string
    {
        return mb_strimwidth($value, 0, self::MAX_VALUE_LENGTH, '…');
    }
}
