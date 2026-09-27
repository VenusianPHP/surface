<?php

/** @return array<string, array<string, mixed>> component directory => its manifest */
function splitManifests(): array
{
    $manifests = [];

    foreach (glob(dirname(__DIR__, 2).'/src/Surface/*/composer.json') as $path) {
        $manifests[basename(dirname($path))] = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    return $manifests;
}

/** @return array<string, mixed> */
function rootManifest(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('publishes every split under the venusian-surface vendor, named after its directory', function () {
    foreach (splitManifests() as $directory => $manifest) {
        $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $directory));

        expect($manifest['name'])->toBe("venusian-surface/{$kebab}");
    }
});

it('declares exactly the splits in the root replace map', function () {
    $splits = array_map(fn (array $manifest): string => $manifest['name'], array_values(splitManifests()));
    $replaced = array_keys(rootManifest()['replace']);
    sort($splits);
    sort($replaced);

    expect($replaced)->toBe($splits);
});

it('never names the surface vendor, which belongs to someone else on Packagist', function () {
    $root = dirname(__DIR__, 2);

    foreach ([$root.'/composer.json', ...glob($root.'/src/Surface/*/composer.json')] as $path) {
        expect(file_get_contents($path))->not->toContain('"surface/');
    }
});

it('gives every split its own .gitattributes and LICENSE', function () {
    foreach (array_keys(splitManifests()) as $directory) {
        $base = dirname(__DIR__, 2)."/src/Surface/{$directory}";

        expect(is_file("{$base}/.gitattributes"))->toBeTrue()
            ->and(is_file("{$base}/LICENSE"))->toBeTrue();
    }
});

it('requires gpio/contracts only where embedded displays import it, and keeps canvas on contracts alone', function () {
    $splits = splitManifests();

    expect(rootManifest()['require'])->toHaveKey('gpio/contracts')
        ->and($splits['EmbeddedDisplays']['require'])->toHaveKey('gpio/contracts')
        ->and(array_keys($splits['Canvas']['require']))->toBe(['php', 'venusian-surface/contracts']);

    foreach ($splits as $directory => $manifest) {
        if ($directory !== 'EmbeddedDisplays') {
            expect($manifest['require'])->not->toHaveKey('gpio/contracts');
        }
    }
});
