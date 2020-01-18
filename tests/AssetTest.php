<?php

namespace RouteBrowserTests;

class AssetTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        config(['app.debug' => true, 'app.env' => 'local']);
    }

    public function listOfAssets()
    {
        return [
            ['route-browser.css', 'text/css'],
            ['route-browser.js', 'application/javascript'],
            ['favicon.png', 'image/png'],
        ];
    }

    /** @dataProvider listOfAssets */
    public function testAsset($name, $type)
    {
        $response = $this->get("/routes/assets/$name");

        $response->assertOk();
        $response->assertHeader('Content-Type', $type);

        $this->expectOutputString(file_get_contents(__DIR__ . "/../build/$name"));
        $response->sendContent();
    }

    public function testMissingFile()
    {
        $this
            ->withExceptionHandling()
            ->get('/routes/assets/invalid.css')
            ->assertNotFound();
    }

    public function testUnsafeFilename()
    {
        $this
            ->withExceptionHandling()
            ->get('/routes/assets/../composer.json')
            ->assertNotFound();
    }
}
