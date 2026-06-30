<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Redirect to the return URL if valid, or fallback to the specified route.
     *
     * @param string $fallbackRoute
     * @param array $fallbackParams
     * @param int $status
     * @param array $headers
     * @param bool|null $secure
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function safeRedirect(string $fallbackRoute, array $fallbackParams = [], int $status = 302, array $headers = [], $secure = null)
    {
        $returnUrl = request()->input('return_url');

        if (!$returnUrl) {
            $returnUrl = url()->previous();
        }

        if ($returnUrl && $this->isValidLocalUrl($returnUrl)) {
            $currentUrl = request()->fullUrl();
            $actionPath = request()->getPathInfo();
            $parsedPrev = parse_url($returnUrl);
            $prevPath = $parsedPrev['path'] ?? '';

            // Prevent redirect loop to same action or edit/create path if redirecting from a store/update action
            if ($prevPath !== $actionPath && !str_ends_with($prevPath, '/edit') && !str_ends_with($prevPath, '/create')) {
                return redirect()->to($returnUrl, $status, $headers, $secure);
            }
        }

        return redirect()->route($fallbackRoute, $fallbackParams, $status, $headers);
    }

    /**
     * Check if a given URL is a valid local URL of this application.
     *
     * @param string|null $url
     * @return bool
     */
    protected function isValidLocalUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $appUrl = config('app.url');
        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        // Relative path (e.g. /admin/users)
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // Host matches the application configuration host
        if ($host && $appHost && strtolower($host) === strtolower($appHost)) {
            return true;
        }

        // Host matches current request host
        $reqHost = request()->getHost();
        if ($host && strtolower($host) === strtolower($reqHost)) {
            return true;
        }

        return false;
    }
}
