<?php

namespace RouteBrowserTests;

/**
 * These are just some basic tests to make sure the package runs.
 *
 * I hope to replace them with more detailed tests in the future.
 */
class SmokeTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        config(['app.debug' => true, 'app.env' => 'local', 'route-browser.exclude-self' => false]);
    }

    public function testListPage()
    {
        $this->get('/routes')
            ->assertOk()
            ->assertSeeText('/routes');
    }

    public function testFilter()
    {
        $this->get('/routes?uri=%2Froutes')
            ->assertOk()
            ->assertSeeText('/routes');
    }

    public function testFilterNoMatch()
    {
        $this->get('/routes?uri=%2Fother')
            ->assertOk()
            ->assertDontSeeText('/routes');
    }
}
