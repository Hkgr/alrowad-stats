<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendShellTest extends TestCase
{
    public function test_print_routes_serve_the_frontend_and_missing_assets_stay_missing(): void
    {
        $index = public_path('index.html');
        $existed = is_file($index);
        $previous = $existed ? file_get_contents($index) : null;
        file_put_contents($index, '<!doctype html><div id="root">rowad-shell</div>');

        try {
            foreach (['/report?institution=rowad', '/institution', '/organization', '/organization/report'] as $path) {
                $response = $this->get($path)->assertOk();
                $this->assertSame($index, $response->baseResponse->getFile()->getPathname());
                $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
            }

            $this->get('/assets/missing.js')->assertNotFound();
            $this->get('/api/v1/does-not-exist')->assertNotFound();
        } finally {
            if ($existed) {
                file_put_contents($index, $previous);
            } else {
                @unlink($index);
            }
        }
    }
}
